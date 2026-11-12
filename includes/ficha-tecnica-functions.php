<?php

if (!defined('ABSPATH')) {
    exit;
}

// FICHA TÉCNICA DE PRODUTO (PRECODE)
// Descrição, dimensões, peso, EAN, categoria, marca, fotos e dados de grade
// (cor/voltagem/tamanho) — tudo que falta para uma página de produto rica.
//
// Roda separado do lote de estoque/preço porque esses dados mudam raramente;
// não faz sentido buscá-los a cada poucos minutos.

// Busca a ficha técnica completa de um SKU na Precode (produtoLoja/ProdutoSku)
if (!function_exists('woo_importador_buscar_ficha_tecnica_precode')) {
function woo_importador_buscar_ficha_tecnica_precode($sku) {
    $sku = intval($sku);
    if ($sku <= 0) {
        return null;
    }

    $url = "https://www.precode.com.br/api/v1/produtoLoja/ProdutoSku/{$sku}";
    $headers = [
        'Authorization' => 'Basic MzVMck1BWGppN1FpeHBNY3E6',
        'Content-Type' => 'application/json'
    ];

    $response = wp_remote_get($url, [
        'headers' => $headers,
        'timeout' => 20
    ]);

    if (is_wp_error($response)) {
        error_log("[WooImportador] Erro ao buscar ficha técnica do SKU {$sku}: " . $response->get_error_message());
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!$data || !isset($data['produto'])) {
        error_log("[WooImportador] Ficha técnica vazia/inválida para SKU {$sku}");
        return null;
    }

    return $data['produto'];
}
}

// Dentro do array "atributos" (uma entrada por variação do agrupador), acha a do SKU pesquisado
if (!function_exists('woo_importador_extrair_atributo_sku')) {
function woo_importador_extrair_atributo_sku($produto_precode, $sku) {
    if (empty($produto_precode['atributos']) || !is_array($produto_precode['atributos'])) {
        return [];
    }

    $sku = intval($sku);
    foreach ($produto_precode['atributos'] as $atributo) {
        if (isset($atributo['sku']) && intval($atributo['sku']) === $sku) {
            return $atributo;
        }
    }

    return $produto_precode['atributos'][0];
}
}

// Categoria hierárquica nativa do WooCommerce: categoria (pai) > subcategoria (filho)
if (!function_exists('woo_importador_definir_categoria_precode')) {
function woo_importador_definir_categoria_precode($produto_id, $categoria, $subcategoria = '') {
    $categoria = trim((string) $categoria);
    if (empty($categoria)) {
        return;
    }

    $termo_pai = term_exists($categoria, 'product_cat');
    if (!$termo_pai) {
        $termo_pai = wp_insert_term($categoria, 'product_cat');
    }
    if (is_wp_error($termo_pai)) {
        return;
    }
    $id_pai = is_array($termo_pai) ? intval($termo_pai['term_id']) : intval($termo_pai);

    $ids_finais = [$id_pai];

    $subcategoria = trim((string) $subcategoria);
    if (!empty($subcategoria)) {
        $termo_filho = term_exists($subcategoria, 'product_cat', $id_pai);
        if (!$termo_filho) {
            $termo_filho = wp_insert_term($subcategoria, 'product_cat', ['parent' => $id_pai]);
        }
        if (!is_wp_error($termo_filho)) {
            $ids_finais[] = is_array($termo_filho) ? intval($termo_filho['term_id']) : intval($termo_filho);
        }
    }

    wp_set_object_terms($produto_id, $ids_finais, 'product_cat');
}
}

// Marca: usa a taxonomia nativa "product_brand" quando o site tiver o recurso Brands ativado;
// caso contrário, cria/usa o atributo global pa_marca — detectado em tempo de execução.
if (!function_exists('woo_importador_definir_marca_precode')) {
function woo_importador_definir_marca_precode($produto_id, $marca) {
    $marca = trim((string) $marca);
    if (empty($marca)) {
        return;
    }

    if (taxonomy_exists('product_brand')) {
        $termo = term_exists($marca, 'product_brand');
        if (!$termo) {
            $termo = wp_insert_term($marca, 'product_brand');
        }
        if (!is_wp_error($termo)) {
            $id = is_array($termo) ? intval($termo['term_id']) : intval($termo);
            wp_set_object_terms($produto_id, [$id], 'product_brand');
        }
        return;
    }

    if (!taxonomy_exists('pa_marca')) {
        if (!function_exists('wc_create_attribute')) {
            return;
        }
        wc_create_attribute([
            'name'         => 'Marca',
            'slug'         => 'marca',
            'type'         => 'select',
            'order_by'     => 'name',
            'has_archives' => false,
        ]);
        register_taxonomy('pa_marca', 'product', ['hierarchical' => false, 'label' => 'Marca', 'query_var' => true]);
    }

    $termo = term_exists($marca, 'pa_marca');
    if (!$termo) {
        $termo = wp_insert_term($marca, 'pa_marca');
    }
    if (is_wp_error($termo)) {
        return;
    }
    $id = is_array($termo) ? intval($termo['term_id']) : intval($termo);

    wp_set_object_terms($produto_id, [$id], 'pa_marca');

    $atributos = get_post_meta($produto_id, '_product_attributes', true);
    $atributos = is_array($atributos) ? $atributos : [];
    $atributos['pa_marca'] = [
        'name'         => 'pa_marca',
        'value'        => '',
        'is_visible'   => 1,
        'is_variation' => 0,
        'is_taxonomy'  => 1,
    ];
    update_post_meta($produto_id, '_product_attributes', $atributos);
}
}

// Especificações sem campo nativo (modelo, garantia, cor, variação) — aparecem na aba
// "Informação adicional" do WooCommerce, a mesma ficha técnica que ML/Amazon mostram.
if (!function_exists('woo_importador_definir_especificacoes_precode')) {
function woo_importador_definir_especificacoes_precode($produto_id, $especificacoes) {
    $atributos = get_post_meta($produto_id, '_product_attributes', true);
    $atributos = is_array($atributos) ? $atributos : [];

    // Remove especificações geradas por esta rotina em execuções anteriores, preservando o restante
    foreach ($atributos as $chave => $valor) {
        if (strpos($chave, 'precode_') === 0) {
            unset($atributos[$chave]);
        }
    }

    $posicao = count($atributos);
    foreach ($especificacoes as $rotulo => $valor) {
        $valor = trim((string) $valor);
        if ($valor === '' || $rotulo === '') {
            continue;
        }

        $chave = 'precode_' . sanitize_title($rotulo);
        $atributos[$chave] = [
            'name'         => $rotulo,
            'value'        => $valor,
            'position'     => $posicao++,
            'is_visible'   => 1,
            'is_variation' => 0,
            'is_taxonomy'  => 0,
        ];
    }

    update_post_meta($produto_id, '_product_attributes', $atributos);
}
}

// FUNÇÃO PRINCIPAL — sincroniza a ficha técnica completa de um produto
if (!function_exists('woo_importador_sincronizar_ficha_tecnica')) {
function woo_importador_sincronizar_ficha_tecnica($produto_id, $sku) {
    $produto_id = intval($produto_id);
    $sku = intval($sku);

    if ($produto_id <= 0 || $sku <= 0) {
        return false;
    }

    $dados = woo_importador_buscar_ficha_tecnica_precode($sku);
    if (!$dados) {
        return false;
    }

    $atributo = woo_importador_extrair_atributo_sku($dados, $sku);

    // --- Texto ---
    if (!empty($dados['descricao'])) {
        wp_update_post([
            'ID'           => $produto_id,
            'post_content' => wp_kses_post($dados['descricao']),
        ]);
    }

    // --- Dimensões físicas (campos nativos do WooCommerce, usados no frete e exibidos
    //     automaticamente na aba "Informação adicional") ---
    if (isset($dados['peso']))            update_post_meta($produto_id, '_weight', wc_format_decimal($dados['peso']));
    if (isset($dados['altura_cm']))       update_post_meta($produto_id, '_height', wc_format_decimal($dados['altura_cm']));
    if (isset($dados['largura_cm']))      update_post_meta($produto_id, '_width', wc_format_decimal($dados['largura_cm']));
    if (isset($dados['profundidade_cm'])) update_post_meta($produto_id, '_length', wc_format_decimal($dados['profundidade_cm']));

    // --- Identificador (GTIN/EAN — campo nativo desde WooCommerce 8.5) ---
    if (!empty($atributo['ean'])) {
        update_post_meta($produto_id, '_global_unique_id', sanitize_text_field($atributo['ean']));
    }

    // --- Fiscal / logística (sem campo nativo — meta própria do plugin) ---
    if (isset($dados['NCM']))     update_post_meta($produto_id, '_precode_ncm', sanitize_text_field($dados['NCM']));
    if (isset($dados['cubagem'])) update_post_meta($produto_id, '_precode_cubagem', sanitize_text_field($dados['cubagem']));
    if (isset($dados['volumes'])) update_post_meta($produto_id, '_precode_volumes', intval($dados['volumes']));
    if (isset($dados['prazoadicionaldeentrega'])) {
        update_post_meta($produto_id, '_precode_prazo_adicional', intval($dados['prazoadicionaldeentrega']));
    }

    // --- Categoria e marca ---
    if (!empty($dados['categoria'])) {
        woo_importador_definir_categoria_precode($produto_id, $dados['categoria'], $dados['subcategoria'] ?? '');
    }
    if (!empty($dados['marca'])) {
        woo_importador_definir_marca_precode($produto_id, $dados['marca']);
    }

    // --- Especificações visíveis (modelo, garantia, cor, variação) ---
    $especificacoes = [
        'Modelo'   => $dados['modelo'] ?? '',
        'Garantia' => !empty($dados['garantia']) ? $dados['garantia'] . ' meses' : '',
    ];
    if (!empty($atributo['tipogradex'])) {
        $especificacoes[$atributo['tipogradex']] = $atributo['nomegradex'] ?? '';
    }
    if (!empty($atributo['tipogradey'])) {
        $especificacoes[$atributo['tipogradey']] = $atributo['nomegradey'] ?? '';
    }
    woo_importador_definir_especificacoes_precode($produto_id, $especificacoes);

    // --- Fotos (idempotente — nunca baixa de novo uma imagem já importada) ---
    if (function_exists('woo_importador_sincronizar_fotos_precode')) {
        woo_importador_sincronizar_fotos_precode(
            $produto_id,
            $atributo['fotoprincipal'] ?? '',
            $atributo['fotosadicional'] ?? []
        );
    }

    // --- Metadados preparatórios para o agrupamento futuro em produto pai/variável ---
    // (o agrupamento em si será feito a partir do Sankhya; aqui só deixamos os dados prontos)
    update_post_meta($produto_id, '_precode_codigo_agrupador', intval($dados['codigoAgrupador'] ?? 0));
    update_post_meta($produto_id, '_precode_grade_x', intval($atributo['codigogradex'] ?? 0));
    update_post_meta($produto_id, '_precode_grade_x_tipo', sanitize_text_field($atributo['tipogradex'] ?? ''));
    update_post_meta($produto_id, '_precode_grade_x_nome', sanitize_text_field($atributo['nomegradex'] ?? ''));
    update_post_meta($produto_id, '_precode_grade_y', intval($atributo['codigogradey'] ?? 0));
    update_post_meta($produto_id, '_precode_grade_y_tipo', sanitize_text_field($atributo['tipogradey'] ?? ''));
    update_post_meta($produto_id, '_precode_grade_y_nome', sanitize_text_field($atributo['nomegradey'] ?? ''));

    update_post_meta($produto_id, '_precode_ficha_atualizada_em', current_time('mysql'));

    // Atualiza cache/lookup tables do WooCommerce após alterar meta "por fora" do objeto de produto
    if (function_exists('wc_delete_product_transients')) {
        wc_delete_product_transients($produto_id);
    }

    error_log("[WooImportador] Ficha técnica sincronizada: SKU {$sku} (produto ID {$produto_id})");
    return true;
}
}

// SINCRONIZAÇÃO EM LOTE — separada do lote de estoque/preço, com seu próprio offset
if (!function_exists('woo_importador_sincronizar_ficha_tecnica_lote')) {
function woo_importador_sincronizar_ficha_tecnica_lote($limite = 20) {
    ini_set('max_execution_time', 300);

    global $wpdb;
    $offset = get_option('woo_importador_ficha_offset', 0);
    $limite = max(1, intval($limite));

    $produtos = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID as produto_id, pm.meta_value as sku
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        AND pm.meta_key = '_sku'
        AND pm.meta_value != ''
        AND pm.meta_value REGEXP '^[0-9]+$'
        ORDER BY p.ID
        LIMIT %d OFFSET %d
    ", $limite, intval($offset)));

    if (!$produtos) {
        update_option('woo_importador_ficha_offset', 0);
        error_log("[WooImportador] Ficha técnica: todos os produtos foram processados, offset reiniciado.");
        return 0;
    }

    $sucessos = 0;
    foreach ($produtos as $produto) {
        if (woo_importador_sincronizar_ficha_tecnica(intval($produto->produto_id), $produto->sku)) {
            $sucessos++;
        }
    }

    update_option('woo_importador_ficha_offset', intval($offset) + $limite);
    error_log("[WooImportador] Lote de ficha técnica concluído: " . count($produtos) . " produtos | Sucessos: {$sucessos}");

    return $sucessos;
}
}

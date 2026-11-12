<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// FUNÇÕES UNIFICADAS E OTIMIZADA

// FUNÇÕES RAILWAY (ESTOQUE)

// Função para buscar estoque do Railway
if (!function_exists('woo_importador_buscar_estoque_railway')) {
function woo_importador_buscar_estoque_railway($sku) {
    $railway_url = get_option('woo_importador_railway_url', '');
    $railway_token = get_option('woo_importador_railway_token', '');
    
    if (empty($railway_url) || empty($railway_token)) {
        return null;
    }
    
    $url = rtrim($railway_url, '/') . '/api/v1/estoque/' . intval($sku); // Sanitização
    
    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . sanitize_text_field($railway_token),
            'Content-Type' => 'application/json'
        ],
        'timeout' => 15
    ]);
    
    if (is_wp_error($response)) {
        error_log("[WooImportador] Erro Railway para SKU {$sku}: " . $response->get_error_message());
        return null;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if ($data && $data['status'] === 'sucesso') {
        return intval($data['resultado']['estoque_total']);
    }
    
    error_log("[WooImportador] Resposta Railway inválida para SKU {$sku}: " . substr($body, 0, 200));
    return null;
}
}

// Função para buscar múltiplos estoques do Railway (SEGURA)
if (!function_exists('woo_importador_buscar_estoques_multiplos_railway')) {
function woo_importador_buscar_estoques_multiplos_railway($skus) {
    $railway_url = get_option('woo_importador_railway_url', '');
    $railway_token = get_option('woo_importador_railway_token', '');
    
    if (empty($railway_url) || empty($railway_token) || empty($skus)) {
        return [];
    }
    
    // CORREÇÃO: Sanitizar todos os SKUs
    $skus_sanitized = array_map('intval', $skus);
    $skus_sanitized = array_filter($skus_sanitized, function($sku) {
        return $sku > 0; // Remover SKUs inválidos
    });
    
    if (empty($skus_sanitized)) {
        return [];
    }
    
    // Limitar a 50 produtos por requisição para evitar timeouts
    if (count($skus_sanitized) > 50) {
        $skus_sanitized = array_slice($skus_sanitized, 0, 50);
    }
    
    $url = rtrim($railway_url, '/') . '/api/v1/estoque/multiplos';
    
    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . sanitize_text_field($railway_token),
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode(['codprods' => $skus_sanitized]),
        'timeout' => 30
    ]);
    
    if (is_wp_error($response)) {
        error_log("[WooImportador] Erro Railway múltiplos: " . $response->get_error_message());
        return [];
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if ($data && $data['status'] === 'sucesso') {
        return $data['resultado']; // Array [sku => estoque]
    }
    
    return [];
}
}

// Função para testar conexão Railway
if (!function_exists('woo_importador_testar_railway')) {
function woo_importador_testar_railway($url, $token) {
    $response = wp_remote_get(rtrim($url, '/') . '/health/ping', [
        'timeout' => 10
    ]);
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    return ($data && $data['status'] === 'healthy');
}
}

// FUNÇÕES RAILWAY (CODLOCAL) - NOVAS

/**
 * Buscar CODLOCAL de um produto específico
 * 
 * @param string|int $sku SKU do produto
 * @return string CODLOCAL ou 'default'
 */
if (!function_exists('woo_importador_get_codlocal')) {
function woo_importador_get_codlocal($sku) {
    $railway_url = get_option('woo_importador_railway_url', '');
    $railway_token = get_option('woo_importador_railway_token', '');
    
    if (empty($railway_url) || empty($railway_token)) {
        error_log("[WooImportador] Railway não configurado para buscar CODLOCAL");
        return 'default';
    }
    
    // Sanitizar SKU
    $sku = intval($sku);
    if ($sku <= 0) {
        return 'default';
    }
    
    // Tentar usar endpoint direto de CODLOCAL
    $url_codlocal = rtrim($railway_url, '/') . '/api/v1/produto/' . $sku . '/codlocal';
    
    $response = wp_remote_get($url_codlocal, [
        'headers' => [
            'Authorization' => 'Bearer ' . sanitize_text_field($railway_token),
            'Content-Type' => 'application/json'
        ],
        'timeout' => 10
    ]);
    
    // Se endpoint direto funcionar
    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && isset($data['codlocal'])) {
            $codlocal = sanitize_text_field($data['codlocal']);
            error_log("[WooImportador] CODLOCAL encontrado para SKU {$sku}: {$codlocal}");
            return $codlocal;
        }
    }
    
    // FALLBACK: Usar endpoint de locais de estoque
    $locais = woo_importador_get_locais_estoque($sku);
    
    if ($locais && isset($locais['locais'][0]['CODLOCAL'])) {
        $codlocal = sanitize_text_field($locais['locais'][0]['CODLOCAL']);
        error_log("[WooImportador] CODLOCAL extraído de locais para SKU {$sku}: {$codlocal}");
        return $codlocal;
    }
    
    error_log("[WooImportador] CODLOCAL não encontrado para SKU {$sku}, usando 'default'");
    return 'default';
}
}

/**
 * Buscar CODLOCAL de múltiplos produtos de uma vez
 * 
 * @param array $skus Array de SKUs
 * @return array Array associativo [sku => codlocal]
 */
if (!function_exists('woo_importador_get_codlocals_multiplos')) {
function woo_importador_get_codlocals_multiplos($skus) {
    $railway_url = get_option('woo_importador_railway_url', '');
    $railway_token = get_option('woo_importador_railway_token', '');
    
    if (empty($railway_url) || empty($railway_token) || empty($skus)) {
        return [];
    }
    
    // Sanitizar SKUs
    $skus_sanitized = array_map('intval', $skus);
    $skus_sanitized = array_filter($skus_sanitized, function($sku) {
        return $sku > 0;
    });
    
    if (empty($skus_sanitized)) {
        return [];
    }
    
    // Limitar a 100 produtos por requisição
    if (count($skus_sanitized) > 100) {
        $skus_sanitized = array_slice($skus_sanitized, 0, 100);
    }
    
    $url = rtrim($railway_url, '/') . '/api/v1/produtos/codlocals';
    
    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . sanitize_text_field($railway_token),
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode(['skus' => $skus_sanitized]),
        'timeout' => 20
    ]);
    
    if (is_wp_error($response)) {
        error_log("[WooImportador] Erro Railway CODLOCALs múltiplos: " . $response->get_error_message());
        
        // FALLBACK: Buscar um por um
        $result = [];
        foreach ($skus_sanitized as $sku) {
            $result[$sku] = woo_importador_get_codlocal($sku);
        }
        return $result;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if ($data && isset($data['codlocals']) && is_array($data['codlocals'])) {
        return $data['codlocals']; // [sku => codlocal]
    }
    
    // FALLBACK: Buscar um por um
    $result = [];
    foreach ($skus_sanitized as $sku) {
        $result[$sku] = woo_importador_get_codlocal($sku);
    }
    return $result;
}
}

/**
 * Buscar informações completas de estoque de um local específico
 * Inclui: CODLOCAL, estoque total, estoque disponível, reservado
 * 
 * @param string|int $sku SKU do produto
 * @return array|null Array com informações ou null
 */
if (!function_exists('woo_importador_get_locais_estoque')) {
function woo_importador_get_locais_estoque($sku) {
    $railway_url = get_option('woo_importador_railway_url', '');
    $railway_token = get_option('woo_importador_railway_token', '');
    
    if (empty($railway_url) || empty($railway_token)) {
        return null;
    }
    
    $sku = intval($sku);
    if ($sku <= 0) {
        return null;
    }
    
    // URL da API Railway para locais de estoque
    $url = rtrim($railway_url, '/') . '/api/v1/produto/' . $sku . '/locais-estoque';
    
    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . sanitize_text_field($railway_token),
            'Content-Type' => 'application/json'
        ],
        'timeout' => 15
    ]);
    
    if (is_wp_error($response)) {
        error_log("[WooImportador] Erro Railway locais estoque para SKU {$sku}: " . $response->get_error_message());
        return null;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if ($data && isset($data['locais']) && is_array($data['locais'])) {
        return [
            'locais' => $data['locais'],
            'estoque_total' => isset($data['estoque_total']) ? intval($data['estoque_total']) : 0
        ];
    }
    
    return null;
}
}

/**
 * Cache de CODLOCAL para evitar múltiplas requisições
 * 
 * @param string|int $sku SKU do produto
 * @return string CODLOCAL
 */
if (!function_exists('woo_importador_get_codlocal_cached')) {
function woo_importador_get_codlocal_cached($sku) {
    $cache_key = 'woo_imp_codlocal_' . intval($sku);
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        return $cached;
    }
    
    $codlocal = woo_importador_get_codlocal($sku);
    
    // Cache por 1 hora
    set_transient($cache_key, $codlocal, HOUR_IN_SECONDS);
    
    return $codlocal;
}
}

/**
 * Limpar cache de CODLOCAL
 * 
 * @param string|int|null $sku SKU específico ou null para limpar tudo
 */
if (!function_exists('woo_importador_limpar_cache_codlocal')) {
function woo_importador_limpar_cache_codlocal($sku = null) {
    if ($sku !== null) {
        $cache_key = 'woo_imp_codlocal_' . intval($sku);
        delete_transient($cache_key);
    } else {
        // Limpar todos os transients de CODLOCAL
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_woo_imp_codlocal_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_woo_imp_codlocal_%'");
    }
}
}

// FUNÇÃO PRINCIPAL DE SINCRONIZAÇÃO (SEGURA E OTIMIZADA)

if (!function_exists('woo_importador_atualizar_lote')) {
function woo_importador_atualizar_lote() {
    ini_set('max_execution_time', 300); // 5 minutos
    
    global $wpdb;
    $offset = get_option('woo_importador_offset', 0);
    
    // CORREÇÃO: Query mais segura e otimizada
    $limite_lote = 300; // Reduzido para evitar timeouts
    
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
    ", $limite_lote, intval($offset)));

    if (!$produtos) {
        error_log("[WooImportador] Todos os produtos foram processados.");
        update_option('woo_importador_offset', 0);
        return;
    }

    $usar_railway = get_option('woo_importador_usar_railway', '0');
    
    if ($usar_railway) {
        $sucessos_estoque = woo_importador_sincronizar_railway_seguro($produtos);
    } else {
        $sucessos_estoque = woo_importador_sincronizar_precode_original($produtos);
    }
    
    // Atualizar offset
    update_option('woo_importador_offset', intval($offset) + $limite_lote);
    
    error_log("[WooImportador] Lote concluído: " . count($produtos) . " produtos | Sucessos: {$sucessos_estoque}");
}
}

// NOVA FUNÇÃO: Sincronização Railway Segura
if (!function_exists('woo_importador_sincronizar_railway_seguro')) {
function woo_importador_sincronizar_railway_seguro($produtos) {
    $railway_url = get_option('woo_importador_railway_url', '');  
    $railway_token = get_option('woo_importador_railway_token', '');
    
    if (empty($railway_url) || empty($railway_token)) {
        error_log("[WooImportador] Railway não configurado");
        return 0;
    }
    
    $sucessos = 0;
    
    // OTIMIZAÇÃO: Aumentado para 50 para otimizar chamadas Railway
    $chunks = array_chunk($produtos, 50);
    
    foreach ($chunks as $chunk) {
        $codprods = array_map(function($produto) {
            return intval($produto->sku);
        }, $chunk);
        
        // Buscar estoques em lote via Railway
        $estoques_railway = woo_importador_buscar_estoques_multiplos_railway($codprods);
        
        if (empty($estoques_railway)) {
            error_log("[WooImportador] Nenhum estoque retornado pelo Railway para este lote");
            continue;
        }
        
        // Aplicar estoques
        foreach ($chunk as $produto) {
            $sku = intval($produto->sku);
            $produto_id = intval($produto->produto_id);
            
            if (isset($estoques_railway[$sku])) {
                $estoque = intval($estoques_railway[$sku]);
                
                // CORREÇÃO: Usar funções WordPress seguras
                // Correção: Usar funções nativas do WooCommerce para garantir atualização das taxonomias e tabelas de busca
                update_post_meta($produto_id, '_manage_stock', 'yes');
                $updated = update_post_meta($produto_id, '_stock', $estoque);
                
                // Essa é a função mágica que resolve o bug da vitrine! Ela atualiza a taxonomia (etiqueta) corretamente.
                wc_update_product_stock_status($produto_id, ($estoque > 0) ? 'instock' : 'outofstock');
                
                // Força atualização da lookup table também
                if (function_exists('wc_update_product_lookup_tables')) {
                    wc_update_product_lookup_tables($produto_id);
                }
                
                if ($updated !== false) {
                    $sucessos++;
                    error_log("[WooImportador] SKU {$sku}: estoque → {$estoque}");
                }
            }
        }
        
        // REMOVIDO: Pausa artificial entre lotes (usleep(200000);)
    }
    
    return $sucessos;  
}
}

// FUNÇÃO ORIGINAL PRECODE (MANTIDA INTACTA)
if (!function_exists('woo_importador_sincronizar_precode_original')) {
function woo_importador_sincronizar_precode_original($produtos) {
    $dados_api = woo_importador_coletar_todos_ativos();
    
    if (!$dados_api || !isset($dados_api['ativos'])) {
        error_log("[WooImportador] Falha ao obter dados da API Precode");
        return 0;
    }

    $ativos = $dados_api['ativos'];
    $sucessos = 0;

    foreach ($produtos as $produto) {
        $produto_id = intval($produto->produto_id);
        $referencia = sanitize_text_field($produto->sku);

        $encontrado = null;
        foreach ($ativos as $item) {
            if (isset($item['IdReferencia']) && $item['IdReferencia'] == $referencia) {
                $encontrado = $item;
                break;
            }
        }

        if (!$encontrado) {
            continue;
        }

        $estoque = isset($encontrado['saldo']) ? intval($encontrado['saldo']) : 0;
        $preco = isset($encontrado['precoSite']) ? floatval($encontrado['precoSite']) : null;

        if ($preco !== null && $preco > 0) {
            update_post_meta($produto_id, '_price', $preco);
            update_post_meta($produto_id, '_regular_price', $preco);
        }

        // Correção: Usar funções nativas do WooCommerce para garantir atualização das taxonomias e tabelas de busca
        update_post_meta($produto_id, '_manage_stock', 'yes');
        update_post_meta($produto_id, '_stock', $estoque);
        
        // Função mágica que resolve o bug da vitrine! Atualiza a taxonomia e limpa o outofstock preso.
        wc_update_product_stock_status($produto_id, ($estoque > 0) ? 'instock' : 'outofstock');
        
        // Força atualização da lookup table também
        if (function_exists('wc_update_product_lookup_tables')) {
            wc_update_product_lookup_tables($produto_id);
        }

        $sucessos++;
        error_log("[WooImportador] Precode: SKU {$referencia} | Estoque: {$estoque} | Preço: R$ {$preco}");
    }
    
    return $sucessos;
}
}

// Busca todos os produtos ativos (Precode - MANTIDA ORIGINAL)
if (!function_exists('woo_importador_coletar_todos_ativos')) {
function woo_importador_coletar_todos_ativos() {
    $url = "https://www.precode.com.br/api/v1/produtoLoja/ListaProduto/ativo";
    $headers = [
        'Authorization' => 'Basic MzVMck1BWGppN1FpeHBNY3E6',
        'Content-Type' => 'application/json'
    ];

    $response = wp_remote_get($url, [
        'headers' => $headers,
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        error_log("[WooImportador] Erro ao acessar a API de produtos ativos");
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return $data;
}
}

// FUNÇÕES DE TESTE E DEBUG (SEGURAS)

if (!function_exists('woo_importador_testar_produto_individual')) {
function woo_importador_testar_produto_individual($sku) {
    $sku = intval($sku); // Sanitização
    
    if ($sku <= 0) {
        echo "<p style='color: red;'>SKU inválido</p>";
        return;
    }
    
    $usar_railway = get_option('woo_importador_usar_railway', '0');
    
    if ($usar_railway) {
        echo "<h3>Testando Railway:</h3>";
        
        // Testar estoque
        $start_time = microtime(true);
        $estoque = woo_importador_buscar_estoque_railway($sku);
        $duration = round((microtime(true) - $start_time) * 1000);
        
        if ($estoque !== null) {
            echo "<p style='color: green;'>Estoque encontrado: {$estoque} (em {$duration}ms)</p>";
        } else {
            echo "<p style='color: red;'>Estoque não encontrado (em {$duration}ms)</p>";
        }
        
        // Testar CODLOCAL
        $start_time = microtime(true);
        $codlocal = woo_importador_get_codlocal($sku);
        $duration = round((microtime(true) - $start_time) * 1000);
        
        echo "<p style='color: blue;'>CODLOCAL: {$codlocal} (em {$duration}ms)</p>";
        
    } else {
        echo "<p style='color: blue;'>ℹUsando modo Precode (teste individual não disponível)</p>";
    }
    
    // Verificar produto no WordPress
    echo "<h3>Testando WordPress:</h3>";
    global $wpdb;
    $produto = $wpdb->get_row($wpdb->prepare(
        "SELECT p.ID, p.post_title 
         FROM {$wpdb->posts} p 
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
         WHERE pm.meta_key = '_sku' AND pm.meta_value = %s
         AND p.post_type = 'product' AND p.post_status = 'publish'",
        $sku
    ));
    
    if ($produto) {
        echo "<p style='color: green;'>Produto encontrado: " . esc_html($produto->post_title) . " (ID: {$produto->ID})</p>";
        
        $estoque_atual = get_post_meta($produto->ID, '_stock', true);
        $preco_atual = get_post_meta($produto->ID, '_price', true);
        
        echo "<p><strong>Estoque atual:</strong> " . ($estoque_atual ?: '0') . "</p>";
        echo "<p><strong>Preço atual:</strong> R$ " . number_format(floatval($preco_atual), 2, ',', '.') . "</p>";
        
    } else {
        echo "<p style='color: red;'>Produto não encontrado no WooCommerce</p>";
    }
}
}

// FUNÇÕES DE VALIDAÇÃO E SEGURANÇA

// Validar e sanitizar SKU
if (!function_exists('woo_importador_validar_sku')) {
function woo_importador_validar_sku($sku) {
    $sku = intval($sku);
    return ($sku > 0) ? $sku : false;
}
}

// Validar configuração Railway
if (!function_exists('woo_importador_validar_config_railway')) {
function woo_importador_validar_config_railway() {
    $usar_railway = get_option('woo_importador_usar_railway', '0');
    $railway_url = get_option('woo_importador_railway_url', '');
    $railway_token = get_option('woo_importador_railway_token', '');
    
    if (!$usar_railway) {
        return ['valido' => false, 'modo' => 'precode'];
    }
    
    if (empty($railway_url) || empty($railway_token)) {
        return ['valido' => false, 'erro' => 'URL ou Token vazio'];
    }
    
    if (!filter_var($railway_url, FILTER_VALIDATE_URL)) {
        return ['valido' => false, 'erro' => 'URL inválida'];
    }
    
    return ['valido' => true, 'modo' => 'railway'];
}
}

// Log seguro para debug
if (!function_exists('woo_importador_log_debug')) {
function woo_importador_log_debug($mensagem, $dados = null) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $log_message = "[WooImportador DEBUG] " . $mensagem;
        
        if ($dados !== null) {
            $log_message .= " | Dados: " . json_encode($dados, JSON_UNESCAPED_UNICODE);
        }
        
        error_log($log_message);
    }
}
}

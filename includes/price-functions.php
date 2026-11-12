<?php

if (!defined('ABSPATH')) {
    exit;
}

// FUNÇÕES DE GESTÃO DE PREÇOS

// Buscar preço personalizado da tabela
function woo_importador_buscar_preco_personalizado($sku) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'woo_importador_precos';
    
    $preco = $wpdb->get_var($wpdb->prepare(
        "SELECT preco_personalizado FROM $table_name WHERE sku = %s AND ativo = 1",
        $sku
    ));
    
    return $preco ? floatval($preco) : null;
}

// Atualizar preço de produto específico
function woo_importador_atualizar_preco_produto($sku, $novo_preco, $observacoes = '') {
    global $wpdb;
    
    // Buscar produto pelo SKU
    $produto_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
        $sku
    ));
    
    if (!$produto_id) {
        error_log("[WooImportador] Produto com SKU {$sku} não encontrado");
        return false;
    }
    
    // Atualizar preço no WooCommerce
    update_post_meta($produto_id, '_price', $novo_preco);
    update_post_meta($produto_id, '_regular_price', $novo_preco);
    
    // Salvar na tabela personalizada
    $table_name = $wpdb->prefix . 'woo_importador_precos';
    
    $wpdb->replace(
        $table_name,
        [
            'sku' => $sku,
            'produto_id' => $produto_id,
            'preco_atual' => get_post_meta($produto_id, '_price', true),
            'preco_personalizado' => $novo_preco,
            'origem_preco' => 'manual',
            'usuario_atualizacao' => get_current_user_id(),
            'observacoes' => $observacoes
        ],
        ['%s', '%d', '%f', '%f', '%s', '%d', '%s']
    );
    
    // Limpar cache do produto
    if (function_exists('wc_delete_product_transicients')) {
        wc_delete_product_transients($produto_id);
    }
    
    error_log("[WooImportador] Preço atualizado: SKU {$sku} → R$ {$novo_preco}");
    
    return true;
}

// Processar upload de preços CSV
function woo_importador_processar_upload_precos($arquivo) {
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        return ['sucesso' => false, 'erro' => 'Erro no upload do arquivo'];
    }
    
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    
    if ($extensao !== 'csv') {
        return ['sucesso' => false, 'erro' => 'Apenas arquivos CSV são suportados'];
    }
    
    $dados = woo_importador_ler_csv($arquivo['tmp_name']);
    
    if (empty($dados)) {
        return ['sucesso' => false, 'erro' => 'Arquivo vazio ou formato inválido'];
    }
    
    // Processar dados
    $processados = 0;
    $sucessos = 0;
    $erros = 0;
    
    foreach ($dados as $linha) {
        $processados++;
        
        if (empty($linha['sku']) || empty($linha['preco'])) {
            $erros++;
            continue;
        }
        
        $sku = sanitize_text_field($linha['sku']);
        $preco = floatval(str_replace(',', '.', $linha['preco']));
        $observacoes = isset($linha['observacoes']) ? sanitize_textarea_field($linha['observacoes']) : '';
        
        if ($preco <= 0) {
            $erros++;
            continue;
        }
        
        $resultado = woo_importador_atualizar_preco_produto($sku, $preco, $observacoes);
        
        if ($resultado) {
            $sucessos++;
        } else {
            $erros++;
        }
    }
    
    return [
        'sucesso' => true,
        'processados' => $processados,
        'sucessos' => $sucessos,
        'erros' => $erros
    ];
}

// Função para ler CSV
function woo_importador_ler_csv($arquivo) {
    $dados = [];
    
    if (($handle = fopen($arquivo, 'r')) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ',');
        
        if (!$headers) {
            return [];
        }
        
        while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
            if (count($row) === count($headers)) {
                $dados[] = array_combine($headers, $row);
            }
        }
        
        fclose($handle);
    }
    
    return $dados;
}
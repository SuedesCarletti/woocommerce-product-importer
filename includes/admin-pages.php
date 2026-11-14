<?php

if (!defined('ABSPATH')) {
    exit;
}

// PÁGINAS ADMINISTRATIVAS - VERSÃO CORRIGIDA COMPLETA

// Página principal (Sincronização)
function woo_importador_pagina_principal() {
    echo '<div class="wrap">';
    echo '<h1>Sincronização de Produtos</h1>';
    
    // Status da configuração
    $usar_railway = get_option('woo_importador_usar_railway', '0');
    $railway_url = get_option('woo_importador_railway_url', '');
    
    if ($usar_railway && !empty($railway_url)) {
        echo '<div class="notice notice-info"><p><strong>Railway Ativo:</strong> Estoque vem do Sankhya via Railway.</p></div>';
    } else {
        echo '<div class="notice notice-warning"><p><strong>Railway Inativo:</strong> Configure nas <a href="?page=woo-importador-config">Configurações</a>.</p></div>';
    }
    
    // Estatísticas seguras
    global $wpdb;
    $total_produtos = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value != ''");
    $offset_atual = get_option('woo_importador_offset', 0);
    $processados = min($offset_atual, intval($total_produtos));
    $restantes = max(0, intval($total_produtos) - $processados);
    
    echo '<div class="card" style="max-width: 600px; padding: 20px; margin: 20px 0;">';
    echo '<h3>Status da Sincronização</h3>';
    echo '<table style="width: 100%;">';
    echo '<tr><td><strong>Total de produtos:</strong></td><td>' . number_format($total_produtos) . '</td></tr>';
    echo '<tr><td><strong>Processados:</strong></td><td>' . number_format($processados) . '</td></tr>';
    echo '<tr><td><strong>Restantes:</strong></td><td>' . number_format($restantes) . '</td></tr>';
    
    if ($total_produtos > 0) {
        $progresso = round(($processados / $total_produtos) * 100, 1);
        echo '<tr><td><strong>Progresso:</strong></td><td>' . $progresso . '%</td></tr>';
    }
    
    echo '</table>';
    
    // Barra de progresso visual
    if ($total_produtos > 0 && $offset_atual > 0) {
        $progresso = round(($processados / $total_produtos) * 100, 1);
        echo '<div style="margin: 15px 0;">';
        echo '<div style="background: #f0f0f0; height: 24px; border-radius: 12px; overflow: hidden;">';
        echo '<div style="background: linear-gradient(90deg, #0073aa, #00a0d2); height: 100%; width: ' . $progresso . '%; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;">';
        if ($progresso > 20) echo $progresso . '%';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    echo '</div>';
    
    // Botões de ação
    echo '<div style="margin: 20px 0;">';
    
    // Form para sincronização padrão (Lote de 50) ou Reset
    echo '<form method="post" style="display: inline; margin-right: 10px;">'; woo_importador_nonce_field();
    echo '<input type="hidden" name="sync_limit" value="50">'; // Limite padrão 50 (incremental)
    
    if ($restantes > 0) {
        // Label corrigido para 50
        echo '<input type="submit" name="atualizar_produtos" class="button button-primary button-large" value="Sincronizar Próximo Lote (50 produtos)" style="margin-right: 10px;">';
    } else {
        // Label corrigido
        echo '<input type="submit" name="atualizar_produtos" class="button button-secondary button-large" value="Reiniciar Sincronização (50 produtos)" style="margin-right: 10px;">';
    }
    
    echo '<input type="submit" name="resetar_progresso" class="button button-secondary" value="Resetar Progresso" onclick="return confirm(\'Tem certeza que deseja resetar o progresso?\');">';
    echo '</form>';
    
    // Sincronização de Lote Grande (300) - Simula o Full Sync solicitado
    echo '<form method="post" style="display: inline;">'; woo_importador_nonce_field();
    echo '<input type="hidden" name="sync_limit" value="300">'; // Limite grande para full sync manual
    echo '<input type="submit" name="atualizar_produtos" class="button button-primary button-large" style="background-color: #d63638; border-color: #c72c2e; margin-left: 20px;" value="Sincronizar TUDO (Lote Grande 300 produtos)">';
    echo '</form>';

    echo '</div>';
    
    // Links úteis
    echo '<div style="margin: 20px 0;">';
    echo '<a href="?page=woo-importador-teste" class="button">Teste Individual</a> ';
    echo '<a href="?page=woo-importador-config" class="button">Configurações</a>';
    echo '</div>';
    
    echo '</div>';
    
    // Processar ações
    if (isset($_POST['atualizar_produtos']) && woo_importador_post_ok()) {
        // Captura o limite da requisição POST (50 ou 300)
        $sync_limit = intval($_POST['sync_limit'] ?? 50);

        if (function_exists('woo_importador_atualizar_lote')) {
            // Passa o limite capturado para a função
            woo_importador_atualizar_lote($sync_limit);
            echo '<div class="notice notice-success"><p>Lote processado! <a href="">Recarregar página</a> para ver o progresso.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Função de sincronização não encontrada.</p></div>';
        }
    }
    
    if (isset($_POST['resetar_progresso']) && woo_importador_post_ok()) {
        update_option('woo_importador_offset', 0);
        echo '<div class="notice notice-success"><p>Progresso resetado!</p></div>';
    }
}

// Página de configurações
function woo_importador_pagina_config() {
    echo '<div class="wrap">';
    echo '<h1>Configurações</h1>';
    
    // Salvar configurações
    if (isset($_POST['salvar_config']) && woo_importador_post_ok()) {
        update_option('woo_importador_usar_railway', isset($_POST['usar_railway']) ? '1' : '0');
        update_option('woo_importador_railway_url', sanitize_url($_POST['railway_url']));
        update_option('woo_importador_railway_token', sanitize_text_field($_POST['railway_token']));
        
        echo '<div class="notice notice-success"><p>Configurações salvas!</p></div>';
    }
    
    $usar_railway = get_option('woo_importador_usar_railway', '0');
    $railway_url = get_option('woo_importador_railway_url', '');
    $railway_token = get_option('woo_importador_railway_token', '');
    
    echo '<form method="post">'; woo_importador_nonce_field();
    echo '<table class="form-table">';
    
    // Configurações Railway
    echo '<tr><th colspan="2"><h2>Railway (Intermediador Sankhya)</h2></th></tr>';
    
    echo '<tr>';
    echo '<th scope="row">Usar Railway para Estoque</th>';
    echo '<td>';
    echo '<input type="checkbox" name="usar_railway" value="1" ' . checked(1, $usar_railway, false) . '>';
    echo '<label> Buscar estoque do Sankhya via Railway</label>';
    echo '<p class="description">Railway faz a ponte entre WordPress e Sankhya ERP</p>';
    echo '</td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th scope="row">URL do Railway</th>';
    echo '<td>';
    echo '<input type="url" name="railway_url" value="' . esc_attr($railway_url) . '" class="regular-text" placeholder="https://sua-app.railway.app">';
    echo '<p class="description">URL completa da sua aplicação Railway</p>';
    echo '</td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<th scope="row">Token API Railway</th>';
    echo '<td>';
    echo '<input type="password" name="railway_token" value="' . esc_attr($railway_token) . '" class="regular-text">';
    echo '<p class="description">Token de autenticação para as APIs do Railway</p>';
    echo '</td>';
    echo '</tr>';
    
    echo '</table>';
    
    echo '<p class="submit">';
    echo '<input type="submit" name="salvar_config" class="button button-primary" value="Salvar Configurações">';
    echo '</p>';
    
    echo '</form>';
    
    // Status do sistema
    echo '<div class="card" style="max-width: 600px; padding: 20px; margin: 20px 0;">';
    echo '<h3>Status do Sistema</h3>';
    
    // Testar Railway se configurado
    if ($usar_railway && !empty($railway_url) && !empty($railway_token)) {
        echo '<p><strong>Railway:</strong> ';
        if (function_exists('woo_importador_testar_railway')) {
            $teste_railway = woo_importador_testar_railway($railway_url, $railway_token);
            if ($teste_railway) {
                echo '<span style="color: green;">Conectado</span></p>';
            } else {
                echo '<span style="color: red;">Erro de conexão</span></p>';
            }
        } else {
            echo '<span style="color: orange;">Função de teste não encontrada</span></p>';
        }
    } else {
        echo '<p><strong>Railway:</strong> <span style="color: orange;">Não configurado</span></p>';
    }
    
    // Estatísticas básicas
    global $wpdb;
    $total_produtos = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_sku'");
    $offset_atual = get_option('woo_importador_offset', 0);
    
    echo '<p><strong>Total de Produtos:</strong> ' . number_format($total_produtos) . '</p>';
    echo '<p><strong>Progresso Sincronização:</strong> ' . number_format(min($offset_atual, $total_produtos)) . '/' . number_format($total_produtos) . '</p>';
    
    echo '</div>';
    
    // Informações sobre a arquitetura
    echo '<div class="card" style="max-width: 800px; padding: 20px; margin: 20px 0;">';
    echo '<h3>ℹComo Funciona</h3>';
    echo '<p><strong>Arquitetura:</strong> WordPress → Railway → Sankhya ERP</p>';
    echo '<ul>';
    echo '<li><strong>WordPress:</strong> Interface de gerenciamento</li>';
    echo '<li><strong>Railway:</strong> Intermediador que conecta com Sankhya</li>';
    echo '<li><strong>Sankhya:</strong> ERP com dados de estoque reais</li>';
    echo '</ul>';
    echo '<p><strong>Vantagens:</strong></p>';
    echo '<ul>';
    echo '<li>Maior segurança (Railway autentica no Sankhya)</li>';
    echo '<li>Melhor performance (Railway otimiza consultas)</li>';
    echo '<li>Logs centralizados (Railway monitora tudo)</li>';
    echo '<li>Escalabilidade (Railway pode servir múltiplos sites)</li>';
    echo '</ul>';
    echo '</div>';
    
    echo '</div>';
}

// Página de teste individual
function woo_importador_pagina_teste() {
    echo '<div class="wrap">';
    echo '<h1>Teste Individual de Produto</h1>';
    
    if (isset($_POST['testar_produto']) && woo_importador_post_ok()) {
        $sku = sanitize_text_field($_POST['sku_teste']);
        if (!empty($sku)) {
            woo_importador_testar_produto_simples($sku);
        }
    }
    
    echo '<div class="card" style="max-width: 700px; padding: 20px; margin: 20px 0;">';
    echo '<h3>Teste do Fluxo</h3>';
    echo '<p>Este teste verifica: <strong>WordPress → Railway → Sankhya</strong></p>';
    
    echo '<form method="post" style="margin: 20px 0;">'; woo_importador_nonce_field();
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row">SKU para Teste</th>';
    echo '<td>';
    echo '<input type="text" name="sku_teste" placeholder="Digite o SKU (ex: 25752)" required style="width: 200px;">';
    echo ' <input type="submit" name="testar_produto" class="button button-primary" value="Testar Produto">';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '</form>';
    echo '</div>';
    
    echo '</div>';
}

// Função de teste simplificada
function woo_importador_testar_produto_simples($sku) {
    echo '<div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">';
    echo '<h3>Resultado do Teste - SKU: ' . esc_html($sku) . '</h3>';
    
    $usar_railway = get_option('woo_importador_usar_railway', '0');
    $railway_url = get_option('woo_importador_railway_url', '');
    $railway_token = get_option('woo_importador_railway_token', '');
    
    if (!$usar_railway || empty($railway_url) || empty($railway_token)) {
        echo '<p style="color: orange;">Railway não está configurado - vá em <a href="?page=woo-importador-config">Configurações</a></p>';
        echo '</div>';
        return;
    }
    
    echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">';
    
    // Teste Railway
    echo '<div>';
    echo '<h4>1⃣ Testando Railway/Sankhya</h4>';
    
    if (function_exists('woo_importador_buscar_estoque_railway')) {
        $start_time = microtime(true);
        $estoque = woo_importador_buscar_estoque_railway($sku);
        $duration = round((microtime(true) - $start_time) * 1000);
        
        if ($estoque !== null) {
            echo '<p style="color: green;"><strong>Sucesso!</strong></p>';
            echo '<p><strong>Estoque encontrado:</strong> ' . $estoque . ' unidades</p>';
            echo '<p>⏱<strong>Tempo:</strong> ' . $duration . 'ms</p>';
        } else {
            echo '<p style="color: red;"><strong>Erro!</strong></p>';
            echo '<p>Estoque não encontrado</p>';
            echo '<p>⏱<strong>Tempo:</strong> ' . $duration . 'ms</p>';
        }
    } else {
        echo '<p style="color: red;">Função de busca não encontrada</p>';
    }
    echo '</div>';
    
    // Teste WordPress
    echo '<div>';
    echo '<h4>2⃣ Testando WordPress</h4>';
    
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
        echo '<p style="color: green;"><strong>Produto encontrado!</strong></p>';
        echo '<p><strong>Nome:</strong> ' . esc_html($produto->post_title) . '</p>';
        echo '<p><strong>ID:</strong> ' . $produto->ID . '</p>';
        
        $estoque_atual = get_post_meta($produto->ID, '_stock', true);
        $preco_atual = get_post_meta($produto->ID, '_price', true);
        
        echo '<p><strong>Estoque atual:</strong> ' . ($estoque_atual ?: '0') . '</p>';
        echo '<p><strong>Preço atual:</strong> R$ ' . number_format(floatval($preco_atual), 2, ',', '.') . '</p>';
        
    } else {
        echo '<p style="color: red;"><strong>Produto não encontrado!</strong></p>';
        echo '<p>SKU "' . esc_html($sku) . '" não existe no WooCommerce</p>';
    }
    echo '</div>';
    
    echo '</div>'; // fim do grid
    
    // Resumo
    echo '<div style="border-top: 1px solid #ddd; padding-top: 20px; margin-top: 20px; background: #f0f8ff; padding: 15px; border-radius: 5px;">';
    echo '<h4>Resumo</h4>';
    
    if (isset($estoque) && $estoque !== null && $produto) {
        echo '<p style="color: green; font-weight: bold;">Teste COMPLETO! Fluxo funcionando.</p>';
    } else {
        echo '<p style="color: orange; font-weight: bold;">Teste PARCIAL.</p>';
        if (!isset($estoque) || $estoque === null) echo '<p>• Problema na comunicação com Railway</p>';
        if (!$produto) echo '<p>• Produto não existe no WooCommerce</p>';
    }
    
    echo '</div>';
    echo '</div>';
}

// Página de Ficha Técnica (descrição, fotos, dimensões, categoria, marca — dados da Precode)
function woo_importador_pagina_ficha_tecnica() {
    echo '<div class="wrap">';
    echo '<h1>Ficha Técnica de Produtos</h1>';
    echo '<p>Busca descrição, dimensões, peso, EAN, categoria, marca e fotos na Precode (<code>produtoLoja/ProdutoSku/{sku}</code>) e grava nos campos nativos do WooCommerce. Roda separado da sincronização de estoque/preço porque esses dados mudam raramente.</p>';

    global $wpdb;
    $total_produtos = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value != ''");
    $offset_atual = intval(get_option('woo_importador_ficha_offset', 0));
    $processados = min($offset_atual, intval($total_produtos));
    $restantes = max(0, intval($total_produtos) - $processados);

    // Processar ações
    if (isset($_POST['sincronizar_lote_ficha']) && woo_importador_post_ok()) {
        $limite = intval($_POST['ficha_limite'] ?? 20);
        if (function_exists('woo_importador_sincronizar_ficha_tecnica_lote')) {
            $sucessos = woo_importador_sincronizar_ficha_tecnica_lote($limite);
            echo '<div class="notice notice-success"><p>Lote processado — ' . intval($sucessos) . ' produtos atualizados. <a href="">Recarregar página</a> para ver o progresso.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Função de sincronização não encontrada.</p></div>';
        }
    }

    if (isset($_POST['resetar_progresso_ficha']) && woo_importador_post_ok()) {
        update_option('woo_importador_ficha_offset', 0);
        echo '<div class="notice notice-success"><p>Progresso da ficha técnica resetado!</p></div>';
    }

    echo '<div class="card" style="max-width: 600px; padding: 20px; margin: 20px 0;">';
    echo '<h3>Status</h3>';
    echo '<table style="width: 100%;">';
    echo '<tr><td><strong>Total de produtos:</strong></td><td>' . number_format($total_produtos) . '</td></tr>';
    echo '<tr><td><strong>Processados:</strong></td><td>' . number_format($processados) . '</td></tr>';
    echo '<tr><td><strong>Restantes:</strong></td><td>' . number_format($restantes) . '</td></tr>';
    echo '</table>';
    echo '</div>';

    echo '<form method="post" style="margin: 20px 0;">'; woo_importador_nonce_field();
    echo '<input type="hidden" name="ficha_limite" value="20">';
    echo '<input type="submit" name="sincronizar_lote_ficha" class="button button-primary button-large" value="Sincronizar Próximo Lote (20 produtos)" style="margin-right: 10px;">';
    echo '<input type="submit" name="resetar_progresso_ficha" class="button button-secondary" value="Resetar Progresso" onclick="return confirm(\'Tem certeza que deseja resetar o progresso da ficha técnica?\');">';
    echo '</form>';

    // Teste individual
    if (isset($_POST['sincronizar_sku_teste']) && woo_importador_post_ok()) {
        $sku = sanitize_text_field($_POST['sku_ficha_teste'] ?? '');
        if (!empty($sku)) {
            woo_importador_testar_ficha_tecnica($sku);
        }
    }

    echo '<div class="card" style="max-width: 700px; padding: 20px; margin: 20px 0;">';
    echo '<h3>Testar um SKU específico</h3>';
    echo '<form method="post">'; woo_importador_nonce_field();
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row">SKU</th>';
    echo '<td>';
    echo '<input type="text" name="sku_ficha_teste" placeholder="Digite o SKU (ex: 25752)" required style="width: 200px;">';
    echo ' <input type="submit" name="sincronizar_sku_teste" class="button button-primary" value="Sincronizar Ficha Técnica">';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '</form>';
    echo '</div>';

    echo '</div>';
}

// Roda a sincronização de ficha técnica para um SKU específico e mostra o resultado
function woo_importador_testar_ficha_tecnica($sku) {
    global $wpdb;

    echo '<div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">';
    echo '<h3>Resultado — SKU: ' . esc_html($sku) . '</h3>';

    $produto = $wpdb->get_row($wpdb->prepare(
        "SELECT p.ID, p.post_title
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE pm.meta_key = '_sku' AND pm.meta_value = %s
         AND p.post_type = 'product' AND p.post_status = 'publish'",
        $sku
    ));

    if (!$produto) {
        echo '<p style="color: red;">Produto com SKU "' . esc_html($sku) . '" não encontrado no WooCommerce.</p>';
        echo '</div>';
        return;
    }

    echo '<p><strong>Produto:</strong> ' . esc_html($produto->post_title) . ' (ID: ' . $produto->ID . ')</p>';

    if (!function_exists('woo_importador_sincronizar_ficha_tecnica')) {
        echo '<p style="color: red;">Função de sincronização não encontrada.</p>';
        echo '</div>';
        return;
    }

    $start_time = microtime(true);
    $resultado = woo_importador_sincronizar_ficha_tecnica($produto->ID, $sku);
    $duration = round((microtime(true) - $start_time) * 1000);

    if (!$resultado) {
        echo '<p style="color: red;">Não foi possível sincronizar (SKU não encontrado na Precode ou erro de conexão). Tempo: ' . $duration . 'ms</p>';
        echo '</div>';
        return;
    }

    echo '<p style="color: green;"><strong>Sincronizado!</strong> (' . $duration . 'ms)</p>';

    echo '<table class="wp-list-table widefat fixed striped" style="max-width: 700px;">';
    echo '<tbody>';
    $campos = [
        'Peso (_weight)' => get_post_meta($produto->ID, '_weight', true),
        'Altura (_height)' => get_post_meta($produto->ID, '_height', true),
        'Largura (_width)' => get_post_meta($produto->ID, '_width', true),
        'Comprimento (_length)' => get_post_meta($produto->ID, '_length', true),
        'EAN (_global_unique_id)' => get_post_meta($produto->ID, '_global_unique_id', true),
        'NCM' => get_post_meta($produto->ID, '_precode_ncm', true),
        'Código agrupador' => get_post_meta($produto->ID, '_precode_codigo_agrupador', true),
        'Grade cor' => get_post_meta($produto->ID, '_precode_grade_x_nome', true),
        'Grade variação' => get_post_meta($produto->ID, '_precode_grade_y_nome', true),
        'Imagem destacada' => has_post_thumbnail($produto->ID) ? 'Definida' : '— nenhuma',
        'Galeria' => count(array_filter(explode(',', get_post_meta($produto->ID, '_product_image_gallery', true)))) . ' imagem(ns)',
        'Categoria' => implode(', ', wp_list_pluck(get_the_terms($produto->ID, 'product_cat') ?: [], 'name')),
    ];
    foreach ($campos as $rotulo => $valor) {
        echo '<tr><td><strong>' . esc_html($rotulo) . '</strong></td><td>' . esc_html((string) $valor) . '</td></tr>';
    }
    echo '</tbody>';
    echo '</table>';

    echo '</div>';
}

// Página de Fantasmas
function woo_importador_pagina_fantasmas() {
    if (!current_user_can('manage_options')) {
        return;
    }

    echo '<div class="wrap">';
    echo '<h1>Produtos Fantasmas (Sem SKU)</h1>';
    
    // Processar Exclusões Individuais ou em Lote
    if (isset($_POST['excluir_fantasma']) && !empty($_POST['fantasma_id']) && woo_importador_post_ok()) {
        $post_id = intval($_POST['fantasma_id']);
        wp_trash_post($post_id);
        echo '<div class="notice notice-success"><p>Produto ID ' . $post_id . ' enviado para a lixeira.</p></div>';
    }
    
    if (isset($_POST['excluir_todos_fantasmas']) && woo_importador_post_ok()) {
        if (!empty($_POST['fantasmas_ids'])) {
            $ids = array_map('intval', explode(',', $_POST['fantasmas_ids']));
            $count = 0;
            foreach ($ids as $id) {
                if (wp_trash_post($id)) {
                    $count++;
                }
            }
            echo '<div class="notice notice-success"><p>' . $count . ' produtos enviados para a lixeira.</p></div>';
        }
    }

    global $wpdb;
    $fantasmas = $wpdb->get_results("
        SELECT p.ID, p.post_title 
        FROM {$wpdb->posts} p 
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku' 
        WHERE p.post_type = 'product' 
        AND p.post_status NOT IN ('trash', 'auto-draft')
        AND (pm.meta_value IS NULL OR pm.meta_value = '')
    ");

    if (empty($fantasmas)) {
        echo '<div class="notice notice-success"><p>Excelente! Nenhum produto fantasma (sem SKU) encontrado na sua loja.</p></div>';
    } else {
        echo '<p>Os produtos abaixo estão ativos no seu banco de dados, mas não possuem um código SKU preenchido. Como o nosso sincronizador precisa do SKU, ele ignora esses produtos, deixando-os perdidos (fantasmas).</p>';
        
        // Botão excluir todos
        $ids_string = implode(',', wp_list_pluck($fantasmas, 'ID'));
        echo '<form method="post" style="margin-bottom: 20px;">'; woo_importador_nonce_field();
        echo '<input type="hidden" name="fantasmas_ids" value="' . esc_attr($ids_string) . '">';
        echo '<input type="submit" name="excluir_todos_fantasmas" class="button button-primary" value="Enviar TODOS (' . count($fantasmas) . ') para a Lixeira" onclick="return confirm(\'Atenção! Todos estes produtos serão enviados para a lixeira. Deseja continuar?\');" style="background: #d63638; border-color: #c72c2e;">';
        echo '</form>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Nome do Produto</th><th>Ação</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($fantasmas as $f) {
            $edit_url = get_edit_post_link($f->ID);
            echo '<tr>';
            echo '<td>' . $f->ID . '</td>';
            echo '<td><strong><a href="' . esc_url($edit_url) . '" target="_blank">' . esc_html($f->post_title ?: '(Sem Título)') . '</a></strong></td>';
            echo '<td>
                    <form method="post" style="display:inline;">
                        ' . woo_importador_nonce_html() . '
                        <input type="hidden" name="fantasma_id" value="' . $f->ID . '">
                        <input type="submit" name="excluir_fantasma" class="button button-small" value="Lixeira" onclick="return confirm(\'Enviar produto para lixeira?\');">
                    </form>
                  </td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }

    
    // NOVA SEÇÃO: SKUS DUPLICADOS
    echo '<hr style="margin-top: 40px; margin-bottom: 20px;">';
    echo '<h2>Produtos com SKU Duplicado</h2>';
    echo '<p>Os produtos abaixo estão ativos e possuem o <strong>mesmo SKU</strong>. Isso faz com que a sincronização atualize todos eles ao mesmo tempo para o mesmo saldo. Escolha o correto e exclua as cópias.</p>';

    // Consulta para encontrar SKUs duplicados (Via Tabela Nativa do WooCommerce)
    $duplicados = $wpdb->get_results("
        SELECT l.sku, COUNT(l.product_id) as qtd, GROUP_CONCAT(l.product_id) as ids
        FROM {$wpdb->prefix}wc_product_meta_lookup l
        INNER JOIN {$wpdb->posts} p ON p.ID = l.product_id
        WHERE l.sku IS NOT NULL AND l.sku != ''
        AND p.post_status != 'trash'
        GROUP BY l.sku
        HAVING COUNT(l.product_id) > 1
        ORDER BY qtd DESC
    ");

    if ($wpdb->last_error) {
        echo '<div class="notice notice-error"><p><strong>Erro de SQL (Duplicados):</strong> ' . esc_html($wpdb->last_error) . '</p></div>';
    }

    if (empty($duplicados)) {
        echo '<div class="notice notice-success"><p>Excelente! Não há produtos com SKU duplicado na sua loja.</p></div>';
    } else {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th style="width:15%">SKU Duplicado</th><th style="width:10%">Quantidade</th><th>Produtos Envolvidos (Clique no título para ver o cadastro)</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($duplicados as $dup) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($dup->sku) . '</strong></td>';
            echo '<td><span style="color:red; font-weight:bold;">' . $dup->qtd . ' repetições</span></td>';
            
            echo '<td>';
            $ids_array = explode(',', $dup->ids);
            foreach ($ids_array as $pid) {
                $p = get_post($pid);
                if ($p) {
                    $edit_url = get_edit_post_link($pid);
                    $tem_imagem = has_post_thumbnail($pid) ? 'Com Imagem' : '❌ Sem Imagem';
                    echo '<div style="margin-bottom:8px; padding:8px; background:#fff; border:1px solid #ccc;">';
                    echo '<strong><a href="' . esc_url($edit_url) . '" target="_blank">' . esc_html($p->post_title ?: '(Sem Título)') . '</a></strong> <span style="color:#666;">(ID: ' . $pid . ')</span> | ' . $tem_imagem;
                    
                    // Botão Lixeira
                    echo '<form method="post" style="display:inline; float:right;">'; woo_importador_nonce_field();
                    echo '<input type="hidden" name="fantasma_id" value="' . $pid . '">';
                    echo '<input type="submit" name="excluir_fantasma" class="button button-small" value="Lixeira" onclick="return confirm(\'Enviar produto ' . $pid . ' para lixeira?\');">';
                    echo '</form>';
                    
                    echo '</div>';
                }
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    echo '</div>';
}

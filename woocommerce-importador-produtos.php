<?php
/*
Plugin Name: WooCommerce Importador Estoque & Preços
Plugin URI: https://github.com/SuedesCarletti/woocommerce-product-importer
Description: Atualiza estoque via Railway/Sankhya e preços via Precode - Versão Unificada
Version: 2.1.0
Author: Suedes Carletti
License: Todos os direitos reservados / All rights reserved
Text Domain: woo-importador
*/

if (!defined('ABSPATH')) exit;

// PLUGIN PRINCIPAL

// Definir constantes do plugin
define('WOO_IMPORTADOR_VERSION', '2.1.0');
define('WOO_IMPORTADOR_PATH', plugin_dir_path(__FILE__));
define('WOO_IMPORTADOR_URL', plugin_dir_url(__FILE__));

// CORREÇÃO: Incluir arquivos com verificação de existência
function woo_importador_incluir_arquivos() {
    $arquivos = [
        'includes/functions.php',
        'includes/image-functions.php',
        'includes/ficha-tecnica-functions.php',
        'includes/admin-pages.php',
        'includes/fix-visibilidade.php'
    ];
    
    foreach ($arquivos as $arquivo) {
        $caminho = WOO_IMPORTADOR_PATH . $arquivo;
        
        if (file_exists($caminho)) {
            require_once $caminho;
        } else {
            error_log("[WooImportador] Arquivo não encontrado: {$arquivo}");
        }
    }
}

// Incluir arquivos na inicialização
add_action('plugins_loaded', 'woo_importador_incluir_arquivos');

// ESTRUTURA DE MENUS

add_action('admin_menu', function () {
    // Verificar se WooCommerce está ativo
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Menu principal
    add_menu_page(
        'Woo Importador',
        'Woo Importador',
        'manage_options',
        'woo-importador',
        'woo_importador_pagina_principal',
        'dashicons-update',
        56
    );
    
    // Submenu: Configurações
    add_submenu_page(
        'woo-importador',
        'Configurações',
        'Configurações',
        'manage_options',
        'woo-importador-config',
        'woo_importador_pagina_config'
    );
    
    // Submenu: Teste Individual  
    add_submenu_page(
        'woo-importador',
        'Teste Individual',
        'Teste Produto',
        'manage_options',
        'woo-importador-teste',
        'woo_importador_pagina_teste'
    );

    // Submenu: Limpeza de Fantasmas
    add_submenu_page(
        'woo-importador',
        'Limpeza Fantasmas',
        'Fantasmas',
        'manage_options',
        'woo-importador-fantasmas',
        'woo_importador_pagina_fantasmas'
    );

    // Submenu: Forçar Visibilidade
    add_submenu_page(
        'woo-importador',
        'Forçar Visibilidade',
        'Correção Visibilidade',
        'manage_options',
        'woo-importador-visibilidade',
        'woo_importador_forcar_visibilidade'
    );

    // Submenu: Ficha Técnica (descrição, fotos, dimensões, categoria, marca)
    add_submenu_page(
        'woo-importador',
        'Ficha Técnica',
        'Ficha Técnica',
        'manage_options',
        'woo-importador-ficha-tecnica',
        'woo_importador_pagina_ficha_tecnica'
    );
});

// HOOKS DE ATIVAÇÃO/DESATIVAÇÃO

register_activation_hook(__FILE__, 'woo_importador_ativar_plugin');
register_deactivation_hook(__FILE__, 'woo_importador_desativar_plugin');

function woo_importador_ativar_plugin() {
    // Verificar dependências
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('❌ Este plugin requer o WooCommerce. Por favor, instale e ative o WooCommerce primeiro.');
    }
    
    // Criar opções padrão
    add_option('woo_importador_usar_railway', '0');
    add_option('woo_importador_railway_url', '');
    add_option('woo_importador_railway_token', '');
    add_option('woo_importador_offset', 0);
    
    error_log("[WooImportador] Plugin ativado - Versão " . WOO_IMPORTADOR_VERSION);
}

function woo_importador_desativar_plugin() {
    // Limpar transients e cache
    delete_transient('woo_importador_stats_ultima_sync');
    
    error_log("[WooImportador] Plugin desativado");
}

// AVISOS ADMINISTRATIVOS

add_action('admin_notices', function() {
    // Aviso se WooCommerce não estiver ativo
    if (!class_exists('WooCommerce')) {
        echo '<div class="notice notice-error">';
        echo '<p><strong>Woo Importador:</strong> Este plugin requer o WooCommerce. Por favor, instale e ative o WooCommerce.</p>';
        echo '</div>';
        return;
    }
    
    // Aviso se Railway não estiver configurado
    $usar_railway = get_option('woo_importador_usar_railway', '0');
    $railway_url = get_option('woo_importador_railway_url', '');
    
    if ($usar_railway && empty($railway_url)) {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'woo-importador') !== false) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>Railway não configurado:</strong> Configure a URL e Token do Railway nas <a href="?page=woo-importador-config">Configurações</a>.</p>';
            echo '</div>';
        }
    }
});

// AJAX HANDLERS (PARA FUTURAS MELHORIAS)

add_action('wp_ajax_woo_importador_teste_produto', 'woo_importador_ajax_teste_produto');

function woo_importador_ajax_teste_produto() {
    // Verificar nonce e permissões
    if (!current_user_can('manage_options')) {
        wp_die('Sem permissão');
    }
    
    $sku = sanitize_text_field($_POST['sku'] ?? '');
    
    if (empty($sku)) {
        wp_send_json_error('SKU obrigatório');
    }
    
    // Executar teste
    ob_start();
    if (function_exists('woo_importador_testar_produto_individual')) {
        woo_importador_testar_produto_individual($sku);
    } else {
        echo '<p style="color: red;">Função de teste não encontrada</p>';
    }
    $resultado = ob_get_clean();
    
    wp_send_json_success(['html' => $resultado]);
}

// UTILITÁRIOS GERAIS

// Obter versão do plugin
function woo_importador_get_versao() {
    return WOO_IMPORTADOR_VERSION;
}

// Verificar se está na página do plugin
function woo_importador_is_admin_page() {
    $screen = get_current_screen();
    return $screen && strpos($screen->id, 'woo-importador') !== false;
}

// Obter estatísticas básicas
function woo_importador_get_stats() {
    global $wpdb;
    
    return [
        'total_produtos' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value != ''"),
        'offset_atual' => get_option('woo_importador_offset', 0),
        'railway_ativo' => get_option('woo_importador_usar_railway', '0'),
        'railway_configurado' => !empty(get_option('woo_importador_railway_url', ''))
    ];
}

// RECURSOS DE DEBUG (DESENVOLVIMENTO)

if (defined('WP_DEBUG') && WP_DEBUG) {
    // Adicionar informações de debug no admin
    add_action('admin_footer', function() {
        if (woo_importador_is_admin_page()) {
            $stats = woo_importador_get_stats();
            echo '<!-- Woo Importador Debug: ' . json_encode($stats) . ' -->';
        }
    });
}

// COMPATIBILIDADE E ATUALIZAÇÕES

// Hook para atualizações futuras
add_action('plugins_loaded', function() {
    $versao_atual = get_option('woo_importador_version', '0');
    
    if (version_compare($versao_atual, WOO_IMPORTADOR_VERSION, '<')) {
        // Executar atualizações se necessário
        update_option('woo_importador_version', WOO_IMPORTADOR_VERSION);
        error_log("[WooImportador] Plugin atualizado para versão " . WOO_IMPORTADOR_VERSION);
    }
});

// LIMPEZA NA DESINSTALAÇÃO

register_uninstall_hook(__FILE__, 'woo_importador_desinstalar');

function woo_importador_desinstalar() {
    // Remover opções (opcional - manter comentado para preservar configurações)
    /*
    delete_option('woo_importador_usar_railway');
    delete_option('woo_importador_railway_url');
    delete_option('woo_importador_railway_token');
    delete_option('woo_importador_offset');
    delete_option('woo_importador_version');
    */
    
    error_log("[WooImportador] Plugin desinstalado");
}

function woo_importador_get_codlocal($sku) {
    $railway_url = get_option('woo_importador_railway_url', '');
    $railway_token = get_option('woo_importador_railway_token', '');
    
    if (empty($railway_url) || empty($railway_token)) {
        return null;
    }
    
    $url = rtrim($railway_url, '/') . '/api/v1/estoque/' . intval($sku) . '?detalhado=true';
    
    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $railway_token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 10
    ));
    
    if (is_wp_error($response)) {
        return null;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!$data || $data['status'] !== 'sucesso' || empty($data['resultado']['locais'])) {
        return null;
    }
    
    // Retorna o CODLOCAL do local com maior estoque disponível
    $locais = $data['resultado']['locais'];
    $local_principal = null;
    $maior_estoque = 0;
    
    foreach ($locais as $local) {
        $estoque_disponivel = max(0, $local['ESTOQUE'] - $local['RESERVADO']);
        if ($estoque_disponivel > $maior_estoque) {
            $maior_estoque = $estoque_disponivel;
            $local_principal = $local['CODLOCAL'];
        }
    }
    
    return $local_principal;
}

/**
 * Obtém todos os locais de estoque de um produto
 * 
 * @param string|int $sku SKU do produto
 * @return array|null Array com dados dos locais ou null se erro
 */
function woo_importador_get_locais_estoque($sku) {
    $railway_url = get_option('woo_importador_railway_url', '');
    $railway_token = get_option('woo_importador_railway_token', '');
    
    if (empty($railway_url) || empty($railway_token)) {
        return null;
    }
    
    $url = rtrim($railway_url, '/') . '/api/v1/estoque/' . intval($sku) . '?detalhado=true';
    
    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $railway_token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 10
    ));
    
    if (is_wp_error($response)) {
        return null;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!$data || $data['status'] !== 'sucesso') {
        return null;
    }
    
    return $data['resultado'];
}

/**
 * Verifica se um produto existe no estoque Sankhya
 * 
 * @param string|int $sku SKU do produto
 * @return bool True se existe, False caso contrário
 */
function woo_importador_produto_existe($sku) {
    $codlocal = woo_importador_get_codlocal($sku);
    return !empty($codlocal);
}

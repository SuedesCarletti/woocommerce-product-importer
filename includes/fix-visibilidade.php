<?php
if (!defined('ABSPATH')) {
    exit;
}

function woo_importador_forcar_visibilidade() {
    echo '<div class="wrap"><h1>Forçar Correção de Visibilidade (Busca)</h1>';
    
    if (isset($_POST['forcar_visibilidade'])) {
        // Destravar flags do WooCommerce
        delete_option('woocommerce_attribute_lookup_regeneration_in_progress');
        delete_option('woocommerce_update_product_lookup_tables_is_running');
        delete_option('woocommerce_product_lookup_tables_update_in_progress');
        
        echo '<div class="notice notice-success"><p>Flags de bloqueio do WooCommerce foram destravadas.</p></div>';
        
        if (!empty($_POST['skus_corrigir'])) {
            $skus = explode(',', $_POST['skus_corrigir']);
            $contador = 0;
            foreach ($skus as $sku) {
                $sku = trim($sku);
                if (empty($sku)) continue;
                
                $product_id = wc_get_product_id_by_sku($sku);
                if ($product_id) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        // Forçar status e visibilidade
                        $product->set_status('publish');
                        $product->set_catalog_visibility('visible');
                        
                        // O GRANDE VILÃO: A taxonomia estava presa em outofstock!
                        // Vamos forçar o WooCommerce a notar a mudança:
                        $product->set_stock_status('outofstock');
                        $product->save(); // Salva como fora de estoque para limpar
                        
                        $product->set_stock_status('instock');
                        $product->save(); // Salva como em estoque para aplicar a taxonomia correta!
                        
                        // Forçar lookup table individualmente
                        if (function_exists('wc_update_product_lookup_tables')) {
                            wc_update_product_lookup_tables($product_id);
                        }
                        
                        echo '<p>Produto SKU ' . esc_html($sku) . ' (ID ' . $product_id . ') corrigido e re-indexado.</p>';
                        $contador++;
                    }
                } else {
                    echo '<p>SKU ' . esc_html($sku) . ' não encontrado.</p>';
                }
            }
            echo '<div class="notice notice-success"><p>Total de ' . $contador . ' produtos corrigidos e forçados na vitrine!</p></div>';
            echo '<p><strong>IMPORTANTE:</strong> Vá no menu SpeedyCache e clique em LIMPAR TODO O CACHE novamente.</p>';
        }
    }
    
    echo '<p>Se seus produtos têm estoque mas não aparecem na pesquisa ou categorias, o sistema de indexação do WooCommerce pode ter travado. Cole os SKUs abaixo (separados por vírgula) para destravar e forçar o re-cadastramento invisível deles.</p>';
    echo '<form method="post">';
    echo '<textarea name="skus_corrigir" rows="5" style="width:100%; max-width:600px;" placeholder="Exemplo: 24479, 23214, 23212"></textarea><br><br>';
    echo '<input type="submit" name="forcar_visibilidade" class="button button-primary" value="Forçar Correção de Visibilidade">';
    echo '</form>';
    echo '</div>';
}

<?php
require_once('../../../wp-load.php');

if (!isset($_GET['sku']) || empty($_GET['sku'])) {
    die("Passe o parametro ?sku=SEUSKU na URL");
}

$sku = sanitize_text_field($_GET['sku']);
echo "<h1>Diagnosticando o porquê do produto estar oculto</h1>";
echo "SKU procurado: <strong>{$sku}</strong><br><hr>";

$product_id = wc_get_product_id_by_sku($sku);
if (!$product_id) {
    die("SKU não encontrado no banco de dados.");
}

$product = wc_get_product($product_id);
echo "<strong>1. Dados Básicos do WooCommerce:</strong><br>";
echo "ID: {$product_id}<br>";
echo "Status do Post: " . get_post_status($product_id) . "<br>";
echo "Visibilidade do Catálogo: " . $product->get_catalog_visibility() . "<br>";
echo "Em Estoque? " . ($product->is_in_stock() ? "SIM" : "NÃO") . "<br>";
echo "Quantidade de Estoque: " . $product->get_stock_quantity() . "<br>";
echo "<hr>";

// Simular a Query que a Loja faz
$args = array(
    'post_type' => 'product',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'post__in' => array($product_id)
);

echo "<strong>2. Teste de WP_Query básico (Sem filtros do WC):</strong><br>";
$query1 = new WP_Query($args);
if ($query1->have_posts()) {
    echo "Encontrado no WP_Query básico.<br>";
} else {
    echo "BLOQUEADO no WP_Query básico.<br>";
}

echo "<hr><strong>3. Teste de WP_Query simulando a Loja (wc_get_products):</strong><br>";
$products = wc_get_products(array(
    'status' => 'publish',
    'include' => array($product_id),
    'visibility' => 'catalog'
));

if (!empty($products)) {
    echo "Encontrado via wc_get_products (Visibilidade normal).<br>";
} else {
    echo "BLOQUEADO pela camada do WooCommerce (A tabela de busca ou taxonomia está falhando).<br>";
}

echo "<hr><strong>4. Testando a tabela wp_wc_product_meta_lookup:</strong><br>";
global $wpdb;
$lookup = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wc_product_meta_lookup WHERE product_id = %d", $product_id));
if ($lookup) {
    echo "Registro na tabela de busca: Encontrado!<br>";
    echo "Estoque na tabela: {$lookup->stock_quantity}<br>";
    echo "Status de estoque na tabela: {$lookup->stock_status}<br>";
} else {
    echo "FALTA o registro na tabela wc_product_meta_lookup!<br>";
}

echo "<hr><strong>5. Testando os Termos de Visibilidade (Taxonomia):</strong><br>";
$terms = wp_get_post_terms($product_id, 'product_visibility', array('fields' => 'names'));
if (!empty($terms) && !is_wp_error($terms)) {
    echo "Termos de visibilidade aplicados a este produto: " . implode(', ', $terms) . "<br>";
} else {
    echo "Nenhum termo de visibilidade encontrado.<br>";
}

echo "<hr><strong>CONCLUSÃO:</strong> Se algum dos testes com '' falhou, tire um print desta tela inteira e me envie!";

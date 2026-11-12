<?php
// Bloquear acesso direto ao arquivo
if (!defined('ABSPATH')) {
    exit;
}

// Baixa uma imagem externa e anexa como attachment do produto. Retorna o ID do attachment ou false.
if (!function_exists('woo_importador_baixar_anexar_imagem')) {
function woo_importador_baixar_anexar_imagem($post_id, $image_url) {
    if (empty($image_url)) {
        return false;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    error_log("[WooImportador] Baixando imagem: " . $image_url);

    $temp_file = download_url($image_url);

    if (is_wp_error($temp_file)) {
        error_log("[WooImportador] ERRO no download da imagem: " . $image_url);
        return false;
    }

    // Obtém informações do arquivo original
    $file_info = pathinfo($image_url);
    $filename = sanitize_file_name($file_info['filename'] ?? 'produto') . '.jpg';

    // Define o caminho final do arquivo convertido, evitando sobrescrever um arquivo homônimo já existente
    $upload_dir = wp_upload_dir();
    $filename = wp_unique_filename($upload_dir['path'], $filename);
    $new_filepath = $upload_dir['path'] . '/' . $filename;

    // Move a imagem para o diretório correto
    rename($temp_file, $new_filepath);

    // Insere a imagem no WordPress
    $attachment = array(
        'post_mime_type' => 'image/jpeg',
        'post_title'     => $filename,
        'post_content'   => '',
        'post_status'    => 'inherit'
    );

    $attach_id = wp_insert_attachment($attachment, $new_filepath, $post_id);
    if (is_wp_error($attach_id) || !$attach_id) {
        error_log("[WooImportador] ERRO ao anexar imagem: " . $image_url);
        return false;
    }

    $attach_data = wp_generate_attachment_metadata($attach_id, $new_filepath);
    wp_update_attachment_metadata($attach_id, $attach_data);

    return $attach_id;
}
}

// Função para adicionar imagem ao produto no WooCommerce (mantida para compatibilidade)
if (!function_exists('woo_importador_adicionar_imagem')) {
function woo_importador_adicionar_imagem($post_id, $image_url, $gallery = false) {
    $attach_id = woo_importador_baixar_anexar_imagem($post_id, $image_url);

    if (!$attach_id) {
        return false;
    }

    if (!$gallery) {
        set_post_thumbnail($post_id, $attach_id);
        error_log("[WooImportador] Imagem principal adicionada ao produto ID: " . $post_id);
    } else {
        $gallery_ids = get_post_meta($post_id, '_product_image_gallery', true);
        $gallery_ids = empty($gallery_ids) ? [] : explode(',', $gallery_ids);
        $gallery_ids[] = $attach_id;
        update_post_meta($post_id, '_product_image_gallery', implode(',', $gallery_ids));
        error_log("[WooImportador] Imagem adicionada à galeria do produto ID: " . $post_id);
    }

    return true;
}
}

/**
 * Sincroniza foto principal + galeria de um produto de forma idempotente.
 * Mantém um mapa (url => attachment_id) em post meta para nunca baixar a mesma imagem duas vezes.
 *
 * @param int $post_id
 * @param string $foto_principal URL da foto principal
 * @param array $fotos_adicionais Array de strings de URL, ou de arrays no formato ['foto' => 'url']
 */
if (!function_exists('woo_importador_sincronizar_fotos_precode')) {
function woo_importador_sincronizar_fotos_precode($post_id, $foto_principal, $fotos_adicionais = []) {
    $mapa = get_post_meta($post_id, '_precode_fotos_importadas', true);
    $mapa = is_array($mapa) ? $mapa : [];

    $resolver_attachment = function ($url) use (&$mapa, $post_id) {
        $url = trim((string) $url);
        if (empty($url)) {
            return false;
        }
        if (isset($mapa[$url]) && get_post($mapa[$url])) {
            return intval($mapa[$url]);
        }
        $attach_id = woo_importador_baixar_anexar_imagem($post_id, $url);
        if ($attach_id) {
            $mapa[$url] = $attach_id;
        }
        return $attach_id;
    };

    if (!empty($foto_principal)) {
        $thumb_id = $resolver_attachment($foto_principal);
        if ($thumb_id) {
            set_post_thumbnail($post_id, $thumb_id);
        }
    }

    if (!empty($fotos_adicionais) && is_array($fotos_adicionais)) {
        $gallery_ids = [];
        foreach ($fotos_adicionais as $foto) {
            $url = is_array($foto) ? ($foto['foto'] ?? '') : $foto;
            $attach_id = $resolver_attachment($url);
            if ($attach_id) {
                $gallery_ids[] = $attach_id;
            }
        }
        if (!empty($gallery_ids)) {
            update_post_meta($post_id, '_product_image_gallery', implode(',', array_unique($gallery_ids)));
        }
    }

    update_post_meta($post_id, '_precode_fotos_importadas', $mapa);
}
}

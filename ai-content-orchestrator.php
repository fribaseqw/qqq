<?php
// includes/ai-content-orchestrator.php

require_once plugin_dir_path(__FILE__) . '../modules/seo-meta-builder.php';
require_once plugin_dir_path(__FILE__) . '../modules/content-writer.php';
require_once plugin_dir_path(__FILE__) . '../publisher/post-publisher.php';

function aicp_generate_content_and_post($topic, $language, $category_id, $auto_publish = true) {
    // Başlık ve içerik üretimi
    $title = aicp_generate_title($topic, $language);
    $content = aicp_generate_article($topic, $language);

    // Başlık ve içerik doğrulama
    if (empty($content) || strlen($content) < 60) {
        error_log('[AI Content] İçerik çok kısa veya boş, işlem iptal edildi.');
        return false;
    }

    if (empty($title) || strlen($title) < 5) {
        $title = ContentWriter::generate_title_from_content($content);
        if (!$title || strlen($title) < 5) {
            $title = ContentWriter::fallbackTitleFromContent($content, $topic);
        }
    }

    // Focus keyword oluştur
    $focus_keyword = strtolower(trim(explode(' ', $topic)[0]));

    // SEO meta oluştur
    $seo = SeoMetaBuilder::build($content, $focus_keyword);

    // Yayın durumu belirle
    $is_seo_ok = $seo['seo_score'] >= 60;
    $post_status = ($auto_publish && $is_seo_ok) ? 'publish' : 'draft';

    // Postu yayımla
    $post_id = wp_insert_post([
        'post_title'    => wp_strip_all_tags($title),
        'post_content'  => $content,
        'post_status'   => $post_status,
        'post_category' => [$category_id],
        'post_author'   => 1,
        'post_type'     => 'post'
    ]);

    if (is_wp_error($post_id)) {
        error_log('[AI Content] Hata: ' . $post_id->get_error_message());
        return false;
    }

    // SEO verilerini post meta olarak ekle
    SeoMetaBuilder::apply_to_post($post_id, $content, $title);

    // SEO puanı logla
    $log_entry = sprintf(
        "[%s] Post ID: %d | SEO Score: %d | Status: %s\n",
        date('Y-m-d H:i:s'), $post_id, $seo['seo_score'], $post_status
    );
    file_put_contents(plugin_dir_path(__FILE__) . '../cron/seo_score_log.txt', $log_entry, FILE_APPEND);

    return [
        'post_id'   => $post_id,
        'seo_score' => $seo['seo_score'],
        'status'    => $post_status
    ];
}

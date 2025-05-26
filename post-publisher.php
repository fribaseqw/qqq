<?php
require_once plugin_dir_path(__FILE__) . '../modules/content-writer.php';

function ai_publish_post($title, $content, $category_id = null) {
    error_log("📥 [AI Publisher] Yayınlama başlatıldı. Başlık: $title");

    // İçerik kontrolü
    if (empty($content) || strlen(trim($content)) < 60) {
        error_log("🛑 [AI Publisher] İçerik boş veya çok kısa. İçerik uzunluğu: " . strlen(trim($content)));
        return false;
    }

    // Başlık kontrolü
    $title = trim($title);
    if (!$title || strlen($title) < 5) {
        error_log("⚠️ [AI Publisher] Başlık eksik ya da kısa. İçeriğe göre başlık AI ile yeniden üretiliyor...");

        $generated_title = ContentWriter::generate_title_from_content($content);

        if ($generated_title && strlen($generated_title) >= 5) {
            $title = $generated_title;
            error_log("✅ [AI Publisher] AI ile başlık üretildi: $title");
        } else {
            $fallback_title = ContentWriter::fallbackTitleFromContent($content, 'Başlık bilinmiyor');
            $title = $fallback_title;
            error_log("⚠️ [AI Publisher] Başlık üretilemedi. Fallback başlık kullanıldı: $title");
        }
    }

    // Kullanıcı doğrulama
    $author_id = 1;
    $user = get_user_by('id', $author_id);
    if (!$user) {
        error_log("🛑 [AI Publisher] Kullanıcı bulunamadı: ID $author_id");
        return false;
    }
    if (!user_can($user, 'publish_posts')) {
        error_log("🛑 [AI Publisher] Kullanıcının yayınlama yetkisi yok: ID $author_id");
        return false;
    }

    // Focus keyword belirleme
    $first_word = explode(' ', wp_strip_all_tags($title))[0];
    $focus_keyword = strtolower(trim($first_word));

    // SEO skoru hesaplama
    $word_count = str_word_count(strip_tags($content));
    $keyword_count = substr_count(strtolower($content), $focus_keyword);
    $keyword_density = ($word_count > 0) ? ($keyword_count / $word_count) * 100 : 0;

    $seo_score = 0;
    if ($keyword_density >= 0.5 && $keyword_density <= 2.5) $seo_score += 30;
    if (stripos($title, $focus_keyword) !== false) $seo_score += 30;
    if (stripos(substr($content, 0, 200), $focus_keyword) !== false) $seo_score += 20;
    if (substr_count($content, '<a ') >= 1) $seo_score += 20;

    // Yazı verisi
    $post_data = array(
        'post_title'    => wp_strip_all_tags($title),
        'post_content'  => $content,
        'post_status'   => 'publish',
        'post_author'   => $author_id,
        'post_category' => $category_id ? array($category_id) : array(),
        'post_type'     => 'post',
    );

    error_log("🛠️ [AI Publisher] wp_insert_post çağrılıyor...");
    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        error_log('❌ [AI Publisher] wp_insert_post HATA: ' . $post_id->get_error_message());
        return false;
    }

    if (!$post_id || $post_id === 0) {
        error_log('❌ [AI Publisher] wp_insert_post başarısız, ID 0 döndü.');
        return false;
    }

    // Yoast SEO meta verileri
    update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus_keyword);
    update_post_meta($post_id, '_yoast_wpseo_metadesc', wp_trim_words(strip_tags($content), 25));
    update_post_meta($post_id, '_yoast_wpseo_title', $title);

    // SEO skoru loglama
    $log_path = plugin_dir_path(__FILE__) . '../cron/seo_score_log.txt';
    $log_entry = date('Y-m-d H:i:s') . " | Post ID: $post_id | SEO Score: $seo_score | Başlık: $title" . PHP_EOL;
    file_put_contents($log_path, $log_entry, FILE_APPEND);

    error_log("✅ [AI Publisher] Yayınlandı. Post ID: $post_id | SEO Score: $seo_score");
    return $post_id;
}

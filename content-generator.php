<?php
require_once plugin_dir_path(__FILE__) . '../includes/logger.php';
require_once plugin_dir_path(__FILE__) . '../modules/content-writer.php';
require_once plugin_dir_path(__FILE__) . '../includes/post-publisher.php'; // <-- EKLENDİ

class ContentWriterWrapper {
    /**
     * Anahtar kelimeye göre içerik üretir.
     *
     * @param string $keyword
     * @return array|false ['title' => ..., 'content' => ...]
     */
    public static function generate($keyword) {
        Logger::log("🧠 İçerik üretimi başlatıldı: $keyword");

        $result = generate_ai_content($keyword); // dizi döner

        if (!is_array($result) || !isset($result['content']) || strlen(trim($result['content'])) < 100) {
            Logger::log("⚠️ İçerik üretimi başarısız veya çok kısa: $keyword");
            return false;
        }

        if (!isset($result['title']) || strlen(trim($result['title'])) < 5) {
            $fallback = mb_substr(strip_tags($result['content']), 0, 60) . '...';
            Logger::log("⚠️ Başlık üretilemedi, fallback kullanılacak: $fallback");
            $result['title'] = $fallback;
        }

        return $result;
    }
}

/**
 * Anahtar kelimeleri basit şekilde genişletir.
 */
function getDeepSeekKeywords($keyword, $category = 'genel') {
    return [
        $keyword,
        "$keyword nedir",
        "$keyword nasıl çalışır",
        "$keyword avantajları",
        "2025'te $keyword trendleri"
    ];
}

/**
 * AI içerik üretim fonksiyonu
 */
function generate_ai_content($keyword, $category = 'genel') {
    Logger::log("📌 AI içerik üretimi başlatıldı: $keyword");

    $keywords = getDeepSeekKeywords($keyword, $category);
    if (!$keywords || !is_array($keywords)) {
        Logger::log("⚠️ Anahtar kelimeler üretilemedi: $keyword");
        return false;
    }

    $result = ContentWriter::generate($keyword); // ['title' => ..., 'content' => ...]
    if (!is_array($result) || !isset($result['content']) || strlen($result['content']) < 100) {
        Logger::log("⚠️ İçerik oluşturulamadı veya çok kısa: $keyword");
        return false;
    }

    Logger::log("✅ İçerik ve başlık başarıyla üretildi.");
    return $result;
}

/**
 * Yayınlama fonksiyonu
 */
function publish_ai_post($keyword) {
    $result = ContentWriterWrapper::generate($keyword);

    if (is_array($result) && !empty(trim($result['content']))) {
        $title = isset($result['title']) && strlen(trim($result['title'])) > 0
            ? trim($result['title'])
            : mb_substr(strip_tags($result['content']), 0, 60) . '...';

        Logger::log("📥 [AI Publisher] Yayınlama başlatıldı: Başlık: $title");

        // ✅ wp_insert_post yerine özel fonksiyon kullanılıyor
        $post_id = ai_publish_post($title, $result['content']);

        if ($post_id) {
            Logger::log("✅ [AI Publisher] Yayınlandı. Post ID: $post_id");
            return $post_id;
        } else {
            Logger::log("❌ [AI Publisher] Yayınlama başarısız.");
            return false;
        }
    }

    Logger::log("🛑 Yayınlama başarısız: içerik üretilemedi.");
    return false;
}

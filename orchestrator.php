<?php

file_put_contents('orchestrator-debug.txt', 'Yükleme başladı - ' . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);

// WordPress çekirdeğini yükle
require_once('/home/walloxgu/public_html/yerelkesif.com/wp-load.php');

// Gerekli dosyalar
require_once plugin_dir_path(__DIR__) . 'modules/publisher.php';
require_once plugin_dir_path(__DIR__) . 'includes/post-publisher.php';

// Diğer modülleri yükle
require_once __DIR__ . '/../modules/logger.php';
require_once __DIR__ . '/../modules/trends-keyword-fetcher.php';
require_once __DIR__ . '/../modules/content-writer.php';
require_once __DIR__ . '/../modules/taxonomy-generator.php';
require_once __DIR__ . '/../modules/image-generator.php';
require_once __DIR__ . '/../modules/seo-meta-builder.php';
require_once __DIR__ . '/../modules/internal-linker.php';
require_once __DIR__ . '/../modules/plagiarism-checker.php';
require_once __DIR__ . '/../modules/silo-builder.php';
require_once __DIR__ . '/../modules/webhook-notifier.php';
require_once __DIR__ . '/../modules/performance-tracker.php';

Logger::log("⏳ İçerik üretim süreci başladı...");

// Anahtar kelimeleri çek
$keywords = TrendsKeywordFetcher::fetch();
Logger::log("🔍 Alınan trend konular: " . implode(', ', $keywords));

// Rate limit kontrolü için sayaçlar
$rate_limit_per_minute = 20;
$requests_sent = 0;
$start_time = microtime(true);

foreach ($keywords as $keyword) {
    // Anahtar kelimeyi asla kırpma, olduğu gibi kullan!
    // En az iki kelime ve en az 15 karakter olmalı (ör: "yapay zeka", "makine öğrenimi" gibi)
    if (mb_strpos($keyword, ' ') === false || mb_strlen($keyword) < 15) {
        Logger::log("SKIP: Anahtar kelime tek kelime veya çok kısa: $keyword");
        continue;
    }

    Logger::log("▶️ İşleniyor: $keyword");
    file_put_contents('orchestrator-debug.txt', "İşleniyor: $keyword\n", FILE_APPEND);

    // ⏱️ Rate limit kontrolü
    $elapsed = microtime(true) - $start_time;
    if ($requests_sent >= $rate_limit_per_minute) {
        if ($elapsed < 60) {
            $sleep_time = ceil(60 - $elapsed);
            Logger::log("⏳ API sınırına ulaşıldı. {$sleep_time} saniye bekleniyor...");
            sleep($sleep_time);
        }
        $requests_sent = 0;
        $start_time = microtime(true);
    }

    $data = ContentWriter::generate($keyword);
    $requests_sent++;

    // Güvenlik: $data bir dizi değilse veya gerekli anahtarlar yoksa atla
    if (!is_array($data) || !isset($data['title'], $data['content'])) {
        Logger::log("⚠️ Geçersiz içerik verisi alındı: $keyword");
        continue;
    }

    $title = $data['title'];
    $content = $data['content'];

    if (!$content || stripos($content, 'başarısız') !== false) {
        Logger::log("⚠️ İçerik üretilemedi: $keyword");
        continue;
    }

    // AI ile başlık üretimi (EK: keyword parametresi ile birlikte çağırılmalı!)
    $title_prompt = "Trend konuda dikkat çekici bir blog başlığı yaz: \"$keyword\"";

    // Rate limit kontrolü başlık üretimi için de yapılır
    $elapsed = microtime(true) - $start_time;
    if ($requests_sent >= $rate_limit_per_minute) {
        if ($elapsed < 60) {
            $sleep_time = ceil(60 - $elapsed);
            Logger::log("⏳ API sınırına ulaşıldı. {$sleep_time} saniye bekleniyor...");
            sleep($sleep_time);
        }
        $requests_sent = 0;
        $start_time = microtime(true);
    }

    // DÜZELTME: Burada iki parametre ile çağır!
    $title = ContentWriter::generate_title_from_content($content, $keyword);
    $requests_sent++;

    // Diğer verileri oluştur
    $taxonomy = TaxonomyGenerator::assign($content);
    $image_url = ImageGenerator::create($content);
    $seo = SeoMetaBuilder::build($content, $keyword);

    // SEO skor kontrolü
    if (!is_array($seo) || !isset($seo['seo_score']) || $seo['seo_score'] < 60) {
        Logger::log("⚠️ Düşük SEO puanı veya eksik SEO verisi nedeniyle içerik atlandı: $keyword");
        continue;
    }

    InternalLinker::link($content);

    // Kopya kontrolü
    if (!PlagiarismChecker::check($content)) {
        Logger::log("❌ Kopya içerik bulundu: $keyword");
        continue;
    }

    // Silo yapısını kur
    SiloBuilder::organize($content);

    // ✅ Gerçek yayınlama burada gerçekleşiyor
    $post_id = Publisher::publish($title, $content, $taxonomy, $seo);

    if (!$post_id) {
        Logger::log("❌ Yayınlama başarısız: $title");
        continue;
    }

    Logger::log("✅ Yayınlandı: $title (ID: $post_id)");

    // Bildirim & performans
    WebhookNotifier::notify($post_id);
    PerformanceTracker::track($post_id);
}

Logger::log("🎉 İçerik üretim süreci tamamlandı.");
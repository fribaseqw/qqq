<?php
function fetch_trending_keywords() {
    $keywords = [];

    // Google Trends
    $googleTrendsHtml = @file_get_contents("https://trends.google.com/trends/trendingsearches/daily?geo=TR");
    if ($googleTrendsHtml && preg_match_all('/<div class="title">([^<]+)<\/div>/', $googleTrendsHtml, $matches)) {
        foreach ($matches[1] as $title) {
            $keywords[] = clean_keyword($title);
        }
    }

    // Reddit r/popular
    $context = stream_context_create([
        'http' => ['header' => "User-Agent: Mozilla/5.0\r\n"]
    ]);
    $redditJson = @file_get_contents("https://www.reddit.com/r/popular.json", false, $context);
    if ($redditJson) {
        $data = json_decode($redditJson, true);
        foreach ($data['data']['children'] ?? [] as $post) {
            $title = $post['data']['title'] ?? '';
            if ($title) {
                $keywords[] = clean_keyword($title);
            }
        }
    }

    // Onedio Gündem
    $onedioHtml = @file_get_contents("https://onedio.com/gundem");
    if ($onedioHtml && preg_match_all('/<h3[^>]*class="card-title"[^>]*>(.*?)<\/h3>/si', $onedioHtml, $matches)) {
        foreach ($matches[1] as $title) {
            $keywords[] = clean_keyword($title);
        }
    }

    // KizlarSoruyor (Benceler)
    $ksHtml = @file_get_contents("https://www.kizlarsoruyor.com/yeni?t=benceler", false, $context);
    if ($ksHtml && preg_match_all('/<a[^>]+class="title"[^>]*>(.*?)<\/a>/si', $ksHtml, $matches)) {
        foreach ($matches[1] as $title) {
            $keywords[] = clean_keyword($title);
        }
    }

    // Log: Toplanan tüm ham keywordler
    file_put_contents(__DIR__ . '/keywords-debug.log', "RAW:\n" . print_r($keywords, true), FILE_APPEND);

    // Temizlik ve filtreleme
    $keywords = array_unique(array_filter(array_map('trim', $keywords)));

    // Log: Trimlenmiş, uniq yapılmış keywordler
    file_put_contents(__DIR__ . '/keywords-debug.log', "TRIM+UNIQ:\n" . print_r($keywords, true), FILE_APPEND);

    // Yalnızca iki ve daha fazla kelimeden oluşan ve 15 harften uzun başlıklar kalsın
    $keywords = array_filter($keywords, function($kw) {
        $valid = is_valid_keyword($kw);
        file_put_contents(__DIR__ . '/keywords-debug.log', "FILTER: $kw => " . ($valid ? "OK" : "SKIP") . "\n", FILE_APPEND);
        return $valid;
    });

    // Log: is_valid_keyword filtresi sonrası
    file_put_contents(__DIR__ . '/keywords-debug.log', "FILTERED:\n" . print_r($keywords, true), FILE_APPEND);

    // Başarısız ve yakın zamanda denenmiş başlıkları çıkar
    $failed = load_json_keywords(__DIR__ . '/failed_keywords.json');
    $recent = load_json_keywords(__DIR__ . '/recent_keywords.json');

    $filtered = [];
    foreach ($keywords as $kw) {
        if (!in_array($kw, $failed) && !in_array($kw, $recent)) {
            $filtered[] = $kw;
        } else {
            file_put_contents(__DIR__ . '/keywords-debug.log', "SKIP (failed/recent): $kw\n", FILE_APPEND);
        }
    }

    // Log: Final keywordler
    file_put_contents(__DIR__ . '/keywords-debug.log', "FINAL:\n" . print_r($filtered, true), FILE_APPEND);

    // Yeni "recent" listesine ekle
    save_json_keywords(__DIR__ . '/recent_keywords.json', $filtered);

    return array_slice($filtered, 0, 15);
}

function clean_keyword($kw) {
    $kw = html_entity_decode(strip_tags($kw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $kw = preg_replace('/[\x00-\x1F\x7F]/u', '', $kw);
    $kw = preg_replace('/[^[:print:]\p{L}\p{N} ]+/u', '', $kw);
    return trim($kw);
}

function is_valid_keyword($kw) {
    $lower = mb_strtolower($kw);
    // En az 15 karakter, en az 2 kelime, en fazla 100 karakter, yasaklı kelimeler/sorular olmasın
    $valid = !(
        mb_strlen($kw) < 15 ||
        mb_strlen($kw) > 100 ||
        str_word_count($kw, 0, 'ğüşıöçĞÜŞİÖÇ') < 2 ||
        preg_match('/[a-z][A-Z]/', $kw) ||
        preg_match('/(keşfet|hemen sor|çok konuşulan|ilginizi çekebilir|göz at|popüler|şu an neler oluyor|benceler|sorular|sponsorlu|reklam|fenomen)/iu', $lower) ||
        preg_match('/(nasıl|neden|niçin|mi|mı|mu|mü)\b/iu', $lower) ||
        !preg_match('/[a-zA-Z0-9çğıöşüÇĞİÖŞÜ]/u', $kw)
    );
    file_put_contents(__DIR__ . '/keywords-debug.log', "is_valid_keyword('$kw') = " . ($valid ? "OK" : "NOPE") . "\n", FILE_APPEND);
    return $valid;
}

function load_json_keywords($path) {
    if (!file_exists($path)) return [];
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    if (strpos($path, 'failed_keywords.json') !== false && is_array($data)) {
        $filtered = [];
        foreach ($data as $kw => $date) {
            if (strtotime($date) >= strtotime('-7 days')) {
                $filtered[] = $kw;
            }
        }
        return $filtered;
    }
    return is_array($data) ? $data : [];
}

function save_json_keywords($path, $keywords) {
    if (strpos($path, 'failed_keywords.json') !== false) {
        $existing = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
        foreach ($keywords as $kw) {
            $existing[$kw] = date('Y-m-d');
        }
        file_put_contents($path, json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    } else {
        $keywords = array_values(array_unique(array_filter(array_map('trim', $keywords))));
        file_put_contents($path, json_encode($keywords, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}

function log_failed_keyword($keyword) {
    save_json_keywords(__DIR__ . '/failed_keywords.json', [$keyword]);
}
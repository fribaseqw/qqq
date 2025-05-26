<?php
class TrendsKeywordFetcher {
    public static function fetch() {
        $keywords = [];

        // ✅ Google Trends üzerinden Türkiye için popüler sorgular
        $googleTrends = self::getGoogleTrends();
        $keywords = array_merge($keywords, $googleTrends);

        // ✅ Reddit'ten trend başlıklar
        $redditTrends = self::getRedditTrends();
        $keywords = array_merge($keywords, $redditTrends);

        // ✅ Quora başlıkları
        $quoraTrends = self::getQuoraTrends();
        $keywords = array_merge($keywords, $quoraTrends);

        // ✅ KizlarSoruyor'dan popüler konular
        $ksTrends = self::getKizlarSoruyorTrends();
        $keywords = array_merge($keywords, $ksTrends);

        return array_slice(array_unique($keywords), 0, 10); // ilk 10 benzersiz konu
    }

    private static function getGoogleTrends() {
        $url = 'https://trends.google.com/trends/hottrends/visualize/internal/data';
        $response = wp_remote_get($url);

        if (is_wp_error($response)) return [];

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) return [];

        $trends = [];
        foreach ($data as $row) {
            if (isset($row['title'])) $trends[] = $row['title'];
        }
        return $trends;
    }

    private static function getRedditTrends() {
        $url = 'https://www.reddit.com/r/popular.json?limit=10';
        $response = wp_remote_get($url, [
            'headers' => ['User-Agent' => 'WP-AI-Content-Bot/1.0']
        ]);

        if (is_wp_error($response)) return [];

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $titles = [];
        if (isset($data['data']['children'])) {
            foreach ($data['data']['children'] as $post) {
                $titles[] = $post['data']['title'];
            }
        }
        return $titles;
    }

    private static function getQuoraTrends() {
        // Not: Gerçek bir API yok, örnekle sabit veri döndürüyoruz
        return [
            'Yapay zekanın geleceği',
            'Zengin nasıl olunur?',
            'En verimli öğrenme yöntemleri'
        ];
    }

    private static function getKizlarSoruyorTrends() {
        $url = 'https://www.kizlarsoruyor.com';
        $response = wp_remote_get($url);

        if (is_wp_error($response)) return [];

        $body = wp_remote_retrieve_body($response);
        preg_match_all('/<a[^>]*href="[^"]*"[^>]*>(.*?)<\/a>/i', $body, $matches);

        $titles = [];
        foreach ($matches[1] as $text) {
            $clean = strip_tags($text);
            if (strlen($clean) > 20 && strlen($clean) < 100) $titles[] = trim($clean);
        }
        return array_slice($titles, 0, 5);
    }
}

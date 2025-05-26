<?php

class SeoMetaBuilder {
    /**
     * İçerikten Yoast SEO uyumlu başlık, açıklama ve skor üretir.
     */
    public static function build($content, $focusKeyword = '') {
        $meta_title = self::generate_title($content);
        $meta_desc = self::generate_description($content);
        $seo_score = self::calculate_score($content, $focusKeyword);

        return [
            'meta_title' => $meta_title,
            'meta_desc'  => $meta_desc,
            'seo_score'  => $seo_score,
        ];
    }

    /**
     * Yoast için uygun meta başlık (max 60 karakter).
     */
    private static function generate_title($content) {
        return mb_substr(trim(strip_tags($content)), 0, 60);
    }

    /**
     * Yoast için uygun meta açıklama (max 155 karakter).
     */
    private static function generate_description($content) {
        return mb_substr(trim(strip_tags($content)), 0, 155);
    }

    /**
     * Temel SEO skor hesaplama algoritması.
     */
    public static function calculate_score($content, $keyword) {
        $score = 0;

        // Anahtar kelime yoğunluğu
        $keyword = mb_strtolower(trim($keyword));
        $content_text = mb_strtolower(strip_tags($content));
        $keyword_count = substr_count($content_text, $keyword);
        $word_count = str_word_count($content_text);
        $density = $word_count > 0 ? ($keyword_count / $word_count) * 100 : 0;

        if ($density >= 0.5 && $density <= 2.5) $score += 30;

        // Başlıkta anahtar kelime
        $title = self::generate_title($content);
        if (stripos($title, $keyword) !== false) $score += 20;

        // Açıklamada anahtar kelime
        $desc = self::generate_description($content);
        if (stripos($desc, $keyword) !== false) $score += 20;

        // Paragraf uzunluğu
        $paragraphs = preg_split('/\n+/', strip_tags($content));
        $long_paragraphs = 0;
        foreach ($paragraphs as $p) {
            if (str_word_count($p) > 40) $long_paragraphs++;
        }
        if ($long_paragraphs >= 3) $score += 15;

        // Okunabilirlik (noktalama sayısı)
        if (preg_match_all('/[.!?]/', $content) >= 5) $score += 15;

        return min(100, $score);
    }

    /**
     * WordPress gönderisine SEO meta verileri uygular.
     * Artık dışarıdan focusKeyword parametresi alır ve ilk kelimeyi almaz!
     */
    public static function apply_to_post($post_id, $content, $title = '', $focusKeyword = '') {
        if (!$post_id || empty($content)) return false;

        // $focusKeyword parametresi boşsa eski yöntemi uygula, doluysa doğrudan kullan
        if (empty($focusKeyword)) {
            // Yine de tam başlığı kullan!
            $focusKeyword = trim(wp_strip_all_tags($title));
        }

        $seo_data = self::build($content, $focusKeyword);

        update_post_meta($post_id, '_yoast_wpseo_focuskw', $focusKeyword);
        update_post_meta($post_id, '_yoast_wpseo_focuskw_text_input', $focusKeyword);
        update_post_meta($post_id, '_yoast_wpseo_focuskeywords', $focusKeyword);
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $seo_data['meta_desc']);
        update_post_meta($post_id, '_yoast_wpseo_title', $seo_data['meta_title']);

        return $seo_data;
    }
}
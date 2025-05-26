<?php

if (!class_exists('ContentWriter')) {
    class ContentWriter {

        public static function generate($keyword) {
            $content = self::retryContentGeneration($keyword);

            if (!$content || mb_strlen($content) < 300) {
                error_log("❌ İçerik üretimi başarısız: $keyword");
                return false;
            }

            $content = self::fix_passive_voice($content);
            $content = self::fix_long_sentences($content);
            $content = self::ensure_headings_exist($content, $keyword);
            $content = self::inject_transition_words($content);
            $content = self::add_internal_and_external_links($content);
            $content = self::insert_keyword_to_intro($content, $keyword);
            $content = self::limit_keyword_density($content, $keyword);
            $content = self::replace_image_tags_with_generated_images($content);

            $title = self::generate_title_from_content($content, $keyword);
            if (!$title || mb_strlen(trim($title)) < 5) {
                error_log("❌ Başlık üretilemedi, içerik iptal edildi.");
                return false;
            }

            $meta = self::generate_meta_description($content, $keyword);

            return [
                'title'   => trim($title),
                'content' => $content,
                'meta'    => $meta
            ];
        }

        private static function retryContentGeneration($keyword) {
            for ($i = 0; $i < 3; $i++) {
                $content = self::generateAIResponse(
                    "deepseek/deepseek-chat-v3-0324:free",
                    [
                        ["role" => "system", "content" =>
                            "Sen deneyimli bir içerik editörüsün. Amacın, SEO uyumlu, uzun (en az 1500 kelime), özgün, insansı ve bilgilendirici blog yazıları üretmek. " .
                            "Yazı H2 ve H3 başlıklarla yapılandırılmalı, geçiş kelimeleri kullanılmalı, cümleler kısa tutulmalı (maks. 15 kelime), doğal ve samimi bir dille yazılmalı. " .
                            "Anahtar kelime ve eş anlamlıları ilk paragrafta tam ve doğal şekilde yer almalı. " .
                            "Giriş, gelişme, sonuç yapısına sahip olmalı. Sonuç kısmı kalıp olmasın. Geçiş kelimeleri metnin %12’si kadar olmalı. Edilgen çatılı fiil oranı %10’u geçmesin. " .
                            "Odak anahtar kelimeyi (tam olarak: '$keyword') yazı içinde en fazla 7 kez kullan, varyasyonları 4’ü aşmasın. " .
                            "Meta açıklamasında anahtar kelimeyi en fazla 2 kez geçir. Alt başlıkların %50’den fazlası anahtar kelime içermesin! " .
                            "Anahtar kelimeyi başlıklarda çoğaltma! Görsellere [img: açıklama] etiketi ekle."
                        ],
                        ["role" => "user", "content" =>
                            "Lütfen '" . $keyword . "' hakkında SEO uyumlu, kaliteli, kullanıcıyı bilgilendiren bir blog yazısı oluştur."
                        ]
                    ],
                    2048
                );

                if ($content && mb_strlen($content) >= 300) return $content;
                error_log("🔁 İçerik üretim denemesi başarısız. Deneme: " . ($i + 1));
                sleep(3);
            }
            return false;
        }

        public static function generate_title_from_content($content, $keyword) {
            if (!$content || mb_strlen($content) < 60) return null;
            $summary = mb_substr(strip_tags($content), 0, 300);

            $prompt = <<<EOT
Aşağıdaki içeriğe uygun, ilgi çekici, SEO uyumlu ve kısa bir başlık üret. Başlık maksimum 12 kelime olmalı, anahtar kelime olan "$keyword" tam olarak geçmeli ve dikkat çekici olmalı. 
Sadece başlığı döndür, başka açıklama yapma.

İçerik Özeti:
$summary

Başlık:
EOT;

            for ($i = 0; $i < 3; $i++) {
                $response = self::generateAIResponse(
                    "deepseek/deepseek-chat-v3-0324:free",
                    [
                        ["role" => "system", "content" =>
                            "Sen profesyonel bir başlık editörüsün. SEO kurallarına uygun, anahtar kelime içeren, çarpıcı başlıklar üret. Yalnızca başlık döndür."
                        ],
                        ["role" => "user", "content" => $prompt]
                    ],
                    60
                );

                if ($response && mb_strlen(trim($response)) > 5) {
                    return trim(strip_tags($response));
                }
                error_log("🔁 Başlık üretim denemesi başarısız. Deneme: " . ($i + 1));
                sleep(2);
            }

            return "Bilgilendirici Blog Yazısı";
        }

        public static function generate_meta_description($content, $keyword) {
            $meta = '';
            $summary = mb_substr(strip_tags($content), 0, 300);

            $prompt = <<<EOT
Aşağıdaki içeriğe uygun, SEO uyumlu, 150 karakter civarı bir meta açıklaması oluştur. 
Anahtar kelime olan "$keyword" tam olarak ve en fazla 2 kez geçsin. 
Sadece meta açıklamayı döndür.

İçerik Özeti:
$summary

Meta:
EOT;

            for ($i = 0; $i < 3; $i++) {
                $response = self::generateAIResponse(
                    "deepseek/deepseek-chat-v3-0324:free",
                    [
                        ["role" => "system", "content" =>
                            "Sen profesyonel bir SEO editörüsün. SEO uyumlu, özgün meta açıklamalar üret. Sadece meta açıklama döndür."
                        ],
                        ["role" => "user", "content" => $prompt]
                    ],
                    50
                );

                if ($response && mb_strlen(trim($response)) > 20) {
                    // Anahtar kelime tam eşleşmesini kontrol et ve 2'den fazlaysa azalt
                    $meta = trim(strip_tags($response));
                    $pattern = '/\b' . preg_quote($keyword, '/') . '\b/ui';
                    preg_match_all($pattern, $meta, $matches);
                    if (count($matches[0]) > 2) {
                        $meta = self::limit_keyword_count_in_text($meta, $keyword, 2);
                    }
                    return $meta;
                }
                error_log("🔁 Meta açıklama üretim denemesi başarısız. Deneme: " . ($i + 1));
                sleep(2);
            }
            return '';
        }

        private static function fix_long_sentences($content) {
            $sentences = preg_split('/(?<=[.!?])\s+/', $content);
            foreach ($sentences as &$sentence) {
                if (str_word_count($sentence, 0, 'ğüşıöçĞÜŞİÖÇ') > 20) {
                    $sentence = preg_replace('/(.{1,100}?[.!?])(?=\s|$)/', "$1\n", $sentence);
                }
            }
            return implode(' ', $sentences);
        }

        private static function fix_passive_voice($content) {
            $passiveWords = [
                'tarafından', 'edildi', 'yapıldı', 'oldu', 'olunur', 'görülür', 'bulunur', 'geçirilir', 'sunulur', 'sağlanır', 'verilir', 'alınır', 'bulunmuştur', 'yazılmıştır'
            ];
            $sentences = preg_split('/(?<=[.!?])\s+/', $content);
            $count = 0;
            foreach ($sentences as &$sentence) {
                foreach ($passiveWords as $pw) {
                    if (stripos($sentence, $pw) !== false) $count++;
                }
            }
            if ($count > intval(count($sentences) * 0.1)) {
                foreach ($sentences as &$sentence) {
                    foreach ($passiveWords as $pw) {
                        $sentence = preg_replace('/\b' . preg_quote($pw, '/') . '\b/i', '', $sentence);
                    }
                }
                $content = implode(' ', $sentences);
            }
            return $content;
        }

        // Başlıklarda anahtar kelime tekrarını azaltır
        private static function ensure_headings_exist($content, $keyword) {
            preg_match_all('/<h2>(.*?)<\/h2>/iu', $content, $h2s);
            $headerCount = count($h2s[1]);
            $keywordHeaderCount = 0;
            foreach ($h2s[1] as $heading) {
                if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/ui', $heading)) {
                    $keywordHeaderCount++;
                }
            }
            if ($headerCount > 0 && ($keywordHeaderCount / $headerCount) > 0.5) {
                // %50'den fazlası anahtar kelime içeriyorsa sadeleştir
                foreach ($h2s[1] as $i => $heading) {
                    if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/ui', $heading) && $keywordHeaderCount > intval($headerCount/2)) {
                        $newHeading = preg_replace('/\b' . preg_quote($keyword, '/') . '\b/ui', '', $heading);
                        $content = str_replace($heading, trim($newHeading), $content);
                        $keywordHeaderCount--;
                    }
                }
            }
            return $content;
        }

        private static function inject_transition_words($content) {
            $transitions = [
                'Ayrıca', 'Ancak', 'İlave olarak', 'Özellikle', 'Buna karşın', 'Ne var ki', 'Bu bağlamda',
                'Her şeyden önce', 'İlk olarak', 'Başlangıç olarak', 'Genel olarak', 'Bu yazıda',
                'Kısaca bahsedecek olursak', 'Bununla birlikte', 'Bu nedenle', 'Öte yandan', 'Sonuç olarak'
            ];
            $sentences = preg_split('/(?<=[.!?])\s+/', $content);
            foreach ($sentences as $i => &$sentence) {
                if ($i % 5 === 0 && mb_strlen($sentence) > 40) {
                    $sentence = $transitions[array_rand($transitions)] . ' ' . $sentence;
                }
            }
            return implode(' ', $sentences);
        }

        private static function add_internal_and_external_links($content) {
            $internal = '<a href="/hakkimizda">Hakkımızda</a>';
            $external = '<a href="https://tr.wikipedia.org" target="_blank" rel="nofollow noopener">Wikipedia</a>';
            return $content . "<p>Kaynaklar: $internal, $external</p>";
        }

        // İlk paragrafta anahtar kelime yoksa ekler (tam eşleşme)
        private static function insert_keyword_to_intro($content, $keyword) {
            // İlk paragrafı bul ve analiz et
            if (!preg_match('/<p>.*?\b' . preg_quote($keyword, '/') . '\b.*?<\/p>/iu', $content)) {
                $content = preg_replace('/<p>(.*?)<\/p>/iu', '<p>' . ucfirst($keyword) . ' hakkında bilgi: $1</p>', $content, 1);
            }
            return $content;
        }

        // Tam kelime eşleşmesiyle yoğunluğu sınırla
        private static function limit_keyword_density($content, $keyword) {
            $pattern = '/\b' . preg_quote($keyword, '/') . '\b/ui';
            preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
            $total = count($matches[0]);
            if ($total > 7) {
                $content = self::limit_keyword_count_in_text($content, $keyword, 7);
            }
            return $content;
        }

        // Bir metindeki anahtar kelime tekrarını tam eşleşme ile sınırlar
        private static function limit_keyword_count_in_text($text, $keyword, $max) {
            $pattern = '/\b' . preg_quote($keyword, '/') . '\b/ui';
            preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
            $toRemove = count($matches[0]) - $max;
            if ($toRemove > 0) {
                $offsets = array_slice($matches[0], $max);
                // Ters sıradan sil ki offset bozulmasın
                foreach (array_reverse($offsets) as $match) {
                    $text = substr_replace($text, '', $match[1], mb_strlen($match[0]));
                }
            }
            return $text;
        }

        private static function replace_image_tags_with_generated_images($content) {
            preg_match_all('/\[img: (.*?)\]/', $content, $matches);
            if (empty($matches[0])) return $content;

            foreach ($matches[1] as $index => $prompt) {
                $image_data_uri = self::generateImageFromPrompt($prompt);
                if ($image_data_uri) {
                    $img_tag = '<img src="' . $image_data_uri . '" alt="' . htmlspecialchars($prompt, ENT_QUOTES) . '" loading="lazy" style="max-width:100%; height:auto; max-height:400px; display:block; margin:10px 0;">';
                    $content = str_replace($matches[0][$index], $img_tag, $content);
                }
            }
            return $content;
        }

        private static function generateImageFromPrompt($prompt) {
            $api_key = '9f62c69574e0741a2a5a481c910e97f47b890fb23c19a43889806baa784debdb';

            $postData = [
                "model" => "stabilityai/sdxl-turbo:free",
                "prompt" => $prompt,
                "n" => 1
            ];

            $response = wp_remote_post('https://ir-api.myqa.cc/v1/openai/images/generations', [
                'method'  => 'POST',
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'X-Title'       => 'AI Image Generator Plugin',
                    'HTTP-Referer'  => 'https://yerelkesif.com'
                ],
                'body'    => json_encode($postData),
                'timeout' => 60
            ]);

            if (is_wp_error($response)) return false;
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return !empty($body['data'][0]['b64_json']) ? 'data:image/png;base64,' . $body['data'][0]['b64_json'] : false;
        }

        private static function generateAIResponse($model, $messages, $max_tokens = 500) {
            $api_key = defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : 'sk-or-xxx...';

            $postData = [
                "model" => $model,
                "messages" => $messages,
                "temperature" => 0.8,
                "max_tokens" => $max_tokens
            ];

            $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
                'method'  => 'POST',
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => 'https://yerelkesif.com',
                    'X-Title'       => 'AI Content Writer Plugin'
                ],
                'body'    => json_encode($postData),
                'timeout' => 60
            ]);

            if (is_wp_error($response)) return false;
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return trim($body['choices'][0]['message']['content'] ?? '');
        }
    }
}
<?php
function spin($text) {
    return preg_replace_callback('/\{(.+?)\}/', function ($matches) {
        $options = explode('|', $matches[1]);
        return $options[array_rand($options)];
    }, $text);
}

function get_humanizer_data($topic = 'default') {
    $data = [
        'teknoloji' => [
            'deyimler' => [
                'çığır açmak', 'devrim yaratmak', 'geleceğe yön vermek'
            ],
            'yorumlar' => [
                "Bu yazı {gerçekten|cidden|hakikaten} teknoloji meraklıları için {altın değerinde|çok kıymetli|benzersiz}.",
                "{Yapay zekâ|Teknoloji} ile ilgili böyle içerikler {daha sık paylaşılmalı|yaygınlaştırılmalı}.",
                "Bilgiler hem güncel hem de {anlaşılır|kapsayıcı|faydalı} bir şekilde aktarılmış."
            ]
        ],
        'sağlık' => [
            'deyimler' => [
                'sağlıklı yaşam bir sanattır', 'bir gram önlem bir kilo tedaviye bedeldir'
            ],
            'yorumlar' => [
                "Yazıdan sonra {kendime daha çok dikkat etmem gerektiğini fark ettim|sağlığımı ciddiye almaya başladım}.",
                "Böyle {farkındalık sağlayan|bilgilendirici|yol gösterici} içerikler çok önemli.",
                "Bilgi, kısa ama öz şekilde verilmiş; {gerçekten faydalı|çok işime yaradı}."
            ]
        ],
        'default' => [
            'deyimler' => [
                'bir taşla iki kuş vurmak', 'saman altından su yürütmek'
            ],
            'yorumlar' => [
                "{Bu yazı|İçerik} insanı olayın içine çekiyor.",
                "Okurken {zamanın nasıl geçtiğini anlamadım|adeta sürüklendim}.",
                "Bu konuyu {yıllardır|uzun süredir} merak ediyordum, {açıklayıcı olmuş|şahane anlatılmış}."
            ]
        ]
    ];

    return $data[$topic] ?? $data['default'];
}

function insert_random_paragraph($text, $insertion) {
    $paragraphs = preg_split('/\n\s*\n/', $text);
    if (count($paragraphs) < 2) {
        // Çok kısa metinlerde sona ekle
        return $text . "\n\n" . $insertion;
    }
    $pos = rand(1, count($paragraphs) - 2);
    array_splice($paragraphs, $pos, 0, $insertion);
    return implode("\n\n", $paragraphs);
}

function apply_humanizer_mask($text, $topic = 'default') {
    $humanizer = get_humanizer_data($topic);
    shuffle($humanizer['deyimler']);
    shuffle($humanizer['yorumlar']);

    $deyim = ucfirst($humanizer['deyimler'][0]) . '.';
    $yorum_spintax = $humanizer['yorumlar'][0];
    $yorum = spin($yorum_spintax);

    // Deyimi sona, yorumu paragrafa yerleştir
    $text = insert_random_paragraph($text, $yorum);
    $text .= "\n\n" . $deyim;

    return $text;
}
?>

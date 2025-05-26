<?php
// İnsansı varyasyon üretici - deyim, benzetme, konuşma dili ve zenginleştirme
function humanize_text($text) {
    $replacements = [
        'çok önemli' => 'hayati derecede kritik',
        'bilgi' => 'veri ve tecrübe yığını',
        'yapılmalıdır' => 'bir an önce hayata geçirilmelidir',
        'gerekir' => 'öncelikli hale gelir',
        'örnek' => 'canlı bir örnek olarak',
        'hızlı' => 'jet hızında',
        'kolay' => 'zahmetsizce',
        'karmaşık' => 'kafaları allak bullak eden',
        'iyi' => 'dört dörtlük',
        'kötü' => 'tam bir fiyasko',
        'başarılı' => 'alkışa değer',
        'dikkat edilmelidir' => 'göz ardı edilmemelidir',
        'önemlidir' => 'kritik rol oynar',
        'faydalı' => 'işe yarar nitelikte',
        'anlaşılır' => 'elinizin altındaki gibi açık',
        'özgün' => 'eşine az rastlanır türden',
        'yeni' => 'henüz taze taze çıkmış',
        'eski' => 'kervan yolda düzülür misali bir yapı'
    ];
    foreach ($replacements as $original => $variant) {
        $text = str_ireplace($original, $variant, $text);
    }
    return $text;
}
?>
<?php
// convert-fonts.php
require_once('tcpdf/tcpdf.php');

function convertFont($fontFile, $fontName) {
    $fontpath = __DIR__ . '/fonts/woff/' . $fontFile;
    if (!file_exists($fontpath)) {
        echo "Error: Font file not found: " . $fontFile . "\n";
        return false;
    }

    try {
        $fontname = TCPDF_FONTS::addTTFfont(
            $fontpath,
            'TrueTypeUnicode',
            '',
            96,
            __DIR__ . '/tcpdf/fonts/'
        );
        echo "Successfully converted: " . $fontFile . " to " . $fontname . "\n";
        return $fontname;
    } catch (Exception $e) {
        echo "Error converting " . $fontFile . ": " . $e->getMessage() . "\n";
        return false;
    }
}

// تبدیل فونت‌ها
$fonts = [
    'IRANSansX-Regular.woff' => 'iransans',
    'IRANSansX-Heavy.woff' => 'iransansb',
    'IRANSansX-Light.woff' => 'iransansl',
    'IRANSansX-Medium.woff' => 'iransansm'
];

foreach ($fonts as $file => $name) {
    convertFont($file, $name);
}
?>
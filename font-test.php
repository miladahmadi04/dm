<?php
require_once('tcpdf/tcpdf.php');

class MyPDF extends TCPDF {
    public function testFont() {
        foreach($this->fontlist as $font) {
            echo $font . "\n";
        }
    }
}

$pdf = new MyPDF();
$pdf->testFont();
?>
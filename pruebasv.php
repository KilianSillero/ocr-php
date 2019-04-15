<?php
require_once __DIR__ . '/vendor/autoload.php';
use thiagoalessio\TesseractOCR\TesseractOCR;
error_reporting(E_ALL);
ini_set('display_errors', 1);
//$path = '/usr/local/Cellar/tesseract/4.0.0_1/bin/tesseract';

$resultado = (new TesseractOCR("images/result_ticket4.jpg"))
        //->executable($path)
        ->lang("spa")
        ->run();
echo $resultado;
?>
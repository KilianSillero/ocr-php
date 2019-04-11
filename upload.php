<?php
require_once __DIR__ . '/vendor/autoload.php';
use thiagoalessio\TesseractOCR\TesseractOCR;
//Si hay imagen
if(isset($_FILES['image'])){

    // *** regex  ***
        //Total
        //$regExTotal = (?i)(?m)(?<=Total|Total\s|Total:\s|Total:|Total\s:\s)\d{1,4}(?:[.\s]\d{3})*(?:[.,]\d{2})(?!\%|\d|\.|\scm|cm|pol|\spol)(?=\s|€|\s€)
        
        // Fecha en formato: M/D/YYYY or MM/DD/YYYY
        // $regExFecha = ^(0?[1-9]|1[0-2])[\/](0?[1-9]|[12]\d|3[01])[\/](19|20)\d{2}$
        
        //Fecha formato: DD mes
        // $regExFechaMes= \d{1,2}\s+(Enero?|Febrero?|Marzo?|Abril?|Mayo|Junio?|Julio?|Agosto?|Septiembre?|Octubre?|Noviembre|Diciembre?)/gi
        

    $path = '/usr/local/Cellar/tesseract/4.0.0_1/bin/tesseract';

    $file_name = $_FILES['image']['name'];
    $file_tmp =$_FILES['image']['tmp_name'];
    move_uploaded_file($file_tmp,"images/".$file_name); //la guardamos en images
    //la mostramos
    echo "<h3>Image Upload Success</h3>";
    echo '<img src="images/'.$file_name.'" style="width:100%">';

    //tratamos la imagen con imagemagick (tiene que estar instalado)
    exec("/usr/local/bin/convert images/$file_name \( -clone 0 -blur 0x10 \) +swap -compose divide -composite images/result_$file_name");

    //hacemos el ocr
    $resultado = (new TesseractOCR("images/result_$file_name"))
        //He tenido que ponerle la ruta donde esta el ejecutable por que aun cogiendo el path en la consola desde php no va
        ->executable($path)
        ->lang("spa")
        ->hocr() //devuelve lo datos en hocr
        ->psm(1)  //Menos estructurado pero reconoce mejor los numeros
        ->config("tessedit_write_images", true)
        ->run();


    //version Legacy (con esta si que van los patrones, pero reconoce peor y mas lento)
        // $resultado = (new TesseractOCR("images/$file_name"))
        // ->executable($path)
        // ->tessdataDir('tessdata')
        // ->lang("spa")
        // ->oem(0)
        // ->userPatterns('patrones/patterns.txt')
        // ->config('load_system_dawg', false)
        // ->config('load_freq_dawg', false)
        // ->psm(1)
        // ->run();
        
    //mostramos el resultado
    echo "<br><h3>OCR after reading</h3><br><pre>$resultado</pre>";

    
}
?>
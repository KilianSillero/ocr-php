<?php
require_once __DIR__ . '/vendor/autoload.php';
use thiagoalessio\TesseractOCR\TesseractOCR;
//Si hay imagen
if(isset($_FILES['image'])){

    $path = '/usr/local/Cellar/tesseract/4.0.0_1/bin/tesseract';

    $file_name = $_FILES['image']['name'];
    $file_tmp =$_FILES['image']['tmp_name'];
    move_uploaded_file($file_tmp,"images/".$file_name); //la guardamos en images
    //la mostramos
    echo "<h3>Image Upload Success</h3>";
    echo '<img src="images/'.$file_name.'" style="width:100%">';

    //hacemos el ocr
    $resultado = (new TesseractOCR("images/$file_name"))
        //He tenido que ponerle la ruta donde esta el ejecutable por que aun cogiendo el path en la consola desde php no va
        ->executable($path)
        ->lang("spa")
        ->run();

    //mostramos el resultado
    echo "<br><h3>OCR after reading</h3><br><pre>$resultado</pre>";

    
}
?>
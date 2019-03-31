<?php
require_once __DIR__ . '/vendor/autoload.php';
include('simple_html_dom.php');
use thiagoalessio\TesseractOCR\TesseractOCR;

//Si hay imagen
if(isset($_FILES['image'])){

    $path = '/usr/local/Cellar/tesseract/4.0.0_1/bin/tesseract';

    $file_name = $_FILES['image']['name'];
    $file_tmp =$_FILES['image']['tmp_name'];
    move_uploaded_file($file_tmp,"images/".$file_name); //la guardamos en images
    list($widthImg, $heightImg) = getimagesize("images/$file_name"); //recoger tamaño de imagen
    
    //hacemos el ocr
    $resultado = (new TesseractOCR("images/$file_name"))
        //He tenido que ponerle la ruta donde esta el ejecutable por que aun cogiendo el path en la consola desde php no va
        ->executable($path)
        ->lang("spa")
        ->hocr() //devuelve lo datos en hocr
        ->psm(12)  //Menos estructurado pero reconoce mejor los numeros
        ->run();

    //tratar los datos para hacer el json
    $html = str_get_html($resultado);
    $arrayWords = array();
    foreach($html->find('span[class="ocrx_word"]') as $word) { //Recoger todas las palabras
        $aux = array();
        $coords = explode(" ", $word->title); //conseguir las cordenadas
        $aux["word"] = $word->plaintext; //palabra
        $aux["x"] = $coords[1]; //cordenada x de la esquina superior izquierda
        $aux["y"] = $coords[2]; //cordenada y de la esquina superior izquierda
        $aux["w"] = "".($coords[3] - $coords[1]); //ancho de la palabra
        $aux["h"] = "".($coords[4] - $coords[2]); //alto de la palabra
        //lo mismo pero en porcentajes
        $aux["xp"] = getPercentOfNumber($aux["x"],$widthImg);
        $aux["yp"] = getPercentOfNumber($aux["y"],$heightImg);
        $aux["wp"] = getPercentOfNumber($aux["w"],$widthImg);
        $aux["hp"] = getPercentOfNumber($aux["h"],$heightImg);
        $arrayWords[] = $aux;
    }
    echo json_encode($arrayWords);

    
        

    
}
function getPercentOfNumber($number, $percent){
    if($number != 0)
        return round(($number / $percent) * 100 , 2);
    else
        return 0;
}
?>
<?php
require_once __DIR__ . '/vendor/autoload.php';
include('simple_html_dom.php');

use thiagoalessio\TesseractOCR\TesseractOCR;

//Si hay imagen
if(isset($_FILES['image'])){

    $path = '/usr/local/Cellar/tesseract/4.0.0_1/bin/tesseract';

    //Recogemos la imagen y la guardamos
    $currentDir = getcwd();
    $uploadDirectory = "/images/";

    $errors = []; // Store all foreseen and unforseen errors here

    $file_name = $_FILES['image']['name'];
    $file_size = $_FILES['image']['size'];
    $file_tmp  = $_FILES['image']['tmp_name'];

    $uploadPath = $currentDir . $uploadDirectory . basename($file_name); 
    $didUpload = false;

    if ($file_size > 8000000) {
        $errors[] = "This file is more than 8MB. Sorry, it has to be less than or equal to 8MB";
    }

    if (empty($errors)) {
        $didUpload = move_uploaded_file($file_tmp, $uploadPath);
        if (!$didUpload) {
            echo "An error occurred somewhere. Try again or contact the admin";
        }
    } else {
        foreach ($errors as $error) {
            echo $error . "These are the errors" . "\n";
        }
    }
    

    //si se ha subido correctamente, hacemos el ocr
    if ($didUpload) {
        //tratamos la imagen con imagemagick (tiene que estar instalado)
        exec("/usr/local/bin/convert images/$file_name \( -clone 0 -blur 0x10 \) +swap -compose divide -composite -deskew 30% images/result_$file_name");

        list($widthImg, $heightImg) = getimagesize("images/result_$file_name"); //recoger tamaño de imagen
        
        //hacemos el ocr
        $resultado = (new TesseractOCR("images/result_$file_name"))
            //He tenido que ponerle la ruta donde esta el ejecutable por que aun cogiendo el path en la consola desde php no va
            ->executable($path)
            ->lang("spa")
            ->hocr() //devuelve lo datos en hocr
            //->psm(12)  //Menos estructurado pero reconoce mejor los numeros
            ->config("tessedit_write_images", true) //saca tambien la imagen procesada (la que va a ser usada para el ocr) para ver como se ve
            ->run();

        //tratar los datos para despues hacer el json
        $html = str_get_html($resultado);
        $arrayJson = array(); //array general del json
        $arrayImportantWords = array(); //array con los campos importantes

        foreach($html->find('span[class="ocrx_word"]') as $word) { //Recoger todas las palabras
            $aux = array(); 
            $coords = explode(" ", $word->title); //conseguir las cordenadas
            $aux["word"] = str_replace('&quot;', '', $word->plaintext); //palabra
            $aux["x"] = getPercentOfNumber($coords[1],$widthImg); //cordenada x de la esquina superior izquierda
            $aux["y"] = getPercentOfNumber($coords[2],$heightImg); //cordenada y de la esquina superior izquierda
            $aux["w"] = getPercentOfNumber(($coords[3] - $coords[1]),$widthImg); //ancho de la palabra
            $aux["h"] = getPercentOfNumber(($coords[4] - $coords[2]),$heightImg); //alto de la palabra
            //regex
            validateRegex($arrayImportantWords, $aux);
        }

        //hacer el json con los datos 
        $arrayJson["total"] = getTotal($arrayImportantWords);
        $arrayJson["cif"] = isset($arrayImportantWords["cif"]["word"]) ? $arrayImportantWords["cif"]["word"] : null;

        //devolver el json
        header('Content-type:application/json;charset=utf-8');
        echo json_encode($arrayJson, JSON_UNESCAPED_UNICODE);


        //borrar los archivos creados
        // unlink("images/$file_name");
        // unlink("images/result_$file_name");
    }
}


/**  FUNCIONES  */
function getPercentOfNumber($number, $percent){
    if($number != 0)
        return round(($number / $percent) * 100 , 2);
    else
        return 0;
}

//funcion para filtrar los datos y guardar los importantes
function validateRegex(&$arrayImportantWords, $arrayWord){
$regExTotal = "/total/i";
$regExCifNif = "/([a-z]|[A-Z])-?[0-9]{8}|[0-9]{8}-?([a-z]|[A-Z])/";
$regExPrecio = "/\d{1,4}(?:[.,\s]\d{3})*(?:[.,]\d{2})(?!\%|\d|\.|\scm|cm|pol|\spol)/";
//$regExIvaEsp = "/\d{1,2}([.,]\d{2})?%|\d{1,2}([.,]\d{2})?\s%|21,00|10,00|4,00|^21|^10|^4/";

    //cif
    if(preg_match($regExCifNif, $arrayWord["word"], $matches)){        
        $arrayWord["word"] = $matches[0];
        $arrayImportantWords["cif"] = $arrayWord;
        return;
    }
    //total
    if(preg_match($regExTotal, $arrayWord["word"], $matches)){        
        $arrayWord["word"] = $matches[0];
        $arrayImportantWords["total"] = $arrayWord;
        return;
    }   
    // //iva
    // if(preg_match($regExIvaEsp, $arrayWord["word"], $matches)){        
    //     $arrayWord["word"] = $matches[0];
    //     $arrayImportantWords["iva"] = $arrayWord;
    //     return;
    // }   
    //precios
    if(preg_match($regExPrecio, $arrayWord["word"], $matches)){        
        $arrayWord["word"] = $matches[0];
        $arrayImportantWords["prices"][] = $arrayWord;
        return;
    }   

}


//funciones para recoger valores
function getTotal($arrayImportantWords){
    $arrayTotals = array();
    if(array_key_exists("prices",$arrayImportantWords)){
        if(array_key_exists("total",$arrayImportantWords)){
            $arrayTotals["nearest"] = getNearest($arrayImportantWords["total"], $arrayImportantWords["prices"])["word"];
        }
        $arrayTotals["bigger"] = getBigger($arrayImportantWords["prices"], "h")["word"];
        $arrayTotals["max"] = getMaxNumber($arrayImportantWords["prices"])["word"];
        $arrayTotals["lowest"] = getBigger($arrayImportantWords["prices"],"y")["word"];

    }
    //devolver el total mas probable (el que mas veces salga)
    $probably = null;
    $repetidos = array_count_values($arrayTotals);
    if($repetidos){
        $key = array_keys($repetidos, max($repetidos));
        $probably = $key[0];
    }
    
    return $probably;
}

function getBigger($array, $value){ //con value 'h' devuelve el más alto, con value 'y' devuelve el que mas abajo esté
    $column = array_column($array, $value);
    $key = array_keys($column,max($column));
    return $array[$key[0]];
}

function getNearest($target, $array, $axis="y"){ //devuelve el mas cercano a su eje (Ej: target 'total', eje 'y')
    $yt = $target[$axis];
    $nearest = [];
    $min = 100;
    foreach ($array as $key=>$value) {
        $aux = abs($yt - $value[$axis]);
        if($aux < $min){
            $nearest = $array[$key];
            $min = $aux;
        }
    }
    return $nearest;
}
function getMaxNumber($array){ //devuelve el que sea mas grande de numero
    $column = array_column($array, 'word');
    $floats= array_map('floatval',$column); //parse a float
    $key = array_keys($floats,max($floats));
    return $array[$key[0]];
}
?>
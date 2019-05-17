<?php
require_once __DIR__ . '/vendor/autoload.php';
include('simple_html_dom.php');

use thiagoalessio\TesseractOCR\TesseractOCR;
//phpinfo();die;
//Si hay imagen
$timestamp = microtime(true);
$tipo = "factura";

if(isset($_FILES['image'])){

    $path = '/usr/local/Cellar/tesseract/4.0.0_1/bin/tesseract';

    //Recogemos la imagen y la guardamos
    $currentDir = getcwd();
    $uploadDirectory = "/images/";

    $errors = []; // Store all foreseen and unforseen errors here

    $file_name = $_FILES['image']['name'];
    $file_name = str_replace(' ', '_', $file_name); //quitar los espacios
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
    
    echo "Timestamp - Despues de subir la imagen: " . (microtime(true) - $timestamp)."<br>";
    //si se ha subido correctamente, hacemos el ocr
    if ($didUpload) {
        //tratamos la imagen con imagemagick (tiene que estar instalado)
        if(mime_content_type ( "images/".$file_name ) == "application/pdf"){
            exec("/usr/local/bin/gs -dSAFER -dNOPAUSE -dBATCH -sDEVICE=jpeg -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -r300 -dFirstPage=1 -dLastPage=1 -sOutputFile=images/result_$file_name images/$file_name");
        }else{
            exec("/usr/local/bin/convert images/$file_name -resize 1500x1500\> \( -clone 0 -blur 0x10 \) +swap -compose divide -composite images/result_$file_name");
        }
        //tratar la imagen con imagick en vez de con comandos
        // $image = new Imagick("images/$file_name");
        // $image2 = clone $image;
        // $image2->blurImage(0,10);
        // $image2->compositeImage($image, Imagick::COMPOSITE_DIVIDEDST, 0, 0);
        // $image2->writeImage("images/result_$file_name");

        list($widthImg, $heightImg) = getimagesize("images/result_$file_name"); //recoger tamaño de imagen
        
        echo "Timestamp - Despues de tratar la imagen: " . (microtime(true) - $timestamp)."<br>";
        //hacemos el ocr
        $resultado = (new TesseractOCR("images/result_$file_name"))
            //He tenido que ponerle la ruta donde esta el ejecutable por que aun cogiendo el path en la consola desde php no va
            ->executable($path)
            ->lang("spa")
            ->hocr() //devuelve lo datos en hocr
            ->psm(1)  //detecta las imagenes giradas pero tarda un poco más
            //->config("tessedit_write_images", true) //saca tambien la imagen procesada (la que va a ser usada para el ocr) para ver como se ve
            ->threadLimit(2)
            ->run();

        echo "Timestamp - Despues de hacer el OCR: " . (microtime(true) - $timestamp)."<br>";

        //tratar los datos para despues hacer el json
        $html = str_get_html($resultado);
        $arrayJson = array(); //array general del json
        $arrayWords = array(); //array con las todas palabras y posiciones
        $arrayLines = array(); //array con las todas palabras y posiciones
        $arrayImportantWords = array(); //array con los campos importantes

        foreach($html->find('span[class="ocrx_word"]') as $word) { //Recoger todas las palabras
            $word = transformToCustomWord($word, $widthImg, $heightImg);
            $arrayWords[] = $word;
            //regex
            validateRegexWords($arrayImportantWords, $word);
        }

        if($tipo == "factura"){
            $lineFac = "";
            foreach($html->find('span[class="ocr_line"]') as $line) { //recorro las lineas
                
                $lineTrans = transformToCustomWord($line, $widthImg, $heightImg);
                $arrayLines[] = $lineTrans;
                //regex
                validateRegexLines($arrayImportantWords, $lineTrans);
                if(array_key_exists("numfacturatext",$arrayImportantWords) && $lineFac == ""){ //si encuentro num de factura
                    $lineFac = $line;
                }
            }
            if ($lineFac){

                
                foreach($lineFac->find('span[class="ocrx_word"]') as $word) {  //busco la palabra factura para coger sus coord
                    
                    $word = transformToCustomWord($word, $widthImg, $heightImg);
                    
                    $regExFactura = "/factura/i";
                    if(preg_match($regExFactura, $word["word"], $matches)){        
                        $word["word"] = $matches[0];
                        $arrayImportantWords["facturatext"] = $word;
                    }
                }
            }
        }
        echo "Timestamp - Despues de convertir los datos a array con cordenadas: " . (microtime(true) - $timestamp)."<br>";


////** ESTO ES LO QUE SE DEVUELVE */
        //hacer el json con los datos 
        $arrayJson["total"] = str_replace(",",".",getProbablyTotal($arrayImportantWords));
        $arrayJson["cifs"] = getCifs($arrayImportantWords);
        $arrayJson["date"] = getProbablyDate($arrayImportantWords);
        $arrayJson["hour"] = getProbablyHour($arrayImportantWords);
        $arrayJson["fecha_formato"] = getDateFormated($arrayJson["date"], $arrayJson["hour"]);
        if($tipo == "factura"){
            $arrayJson["factura"] = getProbablyNumFactura($arrayImportantWords);
        }
        

        echo "Timestamp - Despues de tratar los datos: " . (microtime(true) - $timestamp)."<br>";
        //devolver el json
        header('Content-type:application/json;charset=utf-8');
        //depende de si se quieren tratar los slash desde el cliente, se quita el JSON_UNESCAPED_SLASHES
        echo json_encode($arrayJson, JSON_UNESCAPED_SLASHES);

        //borrar los archivos creados
        // unlink("images/$file_name");
        // unlink("images/result_$file_name");
    }
}


/**  FUNCIONES  */

function transformToCustomWord($word, $widthImg, $heightImg){
    $aux = array(); 
    $word->title = str_replace(";","", $word->title);
    $coords = explode(" ", $word->title); //conseguir las cordenadas
    $aux["word"] = str_replace('&quot;', '', $word->plaintext); //palabra
    $aux["x"] = getPercentOfNumber($coords[1],$widthImg); //cordenada x de la esquina superior izquierda
    $aux["y"] = getPercentOfNumber($coords[2],$heightImg); //cordenada y de la esquina superior izquierda
    $aux["w"] = getPercentOfNumber(($coords[3] - $coords[1]),$widthImg); //ancho de la palabra
    $aux["h"] = getPercentOfNumber(($coords[4] - $coords[2]),$heightImg); //alto de la palabra

    return $aux;
}
function getPercentOfNumber($number, $percent){
    if($number != 0)
        return round(($number / $percent) * 100 , 2);
    else
        return 0;
}

//funcion para filtrar los datos y guardar los importantes
function validateRegexWords(&$arrayImportantWords, $arrayWord){
    $regExTotal = "/total/i";
    $regExCifNif = "/([a-z]|[A-Z])-?[0-9]{8}(?![0-9])|(?<![0-9])[0-9]{8}-?([a-z]|[A-Z])/";
    $regExPrecio = "/\d{1,4}(?:[.,\s]\d{3})*(?:[.,]\d{2})(?!\%|\d|\.|\scm|cm|pol|\spol)/";
    //$regExIvaEsp = "/\d{1,2}([.,]\d{2})?%|\d{1,2}([.,]\d{2})?\s%|21,00|10,00|4,00|^21|^10|^4/";
    $regExFecha =  "/(0?[1-9]|1[0-2])[\/.-](0?[1-9]|[12]\d|3[01])[\/.-](19|20)?\d{2}|(0?[1-9]|[12]\d|3[01])[\/.-](0?[1-9]|1[0-2])[\/.-](19|20)?\d{2}/";
    $regExHora = "/([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?/";
    $regExWithNumber = "/\S*[0-9].*/";

    //Que tengan numeros
    if(preg_match($regExWithNumber, $arrayWord["word"], $matches)){        
        $arrayWord["word"] = $matches[0];
        $arrayImportantWords["numbers"][] = $arrayWord;
    }
    //cif
    if(preg_match($regExCifNif, $arrayWord["word"], $matches)){        
        $arrayWord["word"] = str_replace('-', '', $matches[0]); //quitar guion
        $arrayImportantWords["cifs"][] = $arrayWord;
        return;
    }
    //fecha
    if(preg_match($regExFecha, $arrayWord["word"], $matches)){   
        $replaces = array(".",",","/")   ;  
        $arrayWord["word"] = str_replace($replaces, "-", $matches[0]);
        $arrayImportantWords["date"][] = $arrayWord;
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
    //horas   
    if(preg_match($regExHora, $arrayWord["word"], $matches)){        
        $arrayWord["word"] = $matches[0];
        $arrayImportantWords["hours"][] = $arrayWord;
        return;
    }  
    

}
function validateRegexLines(&$arrayImportantWords, $arrayWord){
    $regExNumFactura = "/n(º|.)\s*(de)?\s*factura|n(u|ú)mero\s*(de)?\s*factura/i";
    //num factura - texto/target
    if(preg_match($regExNumFactura, $arrayWord["word"], $matches)){  
            
        $arrayWord["word"] = $matches[0];
        $arrayImportantWords["numfacturatext"] = $arrayWord;
        
        return;
    }  
}

//funciones para recoger valores
function getProbablyTotal($arrayImportantWords){
    $arrayTotals = array();
    if(array_key_exists("prices",$arrayImportantWords)){
        if(array_key_exists("total",$arrayImportantWords)){
            $arrayTotals["nearest"] = getNearestAxis($arrayImportantWords["total"], $arrayImportantWords["prices"]);
        }
        $arrayTotals["bigger"] = getBigger($arrayImportantWords["prices"], "h");
        $arrayTotals["max"] = getMaxNumber($arrayImportantWords["prices"]);
        $arrayTotals["lowest"] = getBigger($arrayImportantWords["prices"],"y");

    }
    return getMoreProbably($arrayTotals);
}
function getProbablyHour($arrayImportantWords){
    if(array_key_exists("date",$arrayImportantWords) && array_key_exists("hours",$arrayImportantWords)){
        return getNearestAxis($arrayImportantWords["date"][0],$arrayImportantWords["hours"])["word"];
    }
    return null;
}
function getProbablyDate($arrayImportantWords){
    if(array_key_exists("date",$arrayImportantWords)){
        return getMoreProbably($arrayImportantWords["date"]);
    }
    return null;
}
function getProbablyNumFactura($arrayImportantWords){
    
    if(array_key_exists("facturatext",$arrayImportantWords)){
        if(array_key_exists("numbers",$arrayImportantWords)){
            return getNearestXY($arrayImportantWords["facturatext"], $arrayImportantWords["numbers"])["word"];
        }
    }
    return null;
}
function getCifs($arrayImportantWords){

    if(isset($arrayImportantWords["cifs"])){
        $cifs = array_column($arrayImportantWords["cifs"], "word");
        $cifsValidados = [];

        //Quitar del array de cifs el que se pasa por parametro 
        if(isset($_POST['cif'])){
            $cifAQuitar = $_POST['cif'];
            $cifAQuitar = str_replace('-', '', $cifAQuitar); //quitar guion
            if (($key = array_search($cifAQuitar, $cifs)) !== false) {
                unset($cifs[$key]);
            }
        }
        include 'bd.php';
        //llamada al sv para comprobar cifs
        foreach ($cifs as $key => $cif) {
            $result = checkCif($cif);
            if($result != false){
                $cifsValidados["cif"] = array("valido" => true, "cif" => $result["CIF"], "id" => $result["id_cuenta"], "nombre" => $result["nombre"], "nombrefiscal" => $result["nombrefiscal"]);
            }else{
                $cifsValidados["cif"] = array("valido" => false, "cif" => $cif, "id" => null, "nombre" => null, "nombrefiscal" => null);
            }
        }
            
        
                
        return $cifsValidados;
    }
    return null;
}
function getDateFormated($date, $hour){
    if($date != null){
        if(strlen($date) == 8){ //si la fecha esta con los anyos en 2 digitos trata los dos ultimos como anyo
            $datef = DateTime::createFromFormat('!d-m-y', "$date");
            return date("Y-m-d\TH:i:s", strtotime($datef->format("Y-m-d")." ".$hour));
        }
        return date("Y-m-d\TH:i:s", strtotime("$date $hour"));
    }else{
        return null;
    }
}

function getMoreProbably($array){
    //devolver el total mas probable (el que mas veces salga)
    $probably = null;
    if($array){
        $column = array_column($array, "word");
        $repetidos = array_count_values($column);
        if($repetidos){
            $key = array_keys($repetidos, max($repetidos));
            $probably = $key[0];
        }
    }
    
    return $probably;
}


//funciones base
function getBigger($array, $value){ //con value 'h' devuelve el más alto, con value 'y' devuelve el que mas abajo esté
    $column = array_column($array, $value);
    $key = array_keys($column,max($column));
    return $array[$key[0]];
}

function getNearestAxis($target, $array, $axis="y"){ //devuelve el mas cercano a su eje (Ej: target 'total', eje 'y')
   
    $axisT = $target[$axis];
    $nearest = [];
    $min = 100;
    foreach ($array as $key=>$value) {
        $aux = abs($axisT - $value[$axis]);
        if($aux < $min){
            $nearest = $array[$key];
            $min = $aux;
        }
    }
    return $nearest;
}
function getNearestXY($target, $array, $maxX = 20, $maxY = 3){ //devuelve el mas cercano (Ej: target 'Num Factura')
    
    $yt = $target["y"];
    $xt = $target["x"];
    $nearest = [];
    $min = 200;
    foreach ($array as $key=>$value) {
        $aux = abs($yt - $value["y"]) + abs($xt - $value["x"]);
        //maximo de lejos de Y, X | que este a la derecha del objeto (-1 de margen) y que este mas cerca que el anterior
        if(abs($yt - $value["y"]) < $maxY && abs($xt - $value["x"]) < $maxX && ($xt - $value["x"]) < 0  && $aux < $min){
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
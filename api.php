<?php
require_once __DIR__ . '/vendor/autoload.php';
include('simple_html_dom.php');

use thiagoalessio\TesseractOCR\TesseractOCR;
// $post = file_get_contents('php://input');
// echo $post;
// die;

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
    

    //version corta sin comprobaciones
    // $file_name = $_FILES['image']['name'];
    // $file_tmp =$_FILES['image']['tmp_name'];
    // move_uploaded_file($file_tmp,"images/".$file_name); //la guardamos en images

    //si se ha subido correctamente, hacemos el ocr
    if ($didUpload) {
        //tratamos la imagen con imagemagick (tiene que estar instalado)
        exec("/usr/local/bin/convert images/$file_name \( -clone 0 -blur 0x10 \) +swap -compose divide -composite images/result_$file_name");

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

        //tratar los datos para hacer el json
        $html = str_get_html($resultado);
        $arrayJson = array(); //array general del json
        $arrayWords = array(); //array con las palabras y posiciones
        $arrayImportantWords = array(); //array con los campos importantes

        foreach($html->find('span[class="ocrx_word"]') as $word) { //Recoger todas las palabras
            $aux = array(); //array auxiliar para crear el objeto en json
            $coords = explode(" ", $word->title); //conseguir las cordenadas
            $aux["word"] = str_replace('&quot;', '', $word->plaintext); //palabra
            $aux["x"] = getPercentOfNumber($coords[1],$widthImg); //cordenada x de la esquina superior izquierda
            $aux["y"] = getPercentOfNumber($coords[2],$heightImg); //cordenada y de la esquina superior izquierda
            $aux["w"] = getPercentOfNumber(($coords[3] - $coords[1]),$widthImg); //ancho de la palabra
            $aux["h"] = getPercentOfNumber(($coords[4] - $coords[2]),$heightImg); //alto de la palabra

            $arrayWords[] = $aux;
            //regex
            validateRegex($arrayImportantWords, $aux);
        }


        //juntar las arrays
        $arrayJson["arrayWords"] = $arrayWords;
        $arrayJson["arrayImportantWords"] = $arrayImportantWords;

        //devolver el json
        
        // header('Access-Control-Allow-Origin: *');
        // header('Access-Control-Allow-Credentials: true');
        // header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
        // header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
        header('Content-type:application/json;charset=utf-8');
        echo json_encode($arrayJson, JSON_UNESCAPED_UNICODE);
    }
}



//funciones
function getPercentOfNumber($number, $percent){
    if($number != 0)
        return round(($number / $percent) * 100 , 2);
    else
        return 0;
}

//funcion un poco de prueba para probar las expresiones regulares, si va bien se hace mejor
function validateRegex(&$arrayImportantWords, $arrayWord){
$regExCifNif = "/([a-z]|[A-Z]|[0-9])-?[0-9]{7}-?([a-z]|[A-Z]|[0-9])/";
$regExTotal = "/\d{1,4}(?:[.,\s]\d{3})*(?:[.,]\d{2})(?!\%|\d|\.|\scm|cm|pol|\spol)/";
$regExIvaEsp = "/\d{1,2}([.,]\d{2})?%|\d{1,2}([.,]\d{2})?\s%|21,00|10,00|4,00|^21|^10|^4/";

    //cif
    if(preg_match($regExCifNif, $arrayWord["word"], $matches)){
            //$arrayWord["value"] = $matches[0];
            $arrayWord["word"] = $matches[0];
            $arrayImportantWords["cifs"][] = $arrayWord;
            return;
    }
    //total
    if(preg_match($regExTotal, $arrayWord["word"], $matches)){
        //$arrayWord["value"] = $matches[0];
        $arrayWord["word"] = $matches[0];
        $arrayImportantWords["totales"][] = $arrayWord;
        return;
    }   
    //iva
    if(preg_match($regExIvaEsp, $arrayWord["word"], $matches)){
        //$arrayWord["value"] = $matches[0];
        $arrayWord["word"] = $matches[0];
        $arrayImportantWords["ivas"][] = $arrayWord;
        return;
    }   

}
?>
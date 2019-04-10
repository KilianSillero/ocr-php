Uso de tesseract para php:
https://github.com/thiagoalessio/tesseract-ocr-for-php

Hace falta instalar tesseract:
https://github.com/tesseract-ocr/tesseract/wiki#macos

Y hace falta imagemagick para mejorar la imagen de entrada: 
https://www.imagemagick.org/

Y hacer el install de composer

## Servidor
Para el servidor solo hacen falta dos .php:

El de la api que se quiera usar (api1 devuelve todas las palabras al cliente, api2 devuelve solo los datos importantes)

Y el simple_dom_php, que se usa para tratar el hocr mejor.

<html>
    <body>

    <h3>PHP OCR Test</h3>
    <form action="api3.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="image" />
        <p>id_cliente</p>
        <input type="text" name="id_cliente" value="1">
        <p>id_usuario</p>
        <input type="text" name="id_usuario" value="1">
        <p>id_empresa</p>
        <input type="text" name="id_empresa" value="1">
        <p>cif a quitar (cif del usuario)</p>
        <input type="text" name="cif" value="12345678A">
        <input type="submit"/>
    </form>

    </body>
</html>
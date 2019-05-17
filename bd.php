<?php


function checkCif($cif){
    $servername = "localhost";
    $username = "kilian";
    $password = "1234";
    $dbname = "klikair_app";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    } 

    $cif = str_replace('-', '', $cif); //quitar guion

    $sql = "SELECT id_cuenta, nombre, nombrefiscal, CIF
            FROM cuenta 
            WHERE CIF = '" . $conn->escape_string($cif) . "' AND proveedor = '1' AND status = '0'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // output data of each row
        while($row = $result->fetch_assoc()) {
            return array(
                "id_cuenta" => $row["id_cuenta"],
                "nombre" => $row["nombre"],
                "nombrefiscal" => $row["nombrefiscal"],
                "CIF" => $row["CIF"]
            );
        }
    } else {
        return false;
    }
    $conn->close();
}
?>
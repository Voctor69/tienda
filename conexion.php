<?php
    $conexion = mysqli_connect("localhost","root","","testbdpa",3306);
    if($conexion){
        
    }else{
        echo "No se conectó con la base de datos";
    }
?>
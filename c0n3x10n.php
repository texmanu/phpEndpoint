<?php
// date_default_timezone_set('America/Tegucigalpa');

/*$hostname = "localhost\sqlexpress";
$dataConexion = array(
    'Database' => 'mobileApp',
    'UID' => 'AppPorsalud',
    'PWD' => 'App2019',
    "CharacterSet" => 'UTF-8'
);*/

$hostname = "172.16.0.192";
$dataConexion = array(
    'Database' => 'mobileApp',
    'UID' => 'APP_MEDICO24-7_PHP',
    'PWD' => 'EA511CF3',
    "CharacterSet" => 'UTF-8'
);

$conn = sqlsrv_connect($hostname, $dataConexion);

/*if( $conn ) {
    echo "Conexión establecida.";
}else{
    echo "Conexión no se pudo establecer.";
    die( print_r( sqlsrv_errors(), true));
}*/

// SETTINGS
header("Access-Control-Allow-Origin: *");
date_default_timezone_set('America/Tegucigalpa');
setlocale(LC_TIME, "es_ES.UTF-8");

$actualDate = date('Y-m-d h:i:s');

// WS GENERAL
$wsHostURI = 'http://testportal.porsalud.net/Outer/WSAppClientesPORSALUD/WSAppClientesPORSALUD.asmx/';
$soapKeyAccess = '3352d8085d113f7d30226cc5e6059c8a';
// ALERT | SMS WS
$wsAlertsHostURI = 'http://testportal.porsalud.net/Outer/WSSappsAlertas/WSSappsAlertas.asmx/';
$soapAlertsKeyAccess = '4b88ab9492addb20f597501eb5391ed4';

?>

<?php
include_once('c0n3x10n.php');

$userId = 736688364;
$vendorId = 12212;

$uriTest = $wsHostURI.'VendedorValidacion';
$params[KeyAccess] = $soapKeyAccess;
$params[CodigoVendedor] = $vendorId;
$query = http_build_query($params);
$contextData = array (
    'method' => 'POST',
    'header' => "Connection: close\r\n".
                "Content-Length: ".strlen($query)."\r\n",
    'content'=> $query);
$context = stream_context_create (array ( 'http' => $contextData ));
$response = json_decode(file_get_contents($uriTest, false, $context));

echo '<pre>';
print_r($response);
// if($response->CodigoRespuesta > 0){
//     $vendorName = $response->Data[0]->NombreVendedor;
//     $sql = sqlsrv_query($conn, "
//         INSERT INTO USERS_VENDOR
//             (userId, vendorId, vendorName, creationDate, status)
//         VALUES
//             ($userId,'$vendorId','$vendorName','$actualDate',1)
//     ");
// }
?>

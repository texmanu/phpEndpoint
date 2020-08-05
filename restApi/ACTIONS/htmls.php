<?php
$postdata = file_get_contents("php://input");
$request = json_decode($postdata);

if($action == 'getHtml'){
    $type = $request;
    $getCode = sqlsrv_query($conn, "SELECT code FROM HTML WHERE type = '$type'");
    $getCode = sqlsrv_fetch_array($getCode);
    $code = $getCode[code];

    echo json_encode($code);
}
?>
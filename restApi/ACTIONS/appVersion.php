<?php
$postdata = file_get_contents("php://input");
$request = json_decode($postdata);

if($action == 'getAppVersion'){
    $platform = $request;
    $getAppVersion = sqlsrv_query($conn, "SELECT versionCode FROM APP_VERSION WHERE status = 1 AND dispositive = '$platform'");
    $getAppVersion = sqlsrv_fetch_array($getAppVersion);
    $getAppVersion = $getAppVersion[versionCode];
    
    echo json_encode($getAppVersion);
}

?>
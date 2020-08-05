<?php
$postdata = file_get_contents("php://input");
$request = json_decode($postdata);

if($action == 'checkUserPlanPayment'){
    $userId = $request;
    // $userId = '793797447';
    $freePlanId = 566; // ID DEL PLAN GRATUITO

    $paymentState = sqlsrv_query($conn, "SELECT planId, EstadoPago FROM USERS_PLANTYPE WHERE userId = '$userId' AND status = '1'");
    $paymentState = sqlsrv_fetch_array($paymentState);

    if($paymentState[planId] == $freePlanId){
        $result[status] = 1;
    } else {
        if($paymentState[EstadoPago] == 0){
            $result[status] = 0;
            $result[message] = 'Realiza tu pago o ponte al día para poder utilizar este servicio.';
        } else {
            $result[status] = 1;
        }
    }

    echo json_encode($result);
}
else if($action == 'createPanicAlert'){
    $userId = $request->userId;
    $userLocation = $request->userLocation;

    $countServices = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM PANIC_ALERT WHERE userId = '$userId' AND alertStatus = 3");
    $countServices = sqlsrv_fetch_array($countServices);
    $countServices = $countServices[total];
    if($countServices == 2){
        $result[alertId] = 0;
        $result[message] = 'Ya haz utilizado todas tus alertas disponibles.<br>Puedes utilizar el servicio de Llamado de Ambulancia';
    } else {
        $userInfo = sqlsrv_query($conn, "SELECT name, documentNumber, cellPhone FROM USERS_PROFILE WHERE userId = '$userId'");
        $userInfo = sqlsrv_fetch_array($userInfo);
        $userName = $userInfo[name];
        $userDocumentNumber = $userInfo[documentNumber];
        $userPhone = $userInfo[cellPhone];

        // CREATE RAND ALERT ID
        $randAlertId = sqlsrv_query($conn, "
            DECLARE @Random INT
            SELECT @Random = ROUND(((999999999) * RAND() + 1), 0)
            SELECT @Random AS alertId WHERE @Random NOT IN(SELECT alertId FROM PANIC_ALERT)
        ");
        $randAlertId = sqlsrv_fetch_array($randAlertId);
        $alertId = $randAlertId[alertId];

        // GET USER LATLNG
        $userLocation = explode(',', $userLocation);
        $lat = $userLocation[0];
        $lng = $userLocation[1];

        $uriTest = $wsAlertsHostURI.'EnviarAlerta';
        $params[KeyAccess] = $soapAlertsKeyAccess;
        $params[IdWebApp] = 1;
        $params[NombreCliente] = $userName;
        $params[Identificacion] = $userDocumentNumber;
        $params[TipoAlerta] = 1;
        $params[Telefono] = $userPhone;
        $params[Movil] = $userPhone;
        $params[Latitud] = $lat;
        $params[Longitud] = $lng;
        $params[IdCategoria] = 3;
        $params[CodigoAlertaAppMedio247] = $alertId;
        $query = http_build_query($params);
        $contextData = array (
            'method' => 'POST',
            'header' => "Connection: close\r\n".
                        "Content-Length: ".strlen($query)."\r\n",
            'content'=> $query);
        $context = stream_context_create (array ('http' => $contextData));
        $response = json_decode(file_get_contents($uriTest, false, $context));
        // SI USUARIO EXISTE QUE SE GUARDE LA DATA DE LA ALERTA
        if($response->CodigoRespuesta == 1){
            $result[alertId] = $alertId;
            $result[message] = $response->Mensaje;
            $saveAlertId = sqlsrv_query($conn, "
                INSERT INTO PANIC_ALERT
                    (alertId, userId, alertStatus, creationDate, status)
                VALUES
                    ('$alertId','$userId',1,'$actualDate',1)
            ");
        } else {
            $result[alertId] = 0;
            $result[message] = $response->Mensaje.$userId.' '.$lat.' '.$lng;
        }
    }

    echo json_encode($result);
} 
else if($action == "checkPanicAlertStatus"){
    $userId = $request;
    $alertStatus = sqlsrv_query($conn, "SELECT alertStatus FROM PANIC_ALERT WHERE creationDate = (SELECT MAX(creationDate) FROM PANIC_ALERT WHERE userId = '$userId') AND status = 1");
    $alertStatus = sqlsrv_fetch_array($alertStatus);
    $alertStatus = $alertStatus[alertStatus];
    if($alertStatus == 1 || $alertStatus == 2){
        $result[status] = 1;
    } else if ($alertStatus == 3){
        $result[status] = 0;
    }

    echo json_encode($result);
}
else if($action == 'callMe'){
    $userId = $request->userId;
    $userLocation = $request->userLocation;
    $userLocation = explode(',', $userLocation);
    $lat = $userLocation[0];
    $lng = $userLocation[1];

    $userInfo = sqlsrv_query($conn, "SELECT name, documentNumber, cellPhone FROM USERS_PROFILE WHERE userId = '$userId'");
    $userInfo = sqlsrv_fetch_array($userInfo);
    $userName = $userInfo[name];
    $userDocumentNumber = $userInfo[documentNumber];
    $userPhone = $userInfo[cellPhone];

    $uriTest = $wsAlertsHostURI.'EnviarAlerta';
    $params[KeyAccess] = $soapAlertsKeyAccess;
    $params[IdWebApp] = 1;
    $params[NombreCliente] = $userName;
    $params[Identificacion] = $userDocumentNumber;
    $params[TipoAlerta] = 3;
    $params[Telefono] = $userPhone;
    $params[Movil] = $userPhone;
    $params[Latitud] = $lat;
    $params[Longitud] = $lng;
    $params[IdCategoria] = 1;
    $params[CodigoAlertaAppMedio247] = 0;
    $query = http_build_query($params);
    $contextData = array (
        'method' => 'POST',
        'header' => "Connection: close\r\n".
                    "Content-Length: ".strlen($query)."\r\n",
        'content'=> $query);
    $context = stream_context_create (array ( 'http' => $contextData ));
    $response = json_decode(file_get_contents($uriTest, false, $context));

    if($response->CodigoRespuesta == 1){
        $result = 'Pronto nos pondremos en contacto contigo.';
    } else {
        $result = 'Ha sucedido un error, intentalo de nuevo.';
    }

    echo json_encode($result);
}else if($action == 'PanicBotonAlertCreateAEM'){
    $userId = $request->userId;
    $userLocation = $request->userLocation;

    $countServices = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM PANIC_ALERT WHERE userId = '$userId' AND alertStatus = 3");
    $countServices = sqlsrv_fetch_array($countServices);
    $countServices = $countServices[total];
    if($countServices == 2){
        $result[alertId] = 0;
        $result[message] = 'Ya haz utilizado todas tus alertas disponibles.<br>Puedes utilizar el servicio de Llamado de Ambulancia';
    } else {
        $userInfo = sqlsrv_query($conn, "SELECT name, documentNumber, cellPhone FROM USERS_PROFILE WHERE userId = '$userId'");
        $userInfo = sqlsrv_fetch_array($userInfo);
        $userName = $userInfo[name];
        $userDocumentNumber = $userInfo[documentNumber];
        $userPhone = $userInfo[cellPhone];

        // CREATE RAND ALERT ID
        $randAlertId = sqlsrv_query($conn, "
            DECLARE @Random INT
            SELECT @Random = ROUND(((999999999) * RAND() + 1), 0)
            SELECT @Random AS alertId WHERE @Random NOT IN(SELECT alertId FROM PANIC_ALERT)
        ");
        $randAlertId = sqlsrv_fetch_array($randAlertId);
        $alertId = $randAlertId[alertId];

        // GET USER LATLNG
        $userLocation = explode(',', $userLocation);
        $lat = $userLocation[0];
        $lng = $userLocation[1];

        $uriTest = $wsAlertsHostURI.'EnviarAlerta';
        $params[KeyAccess] = $soapAlertsKeyAccess;
        $params[IdWebApp] = 1;
        $params[NombreCliente] = $userName;
        $params[Identificacion] = $userDocumentNumber;
        $params[TipoAlerta] = 4;
        $params[Telefono] = $userPhone;
        $params[Movil] = $userPhone;
        $params[Latitud] = $lat;
        $params[Longitud] = $lng;
        $params[IdCategoria] = 3;
        $params[CodigoAlertaAppMedio247] = $alertId;
        $query = http_build_query($params);
        $contextData = array (
            'method' => 'POST',
            'header' => "Connection: close\r\n".
                        "Content-Length: ".strlen($query)."\r\n",
            'content'=> $query);
        $context = stream_context_create (array ('http' => $contextData));
        $response = json_decode(file_get_contents($uriTest, false, $context));
        // SI USUARIO EXISTE QUE SE GUARDE LA DATA DE LA ALERTA
        if($response->CodigoRespuesta == 1){
            $result[alertId] = $alertId;
            $result[message] = $response->Mensaje;
            $saveAlertId = sqlsrv_query($conn, "
                INSERT INTO PANIC_ALERT
                    (alertId, userId, alertStatus, creationDate, status)
                VALUES
                    ('$alertId','$userId',1,'$actualDate',1)
            ");
        } else {
            $result[alertId] = 0;
            $result[message] = $response->Mensaje.$userId.' '.$lat.' '.$lng;
        }
    }

    echo json_encode($result);

}
?>
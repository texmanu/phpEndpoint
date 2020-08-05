<?php
$postdata = file_get_contents("php://input");
$request = json_decode($postdata);

if($action == 'getSalesPlanInfo'){
    $uriTest = $wsHostURI.'VentaPlanesDisponibles';
    $params[KeyAccess] = $soapKeyAccess;
    $query = http_build_query($params);
    $contextData = array (
        'method' => 'POST',
        'header' => "Connection: close\r\n".
                    "Content-Length: ".strlen($query)."\r\n",
        'content'=> $query);
    $context = stream_context_create (array ( 'http' => $contextData ));
    $response = json_decode(file_get_contents($uriTest, false, $context));
    $result = $response->Data;

    echo json_encode($result);
}
else if($action == 'getUserPlansInfo'){
    $userId = $request;
    $userData = sqlsrv_query($conn, "SELECT documentType, documentNumber FROM USERS_PROFILE WHERE userId = '$userId'");
    $userData = sqlsrv_fetch_array($userData);
    if($userData[documentType] == 'idNumber'){
        $documentType = 1;
    } else if ($userData[documentType] = 'residentNumber'){
        $documentType = 6;
    } else if ($userData[documentType] = 'passportNumber'){
        $documentType = 5;
    }
    $documentNumber = $userData[documentNumber];

    $uriTest = $wsHostURI.'ClienteMisPlanes';
    $params[KeyAccess] = $soapKeyAccess;
    $params[TipoIdentificacion] = $documentType;
    $params[NumeroIdentificacions] = $documentNumber;
    $query = http_build_query($params);
    $contextData = array (
        'method' => 'POST',
        'header' => "Connection: close\r\n".
                    "Content-Length: ".strlen($query)."\r\n",
        'content'=> $query);
    $context = stream_context_create (array ( 'http' => $contextData ));
    $response = json_decode(file_get_contents($uriTest, false, $context));
    $result = $response->Data;

    echo json_encode($result);
}
else if($action == 'getSalesPaymentMethods'){
    $uriTest = $wsHostURI.'PagosFormasDePago';
    $params[KeyAccess] = $soapKeyAccess;
    $query = http_build_query($params);
    $contextData = array (
        'method' => 'POST',
        'header' => "Connection: close\r\n".
                    "Content-Length: ".strlen($query)."\r\n",
        'content'=> $query);
    $context = stream_context_create (array ( 'http' => $contextData ));
    $response = json_decode(file_get_contents($uriTest, false, $context));
    $result = $response->Data;

    echo json_encode($result);
}
else if ($action == 'tengoPayment'){
    $userId = $request->userId;
    $IdPlan = $request->IdPlan;
    $IdPrecioRegistrado = $request->IdPrecioRegistrado;
    $RefPlanPagoId = $request->RefPlanPagoId;
    $IdRefTipoPago = $request->IdRefTipoPago;

    // USER EMAIL
    $userEmail = sqlsrv_query($conn, "SELECT email FROM USERS WHERE userId = '$userId'");
    $userEmail = sqlsrv_fetch_array($userEmail);
    $userEmail = $userEmail[email];

    // USER PROFILE DATA
    $userData = sqlsrv_query($conn, "SELECT name, documentType, documentNumber, cellPhone, birthday, gender, country, city, address FROM USERS_PROFILE WHERE userId = '$userId'");
    $userData = sqlsrv_fetch_array($userData);

    // USER VENDOR CODE
    $userVendorCode = sqlsrv_query($conn, "SELECT vendorId FROM USERS_VENDOR WHERE userId = '$userId'");
    $userVendorCode = sqlsrv_fetch_array($userVendorCode);
    $userVendorCode = $userVendorCode[vendorId];
    if($userVendorCode){
        $params[CodigoVendedor] = $userVendorCode;
    } else {
        $params[CodigoVendedor] = 0;
    }

    // VENTA REGISTRAR
    $uriTest = $wsHostURI.'VentaRegistrar';
    $params[KeyAccess] = $soapKeyAccess;
    $params[Identidad] = $userData[documentNumber];
    if($userData[documentType] == 'idNumber'){
        $userData[documentType] = 1;
    } else if ($userData[documentType] = 'residentNumber'){
        $userData[documentType] = 6;
    } else if ($userData[documentType] = 'passportNumber'){
        $userData[documentType] = 5;
    }

    // GET ACTUAL PLANTYPE ID
    $actualUserPlanType = sqlsrv_query($conn, "SELECT planId FROM USERS_PLANTYPE WHERE userId = '$userId' AND status = 1");
    $actualUserPlanType = sqlsrv_fetch_array($actualUserPlanType);
    $params[IdVentaAnular] = $actualUserPlanType[planId];

    $birthday = $userData[birthday]->format('Y-m-d');
    $params[TipoIdent] = $userData[documentType];
    $params[Nombre] = $userData[name];
    $params[Sexo] = $userData[gender];
    $params[FechaNacimiento] = $birthday;
    $params[RefCiudadId] = $userData[city];
    $params[Direccion] = $userData[address];
    $params[Telefono] = $userData[cellPhone];
    $params[Movil] = $userData[cellPhone];
    $params[Correo] = $userEmail;
    $params[RefPaisId] = $userData[country];

    $params[IdPlan] = $IdPlan;
    $params[IdPrecioRegistrado] = $IdPrecioRegistrado;
    $params[RefPlanPagoId] = $RefPlanPagoId;
    $params[IdRefTipoPago] = $IdRefTipoPago;

    $query = http_build_query($params);
    $contextData = array (
        'method' => 'POST',
        'header' => "Connection: close\r\n".
                    "Content-Length: ".strlen($query)."\r\n",
        'content'=> $query);
    $context = stream_context_create (array ( 'http' => $contextData ));
    $response = json_decode(file_get_contents($uriTest, false, $context));
    $result = $response;

    $RefVentaId = $response->Data[0]->CodigoVenta;
    // UPDATE USER PLANTYPE
    $userPlanType = sqlsrv_query($conn, "
        UPDATE USERS_PLANTYPE
        SET status = 0
        WHERE userId = '$userId'
    ");
    $userPlanType = sqlsrv_query($conn, "
        INSERT INTO USERS_PLANTYPE
            (userId,planId,creationDate,RefVentaId,status)
        VALUES
            ('$userId','$IdPlan','$actualDate','$RefVentaId',1)
    ");

    // PAGO REGISTRAR
    $uriTest = $wsHostURI . 'PagosRegistrarPago';
    $params[KeyAccess] = $soapKeyAccess;
    $params[IdMetodo] = $IdRefTipoPago;
    $params[IdVenta] = $RefVentaId;
    $params[numeroTelefono] = $params[Telefono];
    $params[numeroTarjeta] = '';
    $params[FechaVencimiento] = '';
    $params[codigoSeguridadTarjeta] = '';
    $params[DiaDebito] = 0;
    $params[debitoAutomatico] = 0;
    $params[Monto] = 0.00;

    $query = http_build_query($params);
    $contextData = array(
        'method' => 'POST',
        'header' => "Connection: close\r\n" .
            "Content-Length: " . strlen($query) . "\r\n",
        'content' => $query
    );
    $context = stream_context_create(array('http' => $contextData));
    $response = json_decode(file_get_contents($uriTest, false, $context));

    echo json_encode($result);
}
else if ($action == 'cardPayment'){
    $userId = $request->userId;
    $IdPlan = $request->IdPlan;
    $IdPrecioRegistrado = $request->IdPrecioRegistrado;
    $RefPlanPagoId = $request->RefPlanPagoId;
    $IdRefTipoPago = $request->IdRefTipoPago;
    $PrecioPlan = $request->PrecioPlan;
    $cardInfo = $request->cardInfo;

    // GET ACTUAL PLANTYPE ID
    $actualUserPlanType = sqlsrv_query($conn, "SELECT planId FROM USERS_PLANTYPE WHERE userId = '$userId' AND status = 1");
    $actualUserPlanType = sqlsrv_fetch_array($actualUserPlanType);
    $params2[IdVentaAnular] = $actualUserPlanType[planId];
    // USER EMAIL
    $userEmail = sqlsrv_query($conn, "SELECT email FROM USERS WHERE userId = '$userId'");
    $userEmail = sqlsrv_fetch_array($userEmail);
    $userEmail = $userEmail[email];
    // USER PROFILE DATA
    $userData = sqlsrv_query($conn, "SELECT name, documentType, documentNumber, cellPhone, birthday, gender, country, city, address FROM USERS_PROFILE WHERE userId = '$userId'");
    $userData = sqlsrv_fetch_array($userData);
    // USER VENDOR CODE
    $userVendorCode = sqlsrv_query($conn, "SELECT vendorId FROM USERS_VENDOR WHERE userId = '$userId'");
    $userVendorCode = sqlsrv_fetch_array($userVendorCode);
    $userVendorCode = $userVendorCode[vendorId];
    if($userVendorCode){
        $params2[CodigoVendedor] = $userVendorCode;
    } else {
        $params2[CodigoVendedor] = 0;
    }

    // PAGO ECOMMERCE
    $uriTest = $wsHostURI.'PagosEcomerce';
    $params3[KeyAccess] = $soapKeyAccess;
    $params3[amount] = $PrecioPlan;
    $params3[ccnumber] = $cardInfo->number;
    
    // EXP DATE
    $expDate = explode('-', $cardInfo->expDate);
    $monthExpDate = $expDate[1];
    $yearExpDate = substr($expDate[0], 2);
    $params3[ccexp] = $monthExpDate . $yearExpDate;
    $params3[Id] = 0;
    $params3[RefVentaId] = 0;
    $params3[RefCuotaId] = 1;

    $query = http_build_query($params3);
    $contextData = array(
        'method' => 'POST',
        'header' => "Connection: close\r\n" .
            "Content-Length: " . strlen($query) . "\r\n",
        'content' => $query
    );
    $context = stream_context_create(array('http' => $contextData));
    $response = json_decode(file_get_contents($uriTest, false, $context));
    $CodigoLog = $response->Data[0]->CodigoLog;
    if ($response->CodigoRespuesta == 0) {
        $result = $response;
    } else {
        // REGISTRAR VENTA
        $uriTest = $wsHostURI . 'VentaRegistrar';
        $params2[KeyAccess] = $soapKeyAccess;
        $params2[Identidad] = $userData[documentNumber];
        if ($userData[documentType] == 'idNumber') {
            $userData[documentType] = 1;
        } else if ($userData[documentType] = 'residentNumber') {
            $userData[documentType] = 6;
        } else if ($userData[documentType] = 'passportNumber') {
            $userData[documentType] = 5;
        }

        $birthday = $userData[birthday]->format('Y-m-d');
        $params2[TipoIdent] = $userData[documentType];
        $params2[Nombre] = $userData[name];
        $params2[Sexo] = $userData[gender];
        $params2[FechaNacimiento] = $birthday;
        $params2[RefCiudadId] = $userData[city];
        if ($userData[address]) {
            $params2[Direccion] = $userData[address];
        } else {
            $params2[Direccion] = 0;
        }
        $params2[Telefono] = $userData[cellPhone];
        $params2[Movil] = $userData[cellPhone];
        $params2[Correo] = $userEmail;
        $params2[RefPaisId] = $userData[country];
        $params2[IdPlan] = $IdPlan;
        $params2[IdPrecioRegistrado] = $IdPrecioRegistrado;
        $params2[RefPlanPagoId] = $RefPlanPagoId;
        $params2[IdRefTipoPago] = $IdRefTipoPago;

        $query = http_build_query($params2);
        $contextData = array(
            'method' => 'POST',
            'header' => "Connection: close\r\n" .
                "Content-Length: " . strlen($query) . "\r\n",
            'content' => $query
        );
        $context = stream_context_create(array('http' => $contextData));
        $response = json_decode(file_get_contents($uriTest, false, $context));
        $result = $response;

    // UPDATE USER PLANTYPE
        $RefVentaId = $response->Data[0]->CodigoVenta;
        $userPlanType = sqlsrv_query($conn, "
            UPDATE USERS_PLANTYPE
            SET status = 1,planId = '$IdPlan',creationDate = '$actualDate',RefVentaId='$RefVentaId',EstadoPago = 1
            WHERE userId = '$userId'
        ");
        /*$userPlanType = sqlsrv_query($conn, "
            INSERT INTO USERS_PLANTYPE
                (userId, planId, creationDate, RefVentaId, status)
            VALUES
                ('$userId','$IdPlan','$actualDate','$RefVentaId',1)
        ");*/

    // PAGO REGISTRAR
        $uriTest = $wsHostURI . 'PagosRegistrarPago';
        $params[KeyAccess] = $soapKeyAccess;
        $params[IdMetodo] = $IdRefTipoPago;
        $params[IdVenta] = $RefVentaId;
        $params[numeroTelefono] = $params2[Telefono];
        $params[numeroTarjeta] = $cardInfo->number;
        $params[FechaVencimiento] = $cardInfo->expDate . '-01';
        $params[codigoSeguridadTarjeta] = $cardInfo->cvcCode;
        $params[DiaDebito] = 5;
        $params[debitoAutomatico] = 1;
        $params[Monto] = $PrecioPlan;

        $query = http_build_query($params);
        $contextData = array(
            'method' => 'POST',
            'header' => "Connection: close\r\n" .
                "Content-Length: " . strlen($query) . "\r\n",
            'content' => $query
        );
        $context = stream_context_create(array('http' => $contextData));
        $response = json_decode(file_get_contents($uriTest, false, $context));

    // ACTUALIZAR ECOMMERCE
        $uriTest = $wsHostURI . 'PagoActualizaLogPayCom';
        $params4[KeyAccess] = $soapKeyAccess;
        $params4[IdVenta] = $RefVentaId;
        $params4[IdCuota] = 1;
        $params4[CodigoLog] = $CodigoLog;
        
        $query = http_build_query($params4);
        $contextData = array(
            'method' => 'POST',
            'header' => "Connection: close\r\n" .
                "Content-Length: " . strlen($query) . "\r\n",
            'content' => $query
        );
        $context = stream_context_create(array('http' => $contextData));
        $response = json_decode(file_get_contents($uriTest, false, $context));
    }

    echo json_encode($result);
}
?>

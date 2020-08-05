<?php
$postdata = file_get_contents("php://input");
$request = json_decode($postdata);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

if($action == 'checkUserStatus'){
    $userId = $request;
    $checkuserStatus = sqlsrv_query($conn, "SELECT status FROM USERS WHERE userId = $userId");
    if(sqlsrv_has_rows($checkuserStatus)){
        $checkuserStatus = sqlsrv_fetch_array($checkuserStatus);
        $result[status] = $checkuserStatus[status];
    } else{
        $result = 0;
    }
    echo json_encode($result);
}
else if($action == 'userLogin'){
    $email = $request->email;
    $password = $request->password;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo 2;
    } else {
        $userData = sqlsrv_query($conn, "SELECT password, userId, status FROM USERS WHERE email = '$email'");
        if(sqlsrv_has_rows($userData)){  
            $data = sqlsrv_fetch_array($userData);
            $passwordSaved = $data[password];
            $userId = $data[userId];

            $round = substr($userId, 0, 3)+892;
            $password = crypt($password, '$6$rounds='.$round.'$p0r547ud.'.$userId);

            if($password == $passwordSaved){
                $result[userId] = $userId;

                $userPlanCodeName = sqlsrv_query($conn, "SELECT TOP 1 planId FROM USERS_PLANTYPE WHERE userId = '$userId' ORDER BY creationDate DESC");
                $userPlanCodeName = sqlsrv_fetch_array($userPlanCodeName);
                $result[userPlanCodeName] = $userPlanCodeName[planId];

                $checkuserStatus = sqlsrv_query($conn, "SELECT status FROM USERS WHERE userId = $userId");
                $checkuserStatus = sqlsrv_fetch_array($checkuserStatus);
                $result[status] = $checkuserStatus[status];

                echo json_encode($result);
            }
            else{
                echo 1;
            }
        }
        else{
            echo 0;
        }
    }
}
else if($action == 'userCreate'){
    $email = $request->email;
    $password = $request->password;
    $vendorId = $request->vendorId;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo 2;
    } else {
        $query_email = sqlsrv_query($conn, "SELECT email FROM USERS WHERE email = '$email'");
        if(!sqlsrv_has_rows($query_email)){
            $userId_query = sqlsrv_query($conn, "
                DECLARE @Random INT
                SELECT @Random = ROUND(((999999999) * RAND() + 1), 0)
                SELECT @Random AS userId WHERE @Random NOT IN(SELECT userId FROM USERS)
            ");
            $userId_data = sqlsrv_fetch_array($userId_query);
            $userId = $userId_data[userId];

            $round = substr($userId, 0, 3)+892;
            $password = crypt($password, '$6$rounds='.$round.'$p0r547ud.'.$userId);

            $sql = sqlsrv_query($conn, "
                INSERT INTO USERS
                    (userId, email, password, creationDate, status)
                VALUES
                    ($userId,'$email','$password','$actualDate',0)
            ");

            // ASSIGN VENDOR
            if($vendorId != ''){
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
                if($response->CodigoRespuesta > 0){
                    $vendorName = $response->Data[0]->NombreVendedor;
                    $sql = sqlsrv_query($conn, "
                        INSERT INTO USERS_VENDOR
                            (userId, vendorId, vendorName, creationDate, status)
                        VALUES
                            ($userId,'$vendorId','$vendorName','$actualDate',1)
                    ");
                }
            }

            // USER PLAYTYPE FREE
            $sql = sqlsrv_query($conn, "
                INSERT INTO USERS_PLANTYPE
                    (userId, planId, creationDate, status)
                VALUES
                    ($userId, 566,'$actualDate',1)
            ");

            $result[userId] = $userId;
            $result[userPlanType] = '566';
            echo json_encode($result);
        }
        else{
            echo 0;
        }
    }
}
else if($action == 'userInfoById'){
    $documentType = $request->documentType;
    $documentNumber = $request->documentNumber;

    // CHECK IF DOCUMENT EXISTS IN LOCAL DB
    $checkDocument = sqlsrv_query($conn, "SELECT userId FROM USERS_PROFILE WHERE documentType = '$documentType' AND documentNumber = '$documentNumber'");
    if(sqlsrv_has_rows($checkDocument)){
        $result = 1;
    } else {
        if($documentType != 'idNumber'){
            $uriTest = $wsHostURI.'ClienteValidacionExiste';
            $params[KeyAccess] = $soapKeyAccess;
            $params[NumeroIdentificacions] = $documentNumber;

            if($documentType == 'idNumber'){
                $params[TipoIdentificacion] = 1;
            } else if($documentType == 'residentNumber'){
                $params[TipoIdentificacion] = 6;
            } else if($documentType == 'passportNumber'){
                $params[TipoIdentificacion] = 5;
            }

            $query = http_build_query($params);
            $contextData = array (
                'method' => 'POST',
                'header' => "Connection: close\r\n".
                            "Content-Length: ".strlen($query)."\r\n",
                'content'=> $query);
            $context = stream_context_create (array ( 'http' => $contextData ));
            $response = json_decode(file_get_contents($uriTest, false, $context));
            if($response->CodigoRespuesta > 0){
                $result = $response->Data[0];
                if(sizeof($result) == 0){
                    $result = 0;
                }
            } else {
                $result = 0;
            }
        } else {
            $uriTest = $wsHostURI.'ClienteValidacionIdentidad';
            $params[KeyAccess] = $soapKeyAccess;
            $params[NumeroIdentificacion] = $documentNumber;
            $query = http_build_query($params);
            $contextData = array (
                'method' => 'POST',
                'header' => "Connection: close\r\n".
                            "Content-Length: ".strlen($query)."\r\n",
                'content'=> $query);
            $context = stream_context_create (array ( 'http' => $contextData ));
            $response = json_decode(file_get_contents($uriTest, false, $context));

            if($response->CodigoRespuesta > 0){
                if(sizeof($response->Data) == 0){
                    $result = 0;
                } else {
                    $result[Nombre] = $response->Data[0]->Nombre;
                    $gender = $response->Data[0]->Sexo;
                    if($gender == 'M'){
                        $result[Sexo] = 'Masculino';
                    } else {
                        $result[Sexo] = 'Femenino';
                    }
                    $result[Birthday] = $response->Data[0]->FechaNacimiento;
                }
            } else {
                $result = 0;
            }
        }
    }

    echo json_encode($result);
}
//metodo para crear el user and user_profile de un solo. Se utiliza este metodo para tener un mejor control de estos datos.
elseif($action == 'userGenerate'){
    //datos requeridos para la creacion del perfil
    //Este metodo une a los dos metodos createuser y createprofile. Realmente ambos deben ser generados a un tiempo. 
    $userName= $request->name;
    $userEmail= $request->email;
    $userPassword= $request->password;
    $userVendorId= $resquest->vendorId;
    $userBirthday= $request->birthday;
    $userDocumentType= $request->documentType;
    $userDocumentNumber=$reques->documentNumber;
    $userGender=$request->gender;
    $userIdClient=$request->idClient;
    $userCity=$request->city;
    $userAddress=$request->address;
    $userCountry='3';
    $userClinic=3;
    //creacion de usario y perfil.
    try{
        //Generacion idUser
        $userId_query = sqlsrv_query($conn, "
                DECLARE @Random INT
                SELECT @Random = ROUND(((999999999) * RAND() + 1), 0)
                SELECT @Random AS userId WHERE @Random NOT IN(SELECT userId FROM USERS)
            ");
        $userId_data = sqlsrv_fetch_array($userId_query);
        $userId = $userId_data[userId];
        //Encriptacion de password
        $round = substr($userId, 0, 3)+892;
        $userPassword = crypt($userPassword, '$6$rounds='.$round.'$p0r547ud.'.$userId);
        //insercion de usuario. con un estatus cero
        $sql = sqlsrv_query($conn, "
            INSERT INTO USERS
                (userId, email, password, creationDate, status)
            VALUES
                ($userId,'$userEmail','$userPassword','$actualDate',0)
        ");
        //Busqueda y agregado de vendor en caso de que exista
        if($userVendorId != ''){
            $uriTest = $wsHostURI.'VendedorValidacion';
            $params[KeyAccess] = $soapKeyAccess;
            $params[CodigoVendedor] = $userVendorId;
            $query = http_build_query($params);
            $contextData = array (
                'method' => 'POST',
                'header' => "Connection: close\r\n".
                            "Content-Length: ".strlen($query)."\r\n",
                'content'=> $query);
            $context = stream_context_create (array ( 'http' => $contextData ));
            $response = json_decode(file_get_contents($uriTest, false, $context));
            if($response->CodigoRespuesta > 0){
                $vendorName = $response->Data[0]->NombreVendedor;
                $sql = sqlsrv_query($conn, "
                    INSERT INTO USERS_VENDOR
                        (userId, vendorId, vendorName, creationDate, status)
                    VALUES
                        ($userId,'$userVendorId','$vendorName','$actualDate',1)
                ");
            }else 
             $userVendorId=0;
        }


        //Asignacion de plan a user gratuito. 
        $sql = sqlsrv_query($conn, "
                    INSERT INTO USERS_PLANTYPE
                        (userId, planId, creationDate, status)
                    VALUES
                        ($userId, 566,'$actualDate',1)
                ");
        //Creacion del user-profile
        if ($userCity==22){
            $userClinic=9;
        }elseif($userCity==7){
            $userClinic=3;
        }elseif ($userCity==8){
            $userClinic=8;
        }
        $sql = sqlsrv_query($conn, "
            INSERT INTO USERS_PROFILE
                (userId, name, documentType, documentNumber, birthday, gender, creationDate, status, city, country, address, preferedClinic, idClient,planId)
            VALUES
                ('$userId','$userName','$userDocumentType','$userDocumentNumber','$UserBirthday','$userGender','$userActualDate','1','$userCity','$userCountry','$userAddress','$userClinic','$userIdClient','566')
        ");
        $sql = sqlsrv_query($conn, "
            UPDATE USERS
            SET status = 1
            WHERE userId = $userId
        ");
        $data[userId]=$userId;
        $respuesta[estado]=true;
        $respuesta[data]=$data;

    //Captura de errores
    }catch (Exception $e){
        $respuesta[estado]=false;
        $respuesta[mensaje]=$e->getMessage();
        $respuesta[data]=[];
        echo $respuesta;
    }
    
    


}

else if($action == 'userCreateProfile'){
    $userId = $request->userId;
    $name = $request->name;
    $documentType = $request->documentType;
    $documentNumber = $request->documentNumber;
    $birthday = $request->birthday;

    $gender = $request->gender;
    $address = $request->address;
    $city = $request->city;
    $country = '3';
    $idClient = $request->idClient;

    $sql = sqlsrv_query($conn, "
        INSERT INTO USERS_PROFILE
            (userId, name, documentType, documentNumber, birthday, gender, creationDate, status, city, country, address, preferedClinic, idClient)
        VALUES
            ('$userId','$name','$documentType','$documentNumber','$birthday','$gender','$actualDate','1','$city','$country','$address','$country','$idClient')
    ");

    $sql = sqlsrv_query($conn, "
        UPDATE USERS
        SET status = 1
        WHERE userId = $userId
    ");

    if($sql){
        echo 1;
    } else {
        echo 0;
    }
}
else if($action == 'userCellPhoneGetCode'){
    $userId = $request->userId;
    $cellPhone = $request->cellPhone;
    $randCodeActivation = rand(1111,9999);

    $uriTest = $wsAlertsHostURI.'EnviarMSJMovil';
    $params["KeyAccess"] = '4b88ab9492addb20f597501eb5391ed4';
    $params["MensajeTexto"] = 'app Médico 24-7. Tu código de activación es: '.$randCodeActivation;
    $params["Movil"] = $cellPhone;
    $query = http_build_query($params);
    $contextData = array (
        'method' => 'POST',
        'header' => "Connection: close\r\n".
                    "Content-Length: ".strlen($query)."\r\n",
        'content'=> $query);
    $context = stream_context_create (array ( 'http' => $contextData ));
    $response = json_decode(file_get_contents($uriTest, false, $context));
    if($response->CodigoRespuesta == 1){
        $sql = sqlsrv_query($conn, "
            UPDATE SMS_ACTIVATION
            SET status = 0
            WHERE userId = '$userId'
        ");
        $sql = sqlsrv_query($conn, "
            INSERT INTO SMS_ACTIVATION
                (userId, cellPhoneNumber, smsCode, creationDate, status)
            VALUES
                ('$userId','$cellPhone','$randCodeActivation','$actualDate',1)
        ");
        $result = 1;
    } else {
        $result = 0;
    }

    echo json_encode($result);
}
else if($action == 'userCellPhoneCheckCode'){
    $userId = $request->userId;
    $cellPhone = $request->cellPhoneNumber;
    $activationCode = $request->activationCode;

    $checkActivationCode = sqlsrv_query($conn, "SELECT * FROM SMS_ACTIVATION WHERE userId = '$userId' AND smsCode = '$activationCode' AND status = 1");
    if(sqlsrv_has_rows($checkActivationCode)){
        $sql = sqlsrv_query($conn, "
            UPDATE USERS_PROFILE
            SET cellPhone = '$cellPhone'
            WHERE userId = '$userId'
        ");
        $sql = sqlsrv_query($conn, "
            UPDATE USERS
            SET status = 2
            WHERE userId = '$userId'
        ");
        
        // FREE PLAN SAVE PONER DATOS DEL PLAN NIVEL I APP 5.3
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
        $userVendorCode = $userVendorCode["vendorId"];

        $uriTest = $wsHostURI.'SubCripcionPlanGratis';
        $params2["KeyAccess"] = $soapKeyAccess;
        $params2["Identidad"] = $userData[documentNumber];
        if($userData["documentType"] == 'idNumber'){
            $userData["documentType"] = 1;
        } else if ($userData["documentType"] = 'residentNumber'){
            $userData["documentType"] = 6;
        } else if ($userData["documentType"] = 'passportNumber'){
            $userData["documentType"] = 5;
        }

        $birthday = $userData[birthday]->format('Y-m-d');
        $params2[TipoIdent] = $userData[documentType];
        $params2[Nombre] = $userData[name];
        $params2[Sexo] = $userData[gender];
        $params2[FechaNacimiento] = $birthday;
        $params2[RefCiudadId] = $userData[city];
        if($userData[address]){
            $params2[Direccion] = $userData[address];
        } else {
            $params2[Direccion] = 0;
        }
        $params2[Telefono] = $userData[cellPhone];
        $params2[Movil] = $userData[cellPhone];
        $params2[Correo] = $userEmail;
        $params2[IdPlan] = 566;
        $params2[IdPrecioRegistrado] = 618;
        $params2[RefPlanPagoId] = 2;
        $params2[IdRefTipoPago] = 1;
        $params2[RefPaisId] = $userData[country];
        if($userVendorCode){
            $params2["CodigoVendedor"] = $userVendorCode;
        } else {
            $params2["CodigoVendedor"] = 0;
        }
        $params2[cuotasPago] = 12;
        $params2[IdVentaAnular] = 0;
        
        
        $result[dataSending]=json_encode($params2);
        $query = http_build_query($params2);
        $contextData = array (
            'method' => 'POST',
            'header' => "Connection: close\r\n".
                        "Content-Length: ".strlen($query)."\r\n",
            'content'=> $query);
        $context = stream_context_create (array ( 'http' => $contextData ));
        $response2 = json_decode(file_get_contents($uriTest, false, $context));
        
        $RefVentaId = $response2->CodigoVenta;
        $poliza=$response2->Poliza;
        $certificado=$response2->Certificado;
        $result[dataGet]=json_encode($response2->Poliza);
        $sql = sqlsrv_query($conn, "
            UPDATE USERS_PLANTYPE
            SET RefVentaId = '$RefVentaId'
            WHERE userId = '$userId'
        ");

        //actualizar user_profile datos 
        $sql= sqlsrv_query($conn, "
            UPDATE USER_PROFILE
            SET poliza= '$poliza',
            certificado='$certificado'
            WHERE userID='$userId'"
        );

        $result[mjs]='En teoria todo va bien';
        

    } else {
        $resulta=0;
    }

    echo json_encode($result);
}
else if($action == 'userFullProfile'){
    $userId = $request;

    // USER DATA
    $userEmail = sqlsrv_query($conn, "SELECT email FROM USERS WHERE userId = '$userId'");
    $userEmail = sqlsrv_fetch_array($userEmail);
    $result[email] = $userEmail[email];

    // USER PROFILE
    $userProfile = sqlsrv_query($conn, "SELECT idClient, name, documentType, documentNumber, birthday, cellPhone, gender, country, city, preferedClinic, address, planId, creationDate FROM USERS_PROFILE WHERE userId = '$userId'");
    $userProfile = sqlsrv_fetch_array($userProfile);
    
    $result[name] = $userProfile[name];

    $documentType = $userProfile[documentType];
    if($documentType == 'idNumber'){
        $result[documentType] = 'Identidad';
    } else if($documentType == 'passportNumber'){
        $result[documentType] = 'Pasaporte';
    } else if($documentType == '6'){
        $result[documentType] = 'residentNumber';
    }

    $result[documentNumber] = $userProfile[documentNumber];
    $result[birthday] = $userProfile[birthday];
    $result[cellPhone] = $userProfile[cellPhone];

    $gender = $userProfile[gender];
    if($gender == 1){
        $result[gender] = 'Masculino';
    } else {
        $result[gender] = 'Femenino';
    }

    $countryId = $userProfile[country];
    $countryName = sqlsrv_query($conn, "SELECT countryName FROM COUNTRIES WHERE wsCountryId = '$countryId'");
    $countryName = sqlsrv_fetch_array($countryName);
    $result[country] = $countryName[countryName];

    $cityId = $userProfile[city];
    $cityName = sqlsrv_query($conn, "SELECT cityName FROM CITIES WHERE wsCityId = '$cityId'");
    if(sqlsrv_has_rows($cityName)){
        $cityName = sqlsrv_fetch_array($cityName);
        $result[city] = $cityName[cityName];
    } else {
        $result[city] = 'Seleccionar ciudad';
    }

    $preferedClinicId = $userProfile[preferedClinic];
    $preferedClinicName = sqlsrv_query($conn, "SELECT name FROM CLINICS WHERE wsClinicId = '$preferedClinicId'");
    $preferedClinicName = sqlsrv_fetch_array($preferedClinicName);
    $preferedClinicName = $preferedClinicName[name];
    if($preferedClinicName == ''){
        $result[preferedClinic] = 'Seleccionar clínica';
    } else {
        $result[preferedClinic] = $preferedClinicName;
    }
    
    $address = $userProfile[address];
    if($address == ''){
        $result[address] = 'Ingresa tu domicilio.';
    } else {
        $result[address] = $address;
    }

    // USER PLAN NAME
    $planId = sqlsrv_query($conn, "SELECT TOP (1) planId FROM USERS_PLANTYPE WHERE userId = '$userId' ORDER BY creationDate DESC");
    $planId = sqlsrv_fetch_array($planId);
    $planId = $planId[planId];

    $uriTest = $wsHostURI.'/VentaPlanesDisponibles';
    $params[KeyAccess] = $soapKeyAccess;
    $query = http_build_query($params);
    $contextData = array (
        'method' => 'POST',
        'header' => "Connection: close\r\n".
                    "Content-Length: ".strlen($query)."\r\n",
        'content'=> $query);
    $context = stream_context_create (array ( 'http' => $contextData ));
    $response = json_decode(file_get_contents($uriTest, false, $context));
    $plans = $response->Data;
    $result[plansList] = $plans;

    echo json_encode($result);
}
else if($action == 'saveUserCountry'){
    $userId = $request->userId;
    $countryId = $request->countryId;

    $save = sqlsrv_query($conn,"
        UPDATE USERS_PROFILE
        SET country = '$countryId'
        WHERE userId = '$userId'
    ");

    $countryName = sqlsrv_query($conn, "SELECT countryName FROM COUNTRIES WHERE wsCountryId = '$countryId'");
    $countryName = sqlsrv_fetch_array($countryName);
    $result = $countryName[countryName];
    echo json_encode($result);
}
else if($action == 'saveUserCity'){
    $userId = $request->userId;
    $cityId = $request->cityId;

    $save = sqlsrv_query($conn,"
        UPDATE USERS_PROFILE
        SET city = '$cityId'
        WHERE userId = '$userId'
    ");

    $cityName = sqlsrv_query($conn, "SELECT cityName FROM CITIES WHERE wsCityId = '$cityId'");
    $cityName = sqlsrv_fetch_array($cityName);
    $result = trim($cityName[cityName]);

    echo json_encode($result);
}
else if($action == 'saveUserPreferedClinic'){
    $userId = $request->userId;
    $preferedClinic = $request->preferedClinic;

    $save = sqlsrv_query($conn,"
        UPDATE USERS_PROFILE
        SET preferedClinic = '$preferedClinic'
        WHERE userId = '$userId'
    ");

    $clinicName = sqlsrv_query($conn, "SELECT name FROM CLINICS WHERE wsClinicId = '$preferedClinic'");
    $clinicName = sqlsrv_fetch_array($clinicName);
    $result = $clinicName[name];

    echo json_encode($result);
}
else if($action == 'saveUserAddress'){
    $userId = $request->userId;
    $address = $request->address;

    $save = sqlsrv_query($conn, "
        UPDATE USERS_PROFILE
        SET address = '$address'
        WHERE userId = '$userId'
    ");

    echo 1;
}
else if($action == 'userMedicalRecord'){
    $userId = $request;
    $userDocumentInfo = sqlsrv_query($conn, "SELECT documentType, documentNumber FROM USERS_PROFILE WHERE userId = '$userId'");
    $userDocumentInfo = sqlsrv_fetch_array($userDocumentInfo);
    $userDocumentType = $userDocumentInfo[documentType];
    if($userDocumentType == 'idNumber'){
        $userDocumentType = 1;
    } else if($userDocumentType == 'residentNumber'){
        $userDocumentType = 2;
    } else if($userDocumentType == 'passportNumber'){
        $userDocumentType = 3;
    } 
    $userDocumentNumber = $userDocumentInfo[documentNumber];

    $uriTest = $wsHostURI.'ClienteHistorialMedico';
    $params[KeyAccess] = $soapKeyAccess;
    $params[TipoIdentificacion] = $userDocumentType;
    $params[NumeroIdentificacions] = $userDocumentNumber;

    $query = http_build_query($params);
    $contextData = array (
        'method' => 'POST',
        'header' => "Connection: close\r\n".
                    "Content-Length: ".strlen($query)."\r\n",
        'content'=> $query);
    $context = stream_context_create (array ( 'http' => $contextData ));
    $response = json_decode(file_get_contents($uriTest, false, $context));
    if($response->CodigoRespuesta == 1){
        $result =  $response->Data;
    } else {
        $result = 0;
    }

    echo json_encode($result);
}
else if($action == 'userPasswordRecovery'){
    $email = $request;
    
    require '../thirdPackages/vendor/autoload.php';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo 2;
    } else {
        $checkEmail = sqlsrv_query($conn, "SELECT email, userId FROM USERS WHERE email = '$email'");
        if(sqlsrv_has_rows($checkEmail)){
            $userData = sqlsrv_fetch_array($checkEmail);
            $userId = $userData['userId'];
            $userEmail = $userData['email'];
            echo $userEmail;
            
            $key = $userId.$actualDate;
            $round = rand(2,100);
            $key = crypt($key, $round);

            $sql = sqlsrv_query($conn, "
                UPDATE USERS_PASSWORD_RECOVERY
                SET status = 0
                WHERE userId = '$userId'
            ");

            $sql = sqlsrv_query($conn, "
                INSERT INTO USERS_PASSWORD_RECOVERY
                    (userId, auth, creationDate, status)
                VALUES
                    ('$userId','$key','$actualDate',1)
            ");

            // SEND EMAIL
            $key = base64_encode($key);
             $link = 'https://app.porsalud.net/passwordRecovery.php?pr='.$key;
            $message = "
                <html>
                <head>
                    <title>PORSALUD | Password Recovery</title>
                </head>
                    <body style='width: 600px; margin: auto;'>
                        <p style='padding: 10px 0;'>En PORSALUD, tu seguridad es lo primero.</p>
                        <a href='$link'>Haz click aquí recuperar tu contraseña.</a>
                    </body>
                </html>
            ";

            $mail = new PHPMailer(true);
            // SERVER SETTINGS
            $mail->isSMTP();
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            
            $mail->Host = 'smtp.office365.com';
            $mail->Port = 587;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAuth = true;
            $mail->Username = 'notify@porsalud.net';
            $mail->Password = '#$DS#kj34@#$2';
            
            

            // RECIPIENTS
            $mail->setFrom('notify@porsalud.net', 'PORSALUD');
            $mail->addAddress($userEmail);

            // CONTENT
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            $mail->Subject = "PORSALUD | Password Recovery";
            $mail->Body = $message;
            
            //add be manuel 
            // if(!$mail->send()) {
            //     echo 'Message could not be sent.';
            //     echo 'Mailer Error: ' . $mail->ErrorInfo;
            // } else {
            //     echo 'Message has been sent';
            // }

            // if before manuel
            if($mail->send()){
                echo 1;
            } else {
                echo 0;
            }
        } else {
            echo 3;
        }
    }
}
?>

<?php
$postdata = file_get_contents("php://input");
$request = json_decode($postdata);

if($action == 'getClinicsCountries'){
    $getCountries = sqlsrv_query($conn, "SELECT wsCountryId, countryName FROM COUNTRIES WHERE status = 1");
    $i=0;
    while($data = sqlsrv_fetch_array($getCountries)){
        $result[$i][type] = 'radio';
        $result[$i][value] = $data[wsCountryId];
        $result[$i][label] = trim($data[countryName]);
        if($i == 0){
            $result[$i][checked] = true;
        } else {
            $result[$i][checked] = false;
        }

        $i++;
    }
    
    echo json_encode($result);
}
else if($action == 'getClinicsCities'){
    $countryName = $request;
    $getCountryId = sqlsrv_query($conn, "SELECT wsCountryId FROM COUNTRIES WHERE countryName = '$countryName'");
    $getCountryId = sqlsrv_fetch_array($getCountryId);
    $getCountryId = $getCountryId[wsCountryId];

    $uriTest = $wsHostURI.'ListaCiudadPorPais';
    $params[KeyAccess] = $soapKeyAccess;
    $params[CodigoPais] = $getCountryId;
    
    $query = http_build_query($params);
    $contextData = array (
        'method' => 'POST',
        'header' => "Connection: close\r\n".
                    "Content-Length: ".strlen($query)."\r\n",
        'content'=> $query);
    $context = stream_context_create (array ( 'http' => $contextData ));
    $response = json_decode(file_get_contents($uriTest, false, $context));
    if($response->CodigoRespuesta == 1){
        $cities = $response->Data;
        for ($i=0; $i < sizeOf($cities); $i++) { 
            $result[$i][type] = 'radio';
            $result[$i][label] = trim($cities[$i]->Ciudad);
            $result[$i][value] = $cities[$i]->Id;
            if($i == 0){
                $result[$i][checked] = true;
            } else {
                $result[$i][checked] = false;
            }
        }
    }
    else{
        $result = 0;
    }

    echo json_encode($result);
}
else if($action == 'getClinicsInCity'){
    $cityName = $request;
    $getCityId = sqlsrv_query($conn, "SELECT wsCityId FROM CITIES WHERE cityName = '$cityName'");
    $getCityId = sqlsrv_fetch_array($getCityId);
    $getCityId = $getCityId[wsCityId];

    $uriTest = $wsHostURI.'ListaSedePorCiudad';
    $params[KeyAccess] = $soapKeyAccess;
    $params[IdCiudad] = $getCityId;
    $query = http_build_query($params);
    $contextData = array (
        'method' => 'POST',
        'header' => "Connection: close\r\n".
                    "Content-Length: ".strlen($query)."\r\n",
        'content'=> $query);
    $context = stream_context_create (array ( 'http' => $contextData ));
    $response = json_decode(file_get_contents($uriTest, false, $context));
    if($response->CodigoRespuesta == 1){
        $clinics = $response->Data;
        for ($i=0; $i < sizeOf($clinics); $i++) { 
            $result[$i][type] = 'radio';
            $result[$i][label] = trim($clinics[$i]->SedeNombre);
            $result[$i][value] = $clinics[$i]->Idsede;
            if($i == 0){
                $result[$i][checked] = true;
            } else {
                $result[$i][checked] = false;
            }
        }
    } else {
        $result = 0;
    }

    echo json_encode($result);
}
else if($action == 'getUserDataCountriesCitiesClinics'){
    $userId = $request;
    $countryName = sqlsrv_query($conn, "SELECT countryName FROM COUNTRIES WHERE wsCountryId = (SELECT country FROM USERS_PROFILE WHERE userId = '$userId')");
    if(sqlsrv_has_rows($countryName)){
        $countryName = sqlsrv_fetch_array($countryName);
        $result["userData"]["country"] = $countryName["countryName"];
    } else {
        $result["userData"]["country"] = 'Honduras';
    }

    $cityName = sqlsrv_query($conn, "SELECT cityName FROM CITIES WHERE wsCityId = (SELECT city FROM USERS_PROFILE WHERE userId = '$userId')");
    if(sqlsrv_has_rows($cityName)){
        $cityName = sqlsrv_fetch_array($cityName);
        $result["userData"]["city"] = $cityName["cityName"];
    } else {
        $result["userData"]["city"] = 'Tegucigalpa';
    }

    $countries = sqlsrv_query($conn, "SELECT wsCountryId, countryName FROM COUNTRIES WHERE status = 1");
    $co=0;
    while($data = sqlsrv_fetch_array($countries)){
        $result["countries"][$co]["name"] = trim($data["countryName"]);
        
        $countryId = $data["wsCountryId"];
        $uriTest = $wsHostURI.'ListaCiudadPorPais';
        $params["KeyAccess"] = $soapKeyAccess;
        $params["CodigoPais"] = $countryId;
        $query = http_build_query($params);
        $contextData = array (
            'method' => 'POST',
            'header' => "Connection: close\r\n".
                        "Content-Length: ".strlen($query)."\r\n",
            'content'=> $query);
        $context = stream_context_create(array('http' => $contextData));
        $response = json_decode(file_get_contents($uriTest, false, $context));
        if($response->CodigoRespuesta == 1){
            $cities = $response->Data;
            for ($ci=0; $ci < sizeOf($cities); $ci++) { 
                $result["countries"][$co]["cities"][$ci]["cityName"] = trim($cities[$ci]->Ciudad);
                $cityName = $cities[$ci]->Ciudad;
                $clinics = sqlsrv_query($conn, "SELECT wsClinicId, name, address, email, phoneNumber, latlng, schedule FROM CLINICS WHERE city = '$cityName' AND status = 1 ORDER BY orderPosition DESC");
                $cl=0;
                while ($clData = sqlsrv_fetch_array($clinics)) {
                    $result["countries"][$co]["cities"][$ci]["clinics"][$cl]["name"] = trim($clData["name"]);
                    $result["countries"][$co]["cities"][$ci]["clinics"][$cl]["address"] = trim($clData["address"]);
                    $result["countries"][$co]["cities"][$ci]["clinics"][$cl]["email"] = trim($clData["email"]);
                    $result["countries"][$co]["cities"][$ci]["clinics"][$cl]["phoneNumber"] = trim($clData["phoneNumber"]);
                    $latlng = trim($clData["latlng"]);
                    $latlng = explode(',',$latlng);
                    $result["countries"][$co]["cities"][$ci]["clinics"][$cl]["lat"] = $latlng[0];
                    $result["countries"][$co]["cities"][$ci]["clinics"][$cl]["lng"] = $latlng[1];
                    $result["countries"][$co]["cities"][$ci]["clinics"][$cl]["schedule"] = trim($clData["schedule"]);
                    $result["countries"][$co]["cities"][$ci]["clinics"][$cl]["wsClinicId"] = trim($clData["wsClinicId"]);

                    $cl++;
                }
            }
        } else {
            $result["countries"][$co]["cities"][0] = $response;
        }
        $co++;
    }

    echo json_encode($result);
}
else if($action == 'getClinicsSpecialty'){
    $wsClinicId = $request;

    $uriTest = $wsHostURI.'ClinicaPorEspecialidades';
    $params[KeyAccess] = $soapKeyAccess;
    $params[IdSede] = $wsClinicId;
    $query = http_build_query($params);
    $contextData = array (
        'method' => 'POST',
        'header' => "Connection: close\r\n".
                    "Content-Length: ".strlen($query)."\r\n",
        'content'=> $query);
    $context = stream_context_create (array ( 'http' => $contextData ));
    $response = json_decode(file_get_contents($uriTest, false, $context));
    if($response->CodigoRespuesta == 1){
        $result = $response->Data;
    }
    echo json_encode($result);
}
else if($action == 'getDoctorsBySpecialty'){
    $clinicId = $request->clinicId;
    $specialtyId = $request->specialtyId;

    $uriTest = $wsHostURI.'MedicosEspecialidadSede';
    $params[KeyAccess] = $soapKeyAccess;
    $params[CodigoEspecialidad] = $specialtyId;
    $params[IdSede] = $clinicId;
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
else if($action == 'getDoctorsSearchData'){
    $userId = $request;
    $countryName = sqlsrv_query($conn, "SELECT countryName FROM COUNTRIES WHERE wsCountryId = (SELECT country FROM USERS_PROFILE WHERE userId = '$userId')");
    if(sqlsrv_has_rows($countryName)){
        $countryName = sqlsrv_fetch_array($countryName);
        $result[userData][country] = $countryName[countryName];
    } else {
        $result[userData][country] = 'Honduras';
    }

    $cityName = sqlsrv_query($conn, "SELECT cityName FROM CITIES WHERE wsCityId = (SELECT city FROM USERS_PROFILE WHERE userId = '$userId')");
    if(sqlsrv_has_rows($cityName)){
        $cityName = sqlsrv_fetch_array($cityName);
        $result[userData][city] = $cityName[cityName];
    } else {
        $result[userData][city] = 'Tegucigalpa';
    }

    $userPreferedClinic = sqlsrv_query($conn, "SELECT name, wsClinicId FROM CLINICS WHERE wsClinicId = (SELECT preferedClinic FROM USERS_PROFILE WHERE userId = '$userId')");
    if(sqlsrv_has_rows($userPreferedClinic)){
        $data = sqlsrv_fetch_array($userPreferedClinic);
        $result[userData][preferedClinic][id] = $data[wsClinicId];
        $result[userData][preferedClinic][name] = $data[name];
    }
    else {
        $result[userData][preferedClinic] = 0;
    }

    $countries = sqlsrv_query($conn, "SELECT wsCountryId, countryName FROM COUNTRIES WHERE status = 1");
    $co=0;
    while($data = sqlsrv_fetch_array($countries)){
        $result[countries][$co][name] = trim($data[countryName]);
        
        $countryId = $data[wsCountryId];
        $uriTest = $wsHostURI.'ListaCiudadPorPais';
        $params[KeyAccess] = $soapKeyAccess;
        $params[CodigoPais] = $countryId;
        $query = http_build_query($params);
        $contextData = array (
            'method' => 'POST',
            'header' => "Connection: close\r\n".
                        "Content-Length: ".strlen($query)."\r\n",
            'content'=> $query);
        $context = stream_context_create (array ( 'http' => $contextData ));
        $response = json_decode(file_get_contents($uriTest, false, $context));
        if($response->CodigoRespuesta == 1){
            $cities = $response->Data;
            for ($ci=0; $ci < sizeOf($cities); $ci++) { 
                $result[countries][$co][cities][$ci][cityName] = trim($cities[$ci]->Ciudad);
                $cityName = $cities[$ci]->Ciudad;
                $clinics = sqlsrv_query($conn, "SELECT wsClinicId, name, address, email, phoneNumber, latlng, schedule FROM CLINICS WHERE city = '$cityName' AND status = 1 ORDER BY orderPosition DESC");
                $cl=0;
                while ($clData = sqlsrv_fetch_array($clinics)) {
                    $result[countries][$co][cities][$ci][clinics][$cl][name] = trim($clData[name]);
                    $result[countries][$co][cities][$ci][clinics][$cl][wsClinicId] = trim($clData[wsClinicId]);

                    $wsClinicId = trim($clData[wsClinicId]);
                    $uriTest = $wsHostURI.'ClinicaPorEspecialidades';
                    $params[KeyAccess] = $soapKeyAccess;
                    $params[IdSede] = $wsClinicId;
                    $query = http_build_query($params);
                    $contextData = array (
                        'method' => 'POST',
                        'header' => "Connection: close\r\n".
                                    "Content-Length: ".strlen($query)."\r\n",
                        'content'=> $query);
                    $context = stream_context_create (array ( 'http' => $contextData ));
                    $response = json_decode(file_get_contents($uriTest, false, $context));
                    if($response->CodigoRespuesta == 1){
                        $result[countries][$co][cities][$ci][clinics][$cl][clinicSpecialties] = $response->Data;
                    }

                    $cl++;
                }
            }
        } else {
            $result[countries][$co][response] = false;
        }
        
        $co++;
    }

    echo json_encode($result);
}
else if($action == 'getDoctorsBySpecialtyIdClinicId'){
    $clinicId = $request->clinicId;
    $specialtyId = $request->specialtyId;
    
    $uriTest = $wsHostURI.'MedicosEspecialidadSede';
    $params[KeyAccess] = $soapKeyAccess;
    $params[CodigoEspecialidad] = $specialtyId;
    $params[IdSede] = $clinicId;
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
?>

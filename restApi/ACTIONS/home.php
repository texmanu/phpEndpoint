<?php
$postdata = file_get_contents("php://input");
$request = json_decode($postdata);

if($action == 'carouselServices'){
    $getCarouselServicesData = sqlsrv_query($conn, "SELECT benefitId, serviceCodeName, phoneNumber, status FROM CAROUSEL_SERVICES WHERE status = 1 ORDER BY itemOrder ASC");
    $i=0;
    while($data = sqlsrv_fetch_array($getCarouselServicesData)){
        $result[$i][serviceCodeName] = $data[serviceCodeName];
        $result[$i][phone] = $data[phoneNumber];
        $result[$i][icon] = './assets/imgs/svgs/carousel_'.$data[serviceCodeName].'.svg';
        $result[$i][servicesStatus] = $data[status];
        $result[$i][benefitId] = $data[benefitId];

        // CHECK AVAILABILITY IN PLANS
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

        $plans = $response->Data;
        $benefitId = $data[benefitId];
        $result[$i][availableInPlan] = '';

        for ($pi=0; $pi < sizeof($plans); $pi++) { 
            $planBenefits = $plans[$pi]->Beneficios;
            for ($pb=0; $pb < sizeof($planBenefits); $pb++) { 
                if($plans[$pi]->Beneficios[$pb]->Codigo == $benefitId){
                    $result[$i][availableInPlan] .= $plans[$pi]->Id;
                    if($plans[$pi] <= sizeof($plans)){
                        $result[$i][availableInPlan] .= ',';
                    }
                }
            }
        }

        $i++;
    }

    echo json_encode($result);
}

?>
<?php
$postdata = file_get_contents("php://input");
$request = json_decode($postdata);

if($action == 'getInstagramFeed'){
    $token = '1449591018.d67a3fc.1dc00e0e8a714f2a8728a9ec2e60e01c';
    $code = '785dfe0a3da2405c82aff23ab7254420';
    $totalPost = 10;

	$feedURI = "https://api.instagram.com/v1/users/self/media/recent/?access_token=".$token."&count=".$totalPost;
	$response = file_get_contents($feedURI);
	$response = json_decode(preg_replace('/("\w+"):(\d+)/', '\\1:"\\2"', $response), true);
	for ($i=0; $i < sizeOf($response[data]); $i++) { 
		$result[$i][img] = $response[data][$i][images][standard_resolution][url];
		$result[$i][text] = $response[data][$i][caption][text];
	}

	echo json_encode($result);
}
?>

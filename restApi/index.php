<?php
include_once('../c0n3x10n.php');
$action = $_REQUEST[action];
if($action == ''){
	header('Location: https://www.porsalud.net'); exit();
	//header('Location: https://www.testporsalud.net'); exit();
}

// GET
$dir = 'ACTIONS/';
$filesToGet  = scandir($dir);
array_splice($filesToGet, 0, 1);
array_splice($filesToGet, 0, 1);
for($i=0; $i < sizeof($filesToGet); $i++){
	$file = $filesToGet[$i];
	include_once('ACTIONS/'.$file);
}
?>

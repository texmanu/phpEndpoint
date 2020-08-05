<?php
include_once('c0n3x10n.php');

$password = $_POST['password'];
$key = $_GET[pr];
$key = base64_decode($key);
if(!$password){
    $checkAuthKey = sqlsrv_query($conn, "SELECT status FROM USERS_PASSWORD_RECOVERY WHERE auth = '$key'");
    $checkAuthKey = sqlsrv_fetch_array($checkAuthKey);
    $authKeyStatus = $checkAuthKey[status];
}
?>

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>PORSALUD | Password Recovery System</title>
	
	<script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">

	<link rel="stylesheet" href="https://getbootstrap.com/docs/4.0/examples/sign-in/signin.css">
    <script type="text/javascript">
        function savePassword(){
            var password, confirmPassword;
            password = $('.form-signin input[name=password]').val();
            confirmPassword = $('.form-signin input[name=passwordConfirm]').val();
            if(password !== confirmPassword){
                alert('Las contraseñas no coinciden.');
            } else {
                $('.form-signin').submit();
            }
        }
    </script>
</head>
<body>
	<div class="container text-center">
    <?php
    if($authKeyStatus == 1){
    ?>
		<form class="form-signin" method="POST" action="">
			<img class="mb-4" src="assets/icon.png" alt="" width="80" height="80">
			<h1 class="h4 mb-3 font-weight-bold">Recuperación de contraseña</h1>
			<label class="sr-only" for="password">Nueva Contraseña</label>
			<input type="password" class="form-control mb-2" id="password" name="password" placeholder="Contraseña" required="true">
            <label class="sr-only" for="passwordConfirm">Confirmar Contraseña</label>
			<input type="password" class="form-control mb-2" id="password" name="passwordConfirm" placeholder="Confirmar Contraseña" required="true">
			<button type="button" class="btn btn-success mb-2 btn-block" onclick="savePassword()">Salvar nueva contraseña</button>
		</form>
    <?php
    } else if($password){
        $userId = sqlsrv_query($conn, "SELECT userId FROM USERS_PASSWORD_RECOVERY WHERE auth = '$key'");
        $userId = sqlsrv_fetch_array($userId);
        $userId = $userId[userId];
        
        $round = substr($userId, 0, 3)+892;
        $password = crypt($password, '$6$rounds='.$round.'$p0r547ud.'.$userId);

        sqlsrv_query($conn, "
            UPDATE USERS
            SET password = '$password'
            WHERE userId = '$userId'
        ");

        sqlsrv_query($conn, "
            UPDATE USERS_PASSWORD_RECOVERY
            SET status = 0
            WHERE userId = '$userId'
        ");
    ?>
        <img class="mb-4" src="assets/icon.png" alt="" width="80" height="80">
        <h1 class="h4 mb-3 font-weight-bold text-success">Tu nueva contraseña ha sido salvada.</h1>
        <p class="h4 mb-3">Ya puedes entrar a nuestra aplicación.</p>
    <?php
    } else {
    ?>
        <img class="mb-4" src="assets/icon.png" alt="" width="80" height="80">
        <h1 class="h4 mb-3 font-weight-bold text-success">Este acceso ya no esta disponible.</h1>
        <p class="h4 mb-3">Solicita uno nuevo desde la aplicación.</p>
	</div>
    <?php
    }
    ?>
</body>
</html>

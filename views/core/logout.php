<?php
use Dashboard\Core\Database;
use Dashboard\Core\SecureSession;
use Dashboard\Core\User;

$user->Logout();
 
header('location: /login'); 
exit;
?>

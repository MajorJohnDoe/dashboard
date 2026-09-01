<?php
use Dashboard\Core\CsrfProtection;

// Rotate the CSRF token before destroying the session
CsrfProtection::regenerate();

$user->Logout();
 
header('location: /login'); 
exit;
?>

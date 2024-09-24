<?php
// login.php
use Dashboard\Core\Database;
use Dashboard\Core\SecureSession;
use Dashboard\Core\User;

// These variables should be available from the router
/** @var Database $db */
/** @var SecureSession $session */
/** @var User $user */

$error = null;

if ($user->isLoggedIn()) {
    error_log("User is logged in, redirecting to home page");
    header('Location: /');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    sleep(1); // Simulate a delay for 1 second to make it harder to brute force
    
    $username = $_POST['username'];
    $password = $_POST['password'];
    $persistent = true;

    error_log("Login attempt for username: " . $username);

    if ($user->login($username, $password, $persistent)) {
        error_log("Login successful, redirecting to home page");
        header('Location: /');
        exit;
    } else {
        error_log("Login failed");
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>A Meaningful life with tasks</title>
</head>
<body>
    <div class="background">
        <div class="content">
            <h2>Login</h2>
            <?php if (isset($error)): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form action="/login" method="post">
                <div>
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required />
                </div>
                <div>
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required />
                </div>
                <div style="text-align: right; width: 100%;">
                    <input type="submit" value="Login" name="submit" />
                </div>
            </form>
        </div>
    </div>

    <style>
        body { 
            margin: 0; 
            background-color: #4eafcd; 
            font-family: Arial, sans-serif;
        }
        .background { 
            width: 100%; 
            height: 100vh; 
            margin: 0; 
            padding: 0; 
            display: flex; 
            align-items: center; 
            justify-content: center;
        }
        .content { 
            color: #44484c; 
            width: 350px; 
            max-height: 300px; 
            background-color: #fff;
            padding: 20px;  
            border: 20px solid #469eb9; 
            border-radius: 10px;
        }
        .sysmessage {
            margin: 10px 0 0 0;
            color: #245767;
        }
        input[type="text"], 
        input[type="password"] { 
            border: 1px solid #e1e1e1; 
            font-size: 16px; 
            margin: 5px 0 10px 0; 
            color: #435059; 
            background-color: #f1faff; 
            height: 30px; 
            line-height: 35px; 
            padding: 0 0 0 7px; 
            width: 95%; 
        }
        input[type="submit"] {
            background-color: #469eb9;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #3a8ca3;
        }
        .error {
            color: red;
            margin-bottom: 10px;
        }
    </style>
</body>
</html>
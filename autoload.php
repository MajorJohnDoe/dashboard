<?php
    // handle namespaces
    spl_autoload_register(function ($class) {
        // Project-specific namespace prefix
        $prefix = 'Dashboard\\';
    
        // Base directory for the namespace prefix
        $base_dir = __DIR__ . '/classes/';
    
        // Does the class use the namespace prefix?
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            // No, move to the next registered autoloader
            return;
        }
    
        // Get the relative class name
        $relative_class = substr($class, $len);
    
        // Convert namespace separators to directory separators,
        // append with .class.php, and handle 'core' directory
        $file_path = str_replace('\\', '/', $relative_class);
        $file_parts = explode('/', $file_path);
        
        // Handle nested directories (e.g., Core/Notifications/Handlers/)
        $file = $base_dir . $file_path;
        
        // Try with .class.php suffix first
        if (file_exists($file . '.class.php')) {
            require $file . '.class.php';
        } 
        // Try with .php suffix
        elseif (file_exists($file . '.php')) {
            require $file . '.php';
        }
        // Try as directory with /class.php
        elseif (file_exists($file . '/class.php')) {
            require $file . '/class.php';
        }
        // Try as directory with index.php
        elseif (file_exists($file . '/index.php')) {
            require $file . '/index.php';
        }
    });
    
    // Load function file if it exists
    if (file_exists(__DIR__ . '/functions.php')) {
        require_once __DIR__ . '/functions.php';
    }
    
    // Load config file if it exists
    if (file_exists(__DIR__ . '/config.php')) {
        require_once __DIR__ . '/config.php';
    }


?>
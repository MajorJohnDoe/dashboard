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
        
        // Other classes are directly in the 'classes' directory
        $file = $base_dir . $file_path . '.class.php';
    
        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
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
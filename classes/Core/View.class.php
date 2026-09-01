<?php
namespace Dashboard\Core;

class View {
    public function render($template, $data = []) {
        error_log("Attempting to render template: $template");
        extract($data);
        
        // Ensure browser receives proper HTML Content-Type header
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        
        $templatePath = $_SERVER['DOCUMENT_ROOT'] . "/views/{$template}.php";
    
    if (!file_exists($templatePath)) {
        if (empty($options['full_page'])) {
            return "Error: Template not found.";
        }
        $templatePath = $_SERVER['DOCUMENT_ROOT'] . "/views/404.php";
        $options['title'] = '404 Not Found';
        $options['full_page'] = false;
    }
    
    ob_start();
    
    if (!empty($options['full_page'])) {
        include $_SERVER['DOCUMENT_ROOT'] . '/views/core/header.php';
    }
    
    include $templatePath;
    
    if (!empty($options['full_page'])) {
        include $_SERVER['DOCUMENT_ROOT'] . '/views/core/footer.php';
    }
    
    return ob_get_clean();
    }
}
?>
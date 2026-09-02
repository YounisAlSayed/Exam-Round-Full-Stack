<?php

namespace App\Utils;

class ViewModel
{
    private $template;
    private $data = [];

    public function __construct($template, $data = [])
    {
        $this->template = $template;
        $this->data = $data;
    }

    public function render()
    {
        // Extract array keys as variables (e.g. ['tests' => $tests] becomes $tests)
        extract($this->data);

        $viewPath = __DIR__ . '/../views/' . $this->template . '.phtml';

        if (file_exists($viewPath)) {
            // Set header to HTML instead of JSON (which might be set globally)
            header('Content-Type: text/html; charset=UTF-8');
            require $viewPath;
        } else {
            echo "View not found: " . htmlspecialchars($this->template);
        }
    }
}
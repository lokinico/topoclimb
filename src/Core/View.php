<?php
// src/Core/View.php

namespace TopoclimbCH\Core;

class View
{
    /**
     * @var string
     */
    private string $viewsPath;
    
    /**
     * @var array
     */
    private array $globalData = [];

    /**
     * Constructor
     *
     * @param string $viewsPath
     */
    public function __construct(string $viewsPath = null)
    {
        $this->viewsPath = $viewsPath ?? BASE_PATH . '/resources/views';
    }

    /**
     * Render a view
     *
     * @param string $view
     * @param array $data
     * @return string
     * @throws \Exception
     */
    public function render(string $view, array $data = []): string
    {
        $viewPath = $this->viewsPath . '/' . $view;
        
        if (!file_exists($viewPath)) {
            throw new \Exception("View file not found: {$viewPath}");
        }
        
        // Combine global data with local data
        $data = array_merge($this->globalData, $data);
        
        // Extract variables for the view
        extract($data);
        
        // Start output buffering
        ob_start();
        
        // Include the view file
        include $viewPath;
        
        // Return the output
        return ob_get_clean();
    }

    /**
     * Add global data available to all views
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function addGlobal(string $key, mixed $value): void
    {
        $this->globalData[$key] = $value;
    }

    /**
     * Add multiple global variables at once
     *
     * @param array $data
     * @return void
     */
    public function addGlobals(array $data): void
    {
        $this->globalData = array_merge($this->globalData, $data);
    }
}
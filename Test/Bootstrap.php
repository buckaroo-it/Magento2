<?php
/**
 * Bootstrap file for unit tests
 * Defines Magento constants and loads autoloader
 */

// Define Magento base path constant if not already defined
if (!defined('BP')) {
    define('BP', dirname(__DIR__, 5)); // Points to Magento root from app/code/Buckaroo/Magento2
}

// Load Magento's autoloader (when running in Magento context)
$magentoAutoloader = BP . '/vendor/autoload.php';
if (file_exists($magentoAutoloader)) {
    require_once $magentoAutoloader;
} else {
    // Fallback to module's vendor (standalone execution)
    require_once __DIR__ . '/../vendor/autoload.php';
}

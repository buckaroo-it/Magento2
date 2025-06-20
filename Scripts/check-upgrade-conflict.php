<?php
/**
 * Buckaroo Upgrade Conflict Checker
 * 
 * This script checks if the separate buckaroo/magento2secondchance package
 * is installed and provides upgrade instructions if found.
 */

// ANSI color codes for terminal output
const RED = "\033[1;31m";
const YELLOW = "\033[1;33m";
const GREEN = "\033[1;32m";
const WHITE = "\033[1;37m";
const GRAY = "\033[0;37m";
const RESET = "\033[0m";

/**
 * Check if SecondChance package is installed
 */
function hasSecondChancePackage(): bool
{
    // Check if vendor directory exists
    if (file_exists('vendor/buckaroo/magento2secondchance/composer.json')) {
        return true;
    }
    
    // Check command line arguments for the package name
    if (isset($GLOBALS['argv']) && in_array('buckaroo/magento2secondchance', $GLOBALS['argv'])) {
        return true;
    }
    
    return false;
}

/**
 * Display the upgrade conflict error message
 */
function displayUpgradeError(): void
{
    $border = str_repeat('━', 47);
    
    echo "\n";
    echo RED . "✗ BUCKAROO UPGRADE CONFLICT DETECTED" . RESET . "\n";
    echo YELLOW . $border . RESET . "\n";
    echo WHITE . "The SecondChance module is now included in Buckaroo v2.0.0+" . RESET . "\n";
    echo GRAY . "You need to remove the separate SecondChance package first:" . RESET . "\n\n";
    echo GREEN . "  composer remove buckaroo/magento2secondchance" . RESET . "\n";
    echo GREEN . "  composer update buckaroo/magento2" . RESET . "\n\n";
    echo YELLOW . $border . RESET . "\n";
}

// Main execution
if (hasSecondChancePackage()) {
    displayUpgradeError();
    exit(1);
}

// If we get here, no conflict was detected
exit(0); 
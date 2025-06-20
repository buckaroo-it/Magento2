<?php
/**
 * Buckaroo Upgrade Conflict Checker
 * Simple version without ANSI colors for maximum compatibility
 */

function hasSecondChancePackage(): bool
{
    return file_exists('vendor/buckaroo/magento2secondchance/composer.json');
}

function displayUpgradeError(): void
{
    $border = str_repeat('=', 55);

    echo "\n";
    echo "ERROR: Buckaroo SecondChance Conflict Detected\n";
    echo $border . "\n";
    echo "The SecondChance module is now included in Buckaroo v2.0.0+\n";
    echo "You need to remove the separate SecondChance package first:\n\n";
    echo "  composer remove buckaroo/magento2secondchance\n";
    echo "  composer update buckaroo/magento2\n\n";
    echo "For more help, see: https://github.com/buckaroo-it/Magento2/blob/refactor/UPGRADE.md\n";
    echo $border . "\n";
}

// Main execution
if (hasSecondChancePackage()) {
    displayUpgradeError();
    exit(1);
}

exit(0);
<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */

// Prevent multiple includes
if (defined('BUCKAROO_TEST_BOOTSTRAP_INCLUDED')) {
    return;
}
define('BUCKAROO_TEST_BOOTSTRAP_INCLUDED', true);

// Try multiple possible paths for Magento's unit test bootstrap
$possiblePaths = [
    __DIR__ . '/../../../../../dev/tests/unit/framework/bootstrap.php',
    __DIR__ . '/../../../../dev/tests/unit/framework/bootstrap.php',
    __DIR__ . '/../../../../../vendor/magento/magento2-base/dev/tests/unit/framework/bootstrap.php',
    __DIR__ . '/../../../../vendor/magento/magento2-base/dev/tests/unit/framework/bootstrap.php'
];

$bootstrapIncluded = false;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $bootstrapIncluded = true;
        break;
    }
}

// If Magento bootstrap not found, create minimal test environment
if (!$bootstrapIncluded) {
    // Set up basic error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Initialize basic Magento constants if not already defined
    if (!defined('BP')) {
        define('BP', __DIR__ . '/../../../../../');
    }

    // Autoloader should already be available via Composer
}

// Initialize ObjectManager for tests that require it
if (!class_exists('Magento\Framework\App\ObjectManager')
    || !method_exists('Magento\Framework\App\ObjectManager', 'getInstance')
) {
    // ObjectManager not available, tests should use mocks instead
} else {
    try {
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        if (!$om) {
            // Create a basic ObjectManager mock for unit tests
            // Don't extend ObjectManager directly to avoid constructor parameter issues
            $objectManagerMock = new class
            {
                private $instances = [];

                public function get($type, array $arguments = [])
                {
                    if (!isset($this->instances[$type])) {
                        $this->instances[$type] = new \stdClass();
                    }
                    return $this->instances[$type];
                }

                public function create($type, array $arguments = [])
                {
                    return new \stdClass();
                }

                public function configure(array $configuration)
                {
                    // No-op for test implementation
                    return $this;
                }
            };

            // Since we can't extend ObjectManager directly, we'll skip setting it
            // Unit tests should properly mock their dependencies instead
        }
    } catch (\Throwable $e) {
        // ObjectManager setup failed, continue without it
        // Unit tests should use proper mocking instead
    }
}

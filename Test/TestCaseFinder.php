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

/**
 * Magento 2.1 and lower uses PHPUnit 4.8, which has PHPUnit_Framework_TestCase has base class. Magento 2.2 and higher
 * have an updated version of PHPUnit, which uses \PHPUnit\Framework\Testcase as base class
 */
if (class_exists('PHPUnit_Framework_TestCase')) {
    require 'TestCaseFinder/PHPUnit4.php';
    return;
}

require 'TestCaseFinder/PHPUnit6.php';

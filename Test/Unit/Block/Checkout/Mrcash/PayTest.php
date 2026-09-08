<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Test\Unit\Block\Checkout\Mrcash;

use Buckaroo\Magento2\Block\Checkout\Mrcash\Pay;
use Buckaroo\Magento2\Test\BaseTest;

class PayTest extends BaseTest
{
    protected $instanceClass = Pay::class;

    /**
     * The full alphanumeric (hex) Buckaroo transaction key must be preserved so the Bancontact
     * QR SDK receives a valid key. Regression guard for the digit-only strip that corrupted it.
     */
    public function testPreservesFullAlphanumericTransactionKey(): void
    {
        $instance = $this->getInstance();
        $this->setProperty('response', ['Key' => '2ECCAAB0B23D44B5BAA7DF8DE62F8AAB'], $instance);

        $this->assertSame('2ECCAAB0B23D44B5BAA7DF8DE62F8AAB', $instance->getTransactionKey());
    }

    public function testStripsNonWordCharacters(): void
    {
        $instance = $this->getInstance();
        $this->setProperty('response', ['Key' => 'ABC-123 xyz!@#'], $instance);

        $this->assertSame('ABC123xyz', $instance->getTransactionKey());
    }

    public function testReturnsEmptyStringWhenKeyMissing(): void
    {
        $instance = $this->getInstance();
        $this->setProperty('response', [], $instance);

        $this->assertSame('', $instance->getTransactionKey());
    }
}

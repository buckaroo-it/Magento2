<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
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

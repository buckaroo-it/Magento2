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

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider\Method;

use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard;
use PHPUnit\Framework\Attributes\DataProvider;

class CreditcardTest extends BaseTest
{
    protected $instanceClass = Creditcard::class;

    public function testGetConfig()
    {
        $issuers = 'amex,visa';
        $allowedCurrencies = 'USD,EUR';

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->getMock();

        // Mock the getValue calls for different config paths
        $scopeConfigMock->method('getValue')->willReturnMap([
            // Make the creditcard method active
            [
                $this->getPaymentMethodConfigPath(Creditcard::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE,
                null,
                1
            ],
            // Set allowed creditcards
            [
                Creditcard::XPATH_CREDITCARD_ALLOWED_CREDITCARDS,
                ScopeInterface::SCOPE_STORE,
                null,
                $issuers
            ],
            // Set allowed currencies
            [
                $this->getPaymentMethodConfigPath(Creditcard::CODE, AbstractConfigProvider::ALLOWED_CURRENCIES),
                ScopeInterface::SCOPE_STORE,
                null,
                $allowedCurrencies
            ]
        ]);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey('buckaroo_magento2_creditcard', $result['payment']['buckaroo']);
        $this->assertArrayHasKey('cards', $result['payment']['buckaroo']['buckaroo_magento2_creditcard']);
        $this->assertIsArray($result['payment']['buckaroo']['buckaroo_magento2_creditcard']['cards']);
    }

    /**
     * Test if the getActive magic method returns the correct value.
     */
    public function testGetActive()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->getMock();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(Creditcard::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn('1');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getActive();

        $this->assertEquals(1, $result);
    }

    /**
     * A known card code resolves to its display name.
     */
    public function testGetCardNameReturnsTheNameForAKnownCode()
    {
        // Arrange
        $instance = $this->getInstance();

        // Act
        $result = $instance->getCardName('visa');

        // Assert
        $this->assertEquals('VISA', $result);
    }

    /**
     * An order that never went through Magento's card form has no card type at all. Returning null
     * keeps the order view, the success page and the invoice PDF rendering; throwing broke all three
     * for PayLink orders paid by card.
     *
     * @param string|null $cardType
     */
    #[DataProvider('unresolvableCardTypeProvider')]
    public function testGetCardNameReturnsNullWhenTheCodeCannotBeResolved($cardType)
    {
        // Arrange
        $instance = $this->getInstance();

        // Act
        $result = $instance->getCardName($cardType);

        // Assert
        $this->assertNull($result);
    }

    /**
     * @return array
     */
    public static function unresolvableCardTypeProvider()
    {
        return [
            'empty string' => [''],
            'null' => [null],
            'unknown brand' => ['notacard'],
        ];
    }

    /**
     * A known card name resolves back to its service code.
     */
    public function testGetCardCodeReturnsTheCodeForAKnownName()
    {
        // Arrange
        $instance = $this->getInstance();

        // Act
        $result = $instance->getCardCode('VISA');

        // Assert
        $this->assertEquals('visa', $result);
    }

    /**
     * The admin template reads getCardCode() straight into a CSS class, outside any guard, so it
     * must not throw either.
     */
    public function testGetCardCodeReturnsNullWhenTheNameCannotBeResolved()
    {
        // Arrange
        $instance = $this->getInstance();

        // Act
        $result = $instance->getCardCode('Not A Card');

        // Assert
        $this->assertNull($result);
    }
}

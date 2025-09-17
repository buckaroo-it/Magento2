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

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider\Method;



use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;

class GiftcardsTest extends BaseTest
{
    protected $instanceClass = Giftcards::class;
    /**
     * Test that the config returns the right values
     */
    public function testGetConfig()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->method('getValue')->willReturnMap([
            // Make the giftcards method active
            [
                $this->getPaymentMethodConfigPath(Giftcards::CODE, 'active'),
                ScopeInterface::SCOPE_STORE,
                null,
                1
            ],
            // Set allowed currencies
            [
                $this->getPaymentMethodConfigPath(Giftcards::CODE, 'allowed_currencies'),
                ScopeInterface::SCOPE_STORE,
                null,
                'EUR'
            ]
        ]);

        // Mock Giftcard Collection
        $giftcardCollectionMock = $this->createMock(\Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection::class);
        $giftcardCollectionMock->method('getIterator')->willReturn(new \ArrayIterator([]));
        
        // Mock Giftcard Collection Factory
        $giftcardCollectionFactoryMock = $this->createMock(\Buckaroo\Magento2\Model\ResourceModel\Giftcard\CollectionFactory::class);
        $giftcardCollectionFactoryMock->method('create')->willReturn($giftcardCollectionMock);
        
        // Mock Store Manager
        $storeMock = $this->createMock(\Magento\Store\Model\Store::class);
        $storeMock->method('getBaseUrl')->willReturn('https://example.com/media/');
        $storeManagerMock = $this->createMock(\Magento\Store\Model\StoreManagerInterface::class);
        $storeManagerMock->method('getStore')->willReturn($storeMock);
        
        // Mock Giftcards Source
        $giftcardsSourceMock = $this->createMock(\Buckaroo\Magento2\Model\Config\Source\Giftcards::class);
        $giftcardsSourceMock->method('toOptionArray')->willReturn([]);

        $instance = $this->getInstance([
            'scopeConfig' => $scopeConfigMock,
            'storeManager' => $storeManagerMock,
            'giftcardCollectionFactory' => $giftcardCollectionFactoryMock,
            'giftcardsSource' => $giftcardsSourceMock
        ]);
        $result = $instance->getConfig();

        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey('buckaroo_magento2_giftcards', $result['payment']['buckaroo']);
        $this->assertArrayHasKey('paymentFeeLabel', $result['payment']['buckaroo']['buckaroo_magento2_giftcards']);
        $this->assertArrayHasKey('allowedCurrencies', $result['payment']['buckaroo']['buckaroo_magento2_giftcards']);
    }

    /**
     * Check that the payment fee is return as a false boolean when we have a false-ish value.
     */
    public function testGetPaymentFee()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->onlyMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->method('getValue')
            ->with('payment/buckaroo_magento2_giftcards/active', ScopeInterface::SCOPE_STORE)
            ->willReturn(false);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertEmpty($result);
    }
}

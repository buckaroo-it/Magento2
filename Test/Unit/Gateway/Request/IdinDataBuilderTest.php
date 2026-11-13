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
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request;

use Buckaroo\Magento2\Gateway\Request\IdinDataBuilder;
use Magento\Customer\Model\Session as CustomerSession;

/**
 * The verification request carries a fresh value that is also kept in the session, so the result
 * Buckaroo returns can be tied to the session that asked for it.
 */
class IdinDataBuilderTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = IdinDataBuilder::class;

    public function testTheRequestCarriesTheValueKeptInTheSession(): void
    {
        $stored = [];

        $customerSession = $this->getMockBuilder(CustomerSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCustomerId', '__call'])
            ->getMock();
        $customerSession->method('getCustomerId')->willReturn(5);
        $customerSession->method('__call')->willReturnCallback(
            function ($method, $args) use (&$stored, $customerSession) {
                $stored[] = [$method, $args];
                return $customerSession;
            }
        );

        $store = $this->getFakeMock(\Magento\Store\Model\Store::class)->onlyMethods(['getId'])->getMock();
        $store->method('getId')->willReturn(1);
        $storeManager = $this->getFakeMock(\Magento\Store\Model\StoreManagerInterface::class)->getMock();
        $storeManager->method('getStore')->willReturn($store);
        $urlBuilder = $this->getFakeMock(\Magento\Framework\UrlInterface::class)->getMock();
        $urlBuilder->method('getRouteUrl')->willReturn('https://shop.test/buckaroo/redirect/idinProcess');

        $formKey = $this->getFakeMock(\Magento\Framework\Data\Form\FormKey::class)->onlyMethods(['getFormKey'])->getMock();
        $formKey->method('getFormKey')->willReturn('formkey123');

        $instance = $this->getInstance([
            'formKey' => $formKey,
            'customerSession' => $customerSession,
            'storeManager' => $storeManager,
            'urlBuilder' => $urlBuilder,
        ]);

        $first = $instance->build(['issuer' => 'BANKNL2Y'])['additionalParameters'];
        $second = $instance->build(['issuer' => 'BANKNL2Y'])['additionalParameters'];

        $this->assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', $first['idin_nonce']);
        $this->assertNotSame($first['idin_nonce'], $second['idin_nonce'], 'every verification gets its own value');
        $this->assertSame(
            [
                ['setBuckarooIdinNonce', [$first['idin_nonce']]],
                ['setBuckarooIdinNonce', [$second['idin_nonce']]],
            ],
            $stored
        );
        $this->assertSame(5, $first['idin_cid']);
    }
}

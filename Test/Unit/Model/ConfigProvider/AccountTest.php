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

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider;

use Magento\Sales\Model\Order;
use Magento\Store\Model\Store;
use Magento\Sales\Model\Order\Item;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\ConfigProvider\Account;

class AccountTest extends BaseTest
{
    protected $instanceClass = Account::class;

    /**
     * Test the getConfig function.
     */
    public function testGetConfig()
    {
        $expectedKeys = [
            'active', 'secret_key', 'merchant_key', 'transaction_label',
            'order_confirmation_email', 'order_confirmation_email_sync', 'invoice_email', 'success_redirect', 'failure_redirect', 'failure_redirect_to_checkout', 'cancel_on_failed',
            'debug_types', 'log_handler', 'log_retention', 'payment_fee_label', 'order_status_new',
            'order_status_pending', 'order_status_success', 'order_status_failed', 'create_order_before_transaction',
            'ip_header', 'cart_keep_alive', 'buckaroo_fee_tax_class', 'customer_additional_info',
            'idin', 'idin_mode', 'idin_category'
        ];

        $instance = $this->getInstance();
        $result = $instance->getConfig();

        $this->assertIsArray($result);

        $resultKeys = array_keys($result);
        $this->assertEmpty(array_merge(array_diff($expectedKeys, $resultKeys), array_diff($resultKeys, $expectedKeys)));
    }
    public function testParsedLabelAll()
    {
        $orderNumber = '000000099';
        $productName = 'Product name';
        $shopName = 'Shop Name';

        $productMock = $this->getFakeMock(Item::class)
        ->onlyMethods(['getName'])
        ->getMock();

        $productMock->method('getName')->willReturn($productName);

        $products = [
            $productMock
        ];

        $orderMock = $this->getFakeMock(Order::class)
            ->onlyMethods(['getIncrementId', 'getItems'])
            ->getMock();
        $orderMock->method('getIncrementId')->willReturn($orderNumber);
        $orderMock->method('getItems')->willReturn($products);

        $storeMock = $this->getFakeMock(Store::class)
        ->onlyMethods(['getName'])
        ->getMock();

        $storeMock->method('getName')->willReturn($shopName);

        $account = $this->getFakeMock(Account::class)
        ->onlyMethods(['getTransactionLabel'])
        ->getMock();

        $account->method('getTransactionLabel')
        ->with($storeMock)
        ->willReturn(
            'Order {order_number} shop: {shop_name} product: {product_name}'
        );
        $this->assertEquals("Order {$orderNumber} shop: {$orderNumber} product: {$productName}", $account->getParsedLabel($storeMock, $orderMock));
    }
}

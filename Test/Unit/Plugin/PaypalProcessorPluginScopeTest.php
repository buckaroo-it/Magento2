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

namespace Buckaroo\Magento2\Test\Unit\Plugin;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal;
use Buckaroo\Magento2\Plugin\PaypalProcessorPlugin;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Finding #12. Seller Protection statuses are configurable per store view, and this runs inside
 * the push - which does not sit in the order's store.
 */
class PaypalProcessorPluginScopeTest extends TestCase
{
    #[DataProvider('eligibilityProvider')]
    public function testEligibilityStatusIsReadInTheOrderStore(string $type, string $method): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn('2');
        $order->expects($this->once())->method('addCommentToStatusHistory');

        $config = $this->createMock(Paypal::class);
        $config->expects($this->once())->method($method)->with(2)->willReturn('holded');

        $plugin = $this->getMockBuilder(PaypalProcessorPlugin::class)
            ->setConstructorArgs([$config])
            ->onlyMethods([])
            ->getMock();

        $handle = new \ReflectionMethod(PaypalProcessorPlugin::class, 'handleEligibilityType');
        $handle->setAccessible(true);
        $handle->invoke($plugin, $type, $order);
    }

    public static function eligibilityProvider(): array
    {
        return [
            'eligible'             => [PaypalProcessorPlugin::ELIGIBILITY_TYPE_ELIGIBLE, 'getSellersProtectionEligible'],
            'item not received'    => [PaypalProcessorPlugin::ELIGIBILITY_TYPE_ITEM_NOT_RECEIVED, 'getSellersProtectionItemnotreceivedEligible'],
            'unauthorized payment' => [PaypalProcessorPlugin::ELIGIBILITY_TYPE_UNAUTHORIZED_PAYMENT, 'getSellersProtectionUnauthorizedpaymentEligible'],
            'none'                 => [PaypalProcessorPlugin::ELIGIBILITY_TYPE_NONE, 'getSellersProtectionIneligible'],
        ];
    }
}

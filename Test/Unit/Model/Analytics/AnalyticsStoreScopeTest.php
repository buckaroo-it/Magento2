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
declare(strict_types=1);
namespace Buckaroo\Magento2\Test\Unit\Model\Analytics;

use Buckaroo\Magento2\Model\Analytics\ConfigProvider\Analytics;
use Buckaroo\Magento2\Plugin\AbstractTransactionBuilderAfterGetUrl;
use Buckaroo\Magento2\Service\CookieParamService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;

/**
 * Client-id tracking and its cookie/param pairs are set per store view, so they are read for the
 * quote's or order's store when one is in hand.
 */
class AnalyticsStoreScopeTest extends TestCase
{
    public function testSettingsAreReadForTheGivenStore(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            ['buckaroo_magento2/analytics/enable_ga_client_id_tracking', ScopeInterface::SCOPE_STORE, 2, '1'],
            ['buckaroo_magento2/analytics/cookie_param_pair', ScopeInterface::SCOPE_STORE, 2, [['cookie' => '_ga']]],
        ]);

        $config = new Analytics($scopeConfig, $this->createMock(Json::class));

        $this->assertTrue($config->isClientIdTrackingEnabled(2));
        $this->assertSame([['cookie' => '_ga']], $config->getCookieParamPairs(2));
    }

    public function testReturnUrlPluginUsesTheOrdersStore(): void
    {
        $service = $this->createMock(CookieParamService::class);
        $service->expects($this->once())->method('getUrlParamsByCookies')->with(4)->willReturn('cid=1');

        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(4);

        $plugin = new AbstractTransactionBuilderAfterGetUrl($service);

        $this->assertSame(
            'https://shop.test/buckaroo/redirect/process?cid=1',
            $plugin->afterGetReturnUrl(null, 'https://shop.test/buckaroo/redirect/process', $order)
        );
    }
}

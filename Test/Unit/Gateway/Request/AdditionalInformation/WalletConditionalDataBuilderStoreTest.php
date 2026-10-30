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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\AdditionalInformation;

use Buckaroo\Magento2\Gateway\Request\AdditionalInformation\ApplepayConditionalDataBuilder;
use Buckaroo\Magento2\Gateway\Request\AdditionalInformation\ApplepayDataBuilder;
use Buckaroo\Magento2\Gateway\Request\AdditionalInformation\ApplepayRedirectDataBuilder;
use Buckaroo\Magento2\Gateway\Request\AdditionalInformation\GooglepayConditionalDataBuilder;
use Buckaroo\Magento2\Gateway\Request\AdditionalInformation\GooglepayDataBuilder;
use Buckaroo\Magento2\Gateway\Request\AdditionalInformation\GooglepayRedirectDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Googlepay;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Inline vs redirect is chosen from the integration mode of the order's store, not the ambient
 * one: integration mode is set per store view and admin or REST orders do not run in their store.
 */
class WalletConditionalDataBuilderStoreTest extends TestCase
{
    private function subject(int $storeId): array
    {
        $order = $this->createMock(OrderAdapterInterface::class);
        $order->method('getStoreId')->willReturn($storeId);
        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getOrder')->willReturn($order);

        return ['payment' => $paymentDO];
    }

    public function testApplepayUsesTheOrdersStoreIntegrationMode(): void
    {
        $config = $this->createMock(Applepay::class);
        $config->expects($this->once())->method('getIntegrationMode')->with(2)->willReturn('1');
        $inline = $this->createMock(ApplepayDataBuilder::class);
        $inline->expects($this->never())->method('build');
        $redirect = $this->createMock(ApplepayRedirectDataBuilder::class);
        $redirect->expects($this->once())->method('build')->willReturn(['redirect' => true]);

        $builder = new ApplepayConditionalDataBuilder($config, $inline, $redirect);

        $this->assertSame(['redirect' => true], $builder->build($this->subject(2)));
    }

    public function testGooglepayUsesTheOrdersStoreIntegrationMode(): void
    {
        $config = $this->createMock(Googlepay::class);
        $config->expects($this->once())->method('getIntegrationMode')->with(2)->willReturn('0');
        $inline = $this->createMock(GooglepayDataBuilder::class);
        $inline->expects($this->once())->method('build')->willReturn(['inline' => true]);
        $redirect = $this->createMock(GooglepayRedirectDataBuilder::class);
        $redirect->expects($this->never())->method('build');

        $builder = new GooglepayConditionalDataBuilder($config, $inline, $redirect, $this->createMock(LoggerInterface::class));

        $this->assertSame(['inline' => true], $builder->build($this->subject(2)));
    }
}

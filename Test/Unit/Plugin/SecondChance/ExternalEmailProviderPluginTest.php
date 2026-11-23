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


namespace Buckaroo\Magento2\Test\Unit\Plugin\SecondChance;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\ExternalEmailProvider as ExternalEmailConfig;
use Buckaroo\Magento2\Model\ConfigProvider\SecondChance as SecondChanceConfig;
use Buckaroo\Magento2\Model\SecondChanceRepository;
use Buckaroo\Magento2\Plugin\SecondChance\ExternalEmailProviderPlugin;
use Buckaroo\Magento2\Service\EmailProvider\EmailSender;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\FactoryInterface as TemplateFactory;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address\Renderer as AddressRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * "Fallback to Magento Mail" is respected both when the provider throws and when it reports a failure.
 */
class ExternalEmailProviderPluginTest extends TestCase
{
    /** @var ExternalEmailConfig|MockObject */
    private $config;

    /** @var EmailSender|MockObject */
    private $sender;

    /** @var ExternalEmailProviderPlugin|MockObject */
    private $plugin;

    /** @var Order|MockObject */
    private $order;

    /** @var int */
    private $proceedCalls = 0;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ExternalEmailConfig::class);
        $this->config->method('getProviderName')->willReturn('Provider');
        $this->sender = $this->createMock(EmailSender::class);

        $this->plugin = $this->getMockBuilder(ExternalEmailProviderPlugin::class)
            ->setConstructorArgs([
                $this->config,
                $this->createMock(SecondChanceConfig::class),
                $this->sender,
                $this->createMock(Log::class),
                $this->createMock(TransportBuilder::class),
                $this->createMock(TemplateFactory::class),
                $this->createMock(AddressRenderer::class),
                $this->createMock(PaymentHelper::class),
            ])
            ->onlyMethods(['prepareEmailData'])
            ->getMock();
        $this->plugin->method('prepareEmailData')->willReturn(['to' => 'shopper@example.com']);

        $this->order = $this->createMock(Order::class);
        $this->order->method('getStoreId')->willReturn(3);
        $this->order->method('getIncrementId')->willReturn('000000001');
        $this->proceedCalls = 0;
    }

    private function send(): void
    {
        $proceed = function () {
            $this->proceedCalls++;
        };
        $this->plugin->aroundSendMail(
            $this->createMock(SecondChanceRepository::class),
            $proceed,
            $this->order,
            new \stdClass(),
            1
        );
    }

    public function testProviderDisabledUsesMagentoMail(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->sender->expects($this->never())->method('send');

        $this->send();

        $this->assertSame(1, $this->proceedCalls);
    }

    public function testProviderSuccessSkipsMagentoMail(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->sender->method('send')->willReturn(['success' => true]);

        $this->send();

        $this->assertSame(0, $this->proceedCalls);
    }

    public function testProviderFailureWithFallbackUsesMagentoMail(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isFallbackEnabled')->willReturn(true);
        $this->sender->method('send')->willReturn(['success' => false, 'error' => 'rejected']);

        $this->send();

        $this->assertSame(1, $this->proceedCalls);
    }

    public function testProviderFailureWithoutFallbackDoesNotUseMagentoMail(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isFallbackEnabled')->with(3)->willReturn(false);
        $this->sender->method('send')->willReturn(['success' => false, 'error' => 'rejected']);

        try {
            $this->send();
            $this->fail('Expected the failure to be reported');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('fallback is disabled: rejected', $e->getMessage());
        }

        $this->assertSame(0, $this->proceedCalls);
    }

    public function testProviderExceptionWithoutFallbackIsRethrown(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isFallbackEnabled')->willReturn(false);
        $this->sender->method('send')->willThrowException(new \RuntimeException('down'));

        $this->expectException(\RuntimeException::class);

        try {
            $this->send();
        } finally {
            $this->assertSame(0, $this->proceedCalls);
        }
    }
}

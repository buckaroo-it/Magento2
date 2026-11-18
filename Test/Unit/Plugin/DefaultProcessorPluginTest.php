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

use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard;
use Buckaroo\Magento2\Model\Push\DefaultProcessor;
use Buckaroo\Magento2\Plugin\DefaultProcessorPlugin;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * "Set unsecure orders on hold" per card brand, after a successful push.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.UnusedLocalVariable) false positive on the anonymous push request's __call()
 * @SuppressWarnings(PHPMD.UnusedFormalParameter) __call() must accept the arguments it does not use
 */
class DefaultProcessorPluginTest extends TestCase
{
    public static function holdProvider(): array
    {
        return [
            'visa, not 3-D Secure, hold on'        => ['visa', 'U', true, true],
            'mastercard, not enrolled, hold on'    => ['mastercard', 'N', true, true],
            'dankort, not 3-D Secure, hold on'     => ['dankort', 'U', true, true],
            'visa, 3-D Secure passed, hold on'     => ['visa', 'Y', true, false],
            'visa, not 3-D Secure, hold off'       => ['visa', 'U', false, false],
            'order that cannot be held'            => ['visa', 'U', true, false, false],
        ];
    }

    #[DataProvider('holdProvider')]
    public function testUnsecureOrdersAreHeldWhenTheCardSettingSaysSo(
        string $card,
        string $authentication,
        bool $holdSetting,
        bool $expectHold,
        bool $canHold = true
    ): void {
        $config = $this->createMock(Creditcard::class);
        foreach (['getVisaUnsecureHold', 'getMastercardUnsecureHold', 'getMaestroUnsecureHold', 'getDankortUnsecureHold']
            as $getter) {
            $config->method($getter)->willReturn($holdSetting);
        }

        $order = $this->createOrder($card);
        $order->method('canHold')->willReturn($canHold);
        $order->expects($expectHold ? $this->once() : $this->never())->method('hold')->willReturnSelf();
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $repository->expects($expectHold ? $this->once() : $this->never())->method('save');

        $subject = $this->createProcessor($order, $this->createPushRequest($card, $authentication));

        $result = (new DefaultProcessorPlugin($config, $repository))->afterProcessSucceededPush($subject, true);

        $this->assertTrue($result);
    }

    /**
     * Answers its getters only through __call(), like the real push request classes.
     */
    private function createPushRequest(string $card, string $authentication): object
    {
        return new class ($card, $authentication) {
            private array $values;

            public function __construct(string $card, string $authentication)
            {
                $this->values = [
                    'getService' . ucfirst($card) . 'Authentication' => $authentication,
                    'getService' . ucfirst($card) . 'Enrolled'       => 'Y',
                ];
            }

            public function __call(string $name, array $arguments)
            {
                return $this->values[$name] ?? null;
            }
        };
    }

    private function createOrder(string $card): Order
    {
        $infoInstance = $this->createMock(Payment::class);
        $infoInstance->method('getAdditionalInformation')->willReturnCallback(
            static fn($key = null) => $key === 'card_type' ? $card : null
        );
        $method = $this->createMock(\Buckaroo\Magento2\Model\Method\BuckarooAdapter::class);
        $method->method('getInfoInstance')->willReturn($infoInstance);

        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn('buckaroo_magento2_creditcard');
        $payment->method('getMethodInstance')->willReturn($method);

        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getStoreId')->willReturn(1);
        $order->method('addCommentToStatusHistory')->willReturnSelf();

        return $order;
    }

    private function createProcessor(Order $order, object $pushRequest): DefaultProcessor
    {
        $processor = (new \ReflectionClass(DefaultProcessor::class))->newInstanceWithoutConstructor();
        foreach (['order' => $order, 'pushRequest' => $pushRequest] as $name => $value) {
            $property = new \ReflectionProperty(DefaultProcessor::class, $name);
            $property->setAccessible(true);
            $property->setValue($processor, $value);
        }

        return $processor;
    }
}

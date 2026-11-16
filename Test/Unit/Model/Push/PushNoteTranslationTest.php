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

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\Push\CreditManagmentProcessor;
use Buckaroo\Magento2\Model\Push\DefaultProcessor;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\RendererInterface;
use Magento\Sales\Model\Order;

/**
 * Push notes are saved as text in the order's store view language, so every part must be translated.
 */
class PushNoteTranslationTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = DefaultProcessor::class;

    private const GERMAN = [
        'Payment Push Status: %1'    => 'Push-Zahlungsstatus: %1',
        'Waiting for processor'      => 'Warten auf Verarbeitung',
        'Waiting on consumer action' => 'Warten auf den Kunden',
        'Unknown response code: %1'  => 'Unbekannter Antwortcode: %1',
        'Success'                    => 'Erfolgreich',
        'Total amount of %1 has been paid' => 'Gesamtbetrag von %1 wurde bezahlt',
    ];

    /**
     * @var RendererInterface|null
     */
    private $previousRenderer;

    public function setUp(): void
    {
        parent::setUp();

        $this->previousRenderer = Phrase::getRenderer();
        $renderer = $this->createMock(RendererInterface::class);
        $renderer->method('render')->willReturnCallback([$this, 'renderGerman']);
        Phrase::setRenderer($renderer);
    }

    /**
     * Translate with the small German dictionary and fill the placeholders, like Magento's renderers.
     *
     * @param array $source
     * @param array $arguments
     *
     * @return string
     */
    public function renderGerman(array $source, array $arguments): string
    {
        $text = self::GERMAN[end($source)] ?? end($source);
        foreach (array_values($arguments) as $index => $argument) {
            $text = str_replace('%' . ($index + 1), (string)$argument, $text);
        }

        return $text;
    }

    public function tearDown(): void
    {
        Phrase::setRenderer($this->previousRenderer);
        parent::tearDown();
    }

    public function testPendingPushNoteIsTranslatedIncludingThePrefix(): void
    {
        $instance = $this->getFakeMock(DefaultProcessor::class)->onlyMethods(['getTransferDetails'])->getMock();
        $instance->method('getTransferDetails')->willReturn([]);

        $note = $this->invokeArgs('buildPendingPaymentDescription', ['Waiting for processor'], $instance);

        $this->assertSame('Push-Zahlungsstatus: Warten auf Verarbeitung', (string)$note);
    }

    public function testSuccessPushNoteIsTranslated(): void
    {
        $instance = $this->getFakeMock(DefaultProcessor::class)
            ->onlyMethods(['canPushInvoice', 'formatCommentAmount'])
            ->getMock();
        $instance->method('canPushInvoice')->willReturn(true);
        $instance->method('formatCommentAmount')->willReturn('€29.04');

        $payment = $this->getFakeMock(\Magento\Sales\Model\Order\Payment::class)
            ->onlyMethods(['getAdditionalInformation'])
            ->getMock();
        $payment->method('getAdditionalInformation')->willReturn(
            \Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions::PAYMENT
        );

        $order = $this->getFakeMock(Order::class)
            ->onlyMethods(['getTotalDue', 'getOrderCurrencyCode', 'getBaseTotalDue', 'getBaseCurrencyCode', 'getPayment'])
            ->getMock();
        $order->method('getPayment')->willReturn($payment);
        $order->method('getBaseTotalDue')->willReturn(29.04);
        $order->method('getBaseCurrencyCode')->willReturn('EUR');

        $pushRequest = $this->getFakeMock(PushRequestInterface::class)->getMockForAbstractClass();

        $this->setProperty('order', $order, $instance);
        $this->setProperty('pushRequest', $pushRequest, $instance);

        $details = $this->invokeArgs('getPaymentDetails', ['Success'], $instance);

        $this->assertSame(
            'Push-Zahlungsstatus: <strong>Erfolgreich</strong><br/>Gesamtbetrag von €29.04 wurde bezahlt',
            (string)$details['description']
        );
    }

    public function testCreditManagementWaitingNoteIsTranslated(): void
    {
        $instance = $this->getFakeMock(CreditManagmentProcessor::class)
            ->onlyMethods(['formatCommentAmount'])
            ->getMock();
        $instance->method('formatCommentAmount')->willReturn('€10.00');

        $pushRequest = $this->getFakeMock(PushRequestInterface::class)
            ->addMethods(['getIspaid'])
            ->getMockForAbstractClass();
        $pushRequest->method('getIspaid')->willReturn('false');
        $pushRequest->method('getAmountDebit')->willReturn('10.00');

        $order = $this->getFakeMock(Order::class)
            ->onlyMethods(['canInvoice', 'hasInvoices', 'getState', 'getStatus'])
            ->getMock();
        $order->method('canInvoice')->willReturn(true);
        $order->method('hasInvoices')->willReturn(false);
        $order->method('getState')->willReturn(Order::STATE_PROCESSING);
        $order->method('getStatus')->willReturn('processing');

        $saved = null;
        $orderRequestService = $this->getFakeMock(OrderRequestService::class)
            ->onlyMethods(['updateOrderStatus'])
            ->getMock();
        $orderRequestService->method('updateOrderStatus')->willReturnCallback(
            function (...$args) use (&$saved) {
                $saved = (string)$args[2];
            }
        );

        $this->setProperty('pushRequest', $pushRequest, $instance);
        $this->setProperty('order', $order, $instance);
        $this->setProperty('orderRequestService', $orderRequestService, $instance);

        $this->invoke('updateCm3InvoiceStatus', $instance);

        $this->assertSame('Push-Zahlungsstatus: Warten auf den Kunden', $saved);
    }

    public function testUnknownStatusCodeFallbackIsTranslatable(): void
    {
        $this->assertSame('Unbekannter Antwortcode: 999', (new BuckarooStatusCode())->getResponseMessage(999));
    }
}

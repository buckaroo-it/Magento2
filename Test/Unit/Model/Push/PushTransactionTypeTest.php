<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\Push\PushTransactionType;
use Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub;
use PHPUnit\Framework\Attributes\DataProvider;

class PushTransactionTypeTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\PushTransactionType';

    public function getInstance(array $args = [])
    {
        // Use the real status code map so key/message derivation is actually computed.
        return parent::getInstance($args + ['buckarooStatusCode' => new BuckarooStatusCode()]);
    }

    /**
     * Build a push request double. Keys of $data are getter names, values their returns.
     * The 'additionalInformation' key is a name => value map for getAdditionalInformation().
     */
    private function createPushRequest(array $data = [])
    {
        $defaults = [
            'getTransactionMethod'                     => null,
            'getPrimaryService'                        => null,
            'getStatusCode'                            => null,
            'getTransactionType'                       => null,
            'getDatarequest'                           => null,
            'getInvoicekey'                            => null,
            'getSchemekey'                             => null,
            'getServiceCreditmanagement3Invoicekey'    => null,
            'getEventparametersStatuscode'             => null,
            'getEventparametersTransactionstatuscode'  => null,
            'getAmountCredit'                          => null,
        ];

        $additionalInformation = $data['additionalInformation'] ?? [];
        unset($data['additionalInformation']);

        $mock = $this->getFakeMock(PushRequestInterfaceStub::class)->getMock();
        foreach (array_merge($defaults, $data) as $method => $value) {
            $mock->method($method)->willReturn($value);
        }
        $mock->method('getAdditionalInformation')->willReturnCallback(
            function (string $name) use ($additionalInformation) {
                return $additionalInformation[$name] ?? null;
            }
        );

        return $mock;
    }

    private function createOrder(string $savedCm3InvoiceKey = '', bool $isCanceled = false)
    {
        $paymentMock = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $paymentMock->method('getAdditionalInformation')
            ->with('buckaroo_cm3_invoice_key')
            ->willReturn($savedCm3InvoiceKey);

        $orderMock = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('isCanceled')->willReturn($isCanceled);

        return $orderMock;
    }

    public function testDerivesTransactionPushWithSuccessStatus(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            'getTransactionMethod' => 'ideal',
            'getStatusCode'        => '190',
            'getTransactionType'   => 'C021',
        ]);

        $instance->getPushTransactionType($pushRequest, $this->createOrder());

        $this->assertSame(PushTransactionType::BUCK_PUSH_TYPE_TRANSACTION, $instance->getPushType());
        $this->assertSame(190, $instance->getStatusCode());
        $this->assertSame('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS', $instance->getStatusKey());
        $this->assertSame('Success', $instance->getStatusMessage());
        $this->assertSame('ideal', $instance->getPaymentMethod());
        $this->assertFalse($instance->isGroupTransaction());
        $this->assertFalse($instance->isCreditManagment());
        $this->assertFalse($instance->isFromPayPerEmail());
        $this->assertNull($instance->getServiceAction());
    }

    public function testUsesPrimaryServiceWhenTransactionMethodIsMissing(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            'getTransactionMethod' => null,
            'getPrimaryService'    => 'KlarnaKp',
            'getStatusCode'        => '190',
        ]);

        $instance->getPushTransactionType($pushRequest, $this->createOrder());

        $this->assertSame('KlarnaKp', $instance->getPaymentMethod());
    }

    public function testDerivesInvoicePushWhenCm3InvoiceKeyIsSaved(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            'getInvoicekey'                => 'INVOICE-KEY',
            'getSchemekey'                 => 'SCHEME-KEY',
            'getEventparametersStatuscode' => '791',
        ]);

        $instance->getPushTransactionType($pushRequest, $this->createOrder('SAVED-CM3-KEY'));

        $this->assertSame(PushTransactionType::BUCK_PUSH_TYPE_INVOICE, $instance->getPushType());
        $this->assertTrue($instance->isCreditManagment());
        $this->assertSame(791, $instance->getStatusCode());
        $this->assertSame('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING', $instance->getStatusKey());
        $this->assertSame('Waiting for processor', $instance->getStatusMessage());
    }

    public function testEventTransactionStatusCodeOverridesEventStatusCodeForInvoicePush(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            'getInvoicekey'                           => 'INVOICE-KEY',
            'getSchemekey'                            => 'SCHEME-KEY',
            'getEventparametersStatuscode'            => '791',
            'getEventparametersTransactionstatuscode' => '190',
        ]);

        $instance->getPushTransactionType($pushRequest, $this->createOrder('SAVED-CM3-KEY'));

        $this->assertSame(190, $instance->getStatusCode());
        $this->assertSame('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS', $instance->getStatusKey());
    }

    public function testDerivesIncompleteInvoicePushWhenNoCm3InvoiceKeySaved(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            'getInvoicekey' => 'INVOICE-KEY',
            'getSchemekey'  => 'SCHEME-KEY',
        ]);

        $instance->getPushTransactionType($pushRequest, $this->createOrder(''));

        $this->assertSame(PushTransactionType::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE, $instance->getPushType());
        $this->assertFalse($instance->isCreditManagment());
    }

    public function testDerivesDatarequestPush(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            'getDatarequest' => 'DATAREQUEST-KEY',
            'getStatusCode'  => '890',
        ]);

        $instance->getPushTransactionType($pushRequest, $this->createOrder());

        $this->assertSame(PushTransactionType::BUCK_PUSH_TYPE_DATAREQUEST, $instance->getPushType());
        $this->assertSame(890, $instance->getStatusCode());
        $this->assertSame('BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER', $instance->getStatusKey());
        $this->assertSame('Cancelled by consumer', $instance->getStatusMessage());
    }

    public function testUnmatchedPushTypeStillFallsBackToSuccessStatusCode(): void
    {
        $instance = $this->getInstance();

        // No invoice key, no datarequest, but an order that already has a cm3 invoice key:
        // none of the push type branches match, so the push type is empty.
        $pushRequest = $this->createPushRequest([
            'getStatusCode' => '190',
        ]);

        $instance->getPushTransactionType($pushRequest, $this->createOrder('SAVED-CM3-KEY'));

        $this->assertSame('', $instance->getPushType());
        $this->assertSame(190, $instance->getStatusCode());
        $this->assertSame('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS', $instance->getStatusKey());
    }

    public function testUnknownStatusCodeResolvesToNeutralKey(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            'getStatusCode' => '12345',
        ]);

        $instance->getPushTransactionType($pushRequest, $this->createOrder());

        $this->assertSame(12345, $instance->getStatusCode());
        $this->assertSame('BUCKAROO_MAGENTO2_STATUSCODE_NEUTRAL', $instance->getStatusKey());
        $this->assertSame('Onbekende responsecode: 12345', $instance->getStatusMessage());
    }

    public function testGroupTransactionIsDetectedFromI150TransactionType(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            'getStatusCode'      => '190',
            'getTransactionType' => PushTransactionType::BUCK_PUSH_GROUPTRANSACTION_TYPE,
        ]);

        $instance->getPushTransactionType($pushRequest, $this->createOrder());

        $this->assertTrue($instance->isGroupTransaction());
    }

    public function testServiceActionComesFromMagentoAdditionalInformation(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            'getStatusCode'         => '190',
            'additionalInformation' => ['service_action_from_magento' => 'capture'],
        ]);

        $instance->getPushTransactionType($pushRequest, $this->createOrder());

        $this->assertSame('capture', $instance->getServiceAction());
    }

    public function testServiceActionBecomesRefundForCreditAmountOnSuccessfulPush(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            'getStatusCode'   => '190',
            'getAmountCredit' => '10.00',
        ]);

        $instance->getPushTransactionType($pushRequest, $this->createOrder());

        $this->assertSame('refund', $instance->getServiceAction());
    }

    public function testServiceActionBecomesCancelAuthorizeForCanceledAuthorization(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            'getStatusCode'      => '890',
            'getAmountCredit'    => '10.00',
            'getTransactionType' => PushTransactionType::BUCK_PUSH_CANCEL_AUTHORIZE_TYPE,
        ]);

        $instance->getPushTransactionType($pushRequest, $this->createOrder('', true));

        $this->assertSame('cancel_authorize', $instance->getServiceAction());
    }

    public function testServiceActionStaysRefundWhenOrderIsNotCanceled(): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            'getStatusCode'      => '890',
            'getAmountCredit'    => '10.00',
            'getTransactionType' => PushTransactionType::BUCK_PUSH_CANCEL_AUTHORIZE_TYPE,
        ]);

        $instance->getPushTransactionType($pushRequest, $this->createOrder('', false));

        $this->assertSame('refund', $instance->getServiceAction());
    }

    public static function payPerEmailOriginProvider(): array
    {
        return [
            'frompayperemail additional information flag' => [
                ['frompayperemail' => '1'],
                true,
            ],
            'service action frompayperemail' => [
                ['service_action_from_magento' => 'frompayperemail'],
                true,
            ],
            'service action frompaylink' => [
                ['service_action_from_magento' => 'frompaylink'],
                true,
            ],
            'regular push is not from payperemail' => [
                [],
                false,
            ],
        ];
    }

    #[DataProvider('payPerEmailOriginProvider')]
    public function testIsFromPayPerEmailDetection(array $additionalInformation, bool $expected): void
    {
        $instance = $this->getInstance();

        $pushRequest = $this->createPushRequest([
            'getStatusCode'         => '190',
            'additionalInformation' => $additionalInformation,
        ]);

        $instance->getPushTransactionType($pushRequest, $this->createOrder());

        $this->assertSame($expected, $instance->isFromPayPerEmail());
    }

    public function testStateIsOnlyInitializedOnce(): void
    {
        $instance = $this->getInstance();

        $firstPushRequest = $this->createPushRequest([
            'getTransactionMethod' => 'ideal',
            'getStatusCode'        => '190',
        ]);
        $secondPushRequest = $this->createPushRequest([
            'getTransactionMethod' => 'klarnakp',
            'getStatusCode'        => '490',
        ]);

        $instance->getPushTransactionType($firstPushRequest, $this->createOrder());
        $instance->getPushTransactionType($secondPushRequest, $this->createOrder());

        $this->assertSame('ideal', $instance->getPaymentMethod());
        $this->assertSame(190, $instance->getStatusCode());
    }
}

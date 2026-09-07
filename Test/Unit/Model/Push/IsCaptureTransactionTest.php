<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A capture must be identified by the action Magento itself initiated, never by the gateway's
 * transaction_type (those codes are per card brand: C800 visa, C805 mastercard, C811 maestro)
 * and never by mutationtype=Collecting (present on every money-in push, pay pushes included).
 */
class IsCaptureTransactionTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\DefaultProcessor';

    private $buckarooStatusCodeMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->buckarooStatusCodeMock = $this->getFakeMock(BuckarooStatusCode::class)->getMock();
    }

    public function getInstance(array $args = [])
    {
        return parent::getInstance(array_merge([
            'buckarooStatusCode' => $this->buckarooStatusCodeMock,
        ], $args));
    }

    /**
     * Payloads mirror real pushes captured in var/log/Buckaroo.
     *
     * @return array<string, array{0: array, 1: bool}>
     */
    public static function pushProvider(): array
    {
        return [
            // brq_transaction_type C001, mutationtype Collecting - the regression
            'bank transfer pay push' => [
                [
                    'additional' => [['initiated_by_magento', 1], ['service_action_from_magento', 'pay']],
                    'method'     => 'transfer',
                    'transactionType' => 'C001',
                    'mutationType'    => 'Collecting',
                ],
                false,
            ],
            // brq_transaction_type C052, mutationtype Collecting
            'alipay pay push' => [
                [
                    'additional' => [['initiated_by_magento', 1], ['service_action_from_magento', 'pay']],
                    'method'     => 'Alipay',
                    'transactionType' => 'C052',
                    'mutationType'    => 'Collecting',
                ],
                false,
            ],
            // brq_transaction_type C800 (visa capture)
            'visa capture initiated by magento' => [
                [
                    'additional' => [['initiated_by_magento', 1], ['service_action_from_magento', 'capture']],
                    'method'     => 'visa',
                    'transactionType' => 'C800',
                    'mutationType'    => 'Collecting',
                ],
                true,
            ],
            // brq_transaction_type C805 - the brand code the old C800 check missed entirely
            'mastercard capture initiated by magento' => [
                [
                    'additional' => [['initiated_by_magento', 1], ['service_action_from_magento', 'capture']],
                    'method'     => 'mastercard',
                    'transactionType' => 'C805',
                    'mutationType'    => 'Collecting',
                ],
                true,
            ],
            // brq_transaction_type I800, mutationtype Informational
            'creditcard authorize push' => [
                [
                    'additional' => [['initiated_by_magento', 1], ['service_action_from_magento', 'authorize']],
                    'method'     => 'visa',
                    'transactionType' => 'I800',
                    'mutationType'    => 'Informational',
                ],
                false,
            ],
            'capture action not initiated by magento' => [
                [
                    'additional' => [['service_action_from_magento', 'capture']],
                    'method'     => 'visa',
                    'transactionType' => 'C800',
                    'mutationType'    => 'Collecting',
                ],
                false,
            ],
            'klarnakp capture confirmation with success status' => [
                [
                    'additional' => [],
                    'method'     => 'klarnakp',
                    'captureId'  => 'CAPTURE-123',
                    'statusCode' => BuckarooStatusCode::SUCCESS,
                ],
                true,
            ],
            'klarnakp capture confirmation with non-success status' => [
                [
                    'additional' => [],
                    'method'     => 'klarnakp',
                    'captureId'  => 'CAPTURE-123',
                    'statusCode' => BuckarooStatusCode::PENDING_PROCESSING,
                ],
                false,
            ],
            'klarnakp success without a capture id' => [
                [
                    'additional' => [],
                    'method'     => 'klarnakp',
                    'captureId'  => '',
                    'statusCode' => BuckarooStatusCode::SUCCESS,
                ],
                false,
            ],
        ];
    }

    #[DataProvider('pushProvider')]
    public function testIsCaptureTransaction(array $push, bool $expected): void
    {
        $instance = $this->getInstance();
        $this->setProperty('pushRequest', $this->createPushRequest($push), $instance);

        $this->assertSame($expected, $this->invoke('isCaptureTransaction', $instance));
    }

    /**
     * Bank transfer has no authorize/capture flow, so no transfer push may enter the capture branch,
     * not even one carrying capture markers.
     */
    public function testTransferProcessorNeverReportsACapture(): void
    {
        $instance = $this->getObject('Buckaroo\Magento2\Model\Push\TransferProcessor', [
            'buckarooStatusCode' => $this->buckarooStatusCodeMock,
        ]);

        $this->setProperty('pushRequest', $this->createPushRequest([
            'additional' => [['initiated_by_magento', 1], ['service_action_from_magento', 'capture']],
            'method'     => 'transfer',
        ]), $instance);

        $this->assertFalse($this->invoke('isCaptureTransaction', $instance));
    }

    /**
     * @param array{additional?: array, method?: string, transactionType?: string,
     *              mutationType?: string, captureId?: string, statusCode?: int} $push
     */
    private function createPushRequest(array $push)
    {
        $additional = $push['additional'] ?? [];
        $captureId  = $push['captureId'] ?? '';
        $statusCode = $push['statusCode'] ?? BuckarooStatusCode::SUCCESS;

        // transaction_type and mutationtype are modelled deliberately: they are the fields the
        // pre-fix predicate keyed off, so a regression back to it must fail these tests.
        $fields = [
            'transaction_method' => $push['method'] ?? '',
            'transaction_type'   => $push['transactionType'] ?? '',
            'mutationtype'       => $push['mutationType'] ?? 'Collecting',
        ];

        $mock = $this->getFakeMock(PushRequestInterfaceStub::class)->getMock();

        $mock->method('hasAdditionalInformation')->willReturnCallback(
            function ($name, $value) use ($additional) {
                return in_array([$name, $value], $additional, true);
            }
        );

        $mock->method('hasPostData')->willReturnCallback(
            function ($name, $value) use ($fields) {
                $actual = $fields[$name] ?? null;

                if ($actual === null) {
                    return false;
                }

                return is_array($value) ? in_array($actual, $value, true) : $actual === $value;
            }
        );

        $mock->method('getServiceKlarnakpCaptureid')->willReturn($captureId);
        $mock->method('getStatusCode')->willReturn((string)$statusCode);

        return $mock;
    }
}

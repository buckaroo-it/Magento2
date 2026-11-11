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

namespace Buckaroo\Magento2\Test\Unit\Model\RequestPush;

use Buckaroo\Magento2\Model\RequestPush\JsonPushRequest;
use Buckaroo\Magento2\Model\Validator\PushSDK;

/**
 * A JSON push nests service parameters under Services[].Parameters[]. They were never read, so a
 * Klarna KP capture made in Plaza and pushed as JSON was not recognised as a capture.
 */
class JsonPushRequestTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = JsonPushRequest::class;

    /**
     * Shape of the push Buckaroo sends for a Klarna KP capture (Pay on a reservation).
     */
    private const KLARNAKP_CAPTURE = [
        'Transaction' => [
            'Key' => 'B93FCE57527442638FC56CD7E434F460',
            'Invoice' => '300000350',
            'ServiceCode' => 'KlarnaKp',
            'Status' => ['Code' => ['Code' => 190, 'Description' => 'Success']],
            'Order' => '300000350',
            'Currency' => 'EUR',
            'AmountDebit' => 66.15,
            'TransactionType' => 'V610',
            'Services' => [
                [
                    'Name' => 'KlarnaKp',
                    'Action' => null,
                    'Parameters' => [
                        ['Name' => 'CaptureId', 'Value' => '6c9952fd-7d50-4b7c-913f-faca6d9110fa'],
                    ],
                ],
            ],
            'MutationType' => 2,
        ],
    ];

    public function testAServiceParameterIsReadLikeInAnHttpPostPush(): void
    {
        $push = $this->makePush(self::KLARNAKP_CAPTURE);

        $this->assertSame('6c9952fd-7d50-4b7c-913f-faca6d9110fa', $push->getServiceKlarnakpCaptureid());
    }

    public function testAKlarnaKpCaptureIsRecognisedFromAJsonPush(): void
    {
        $push = $this->makePush(self::KLARNAKP_CAPTURE);

        $this->assertTrue($push->hasPostData('transaction_method', ['klarnakp', 'KlarnaKp']));
        $this->assertNotEmpty($push->getServiceKlarnakpCaptureid());
    }

    public function testAMissingServiceParameterIsNull(): void
    {
        $push = $this->makePush(self::KLARNAKP_CAPTURE);

        $this->assertNull($push->getServiceKlarnakpReservationnumber());
        $this->assertNull($push->getServiceTransferIban());
    }

    public function testAPushWithoutServicesHasNoServiceParameters(): void
    {
        $data = self::KLARNAKP_CAPTURE;
        unset($data['Transaction']['Services']);

        $this->assertNull($this->makePush($data)->getServiceKlarnakpCaptureid());

        $data['Transaction']['Services'] = [['Name' => 'KlarnaKp', 'Parameters' => null], 'not a service'];
        $this->assertNull($this->makePush($data)->getServiceKlarnakpCaptureid());
    }

    public function testATopLevelFieldStillWins(): void
    {
        $data = self::KLARNAKP_CAPTURE;
        $data['Transaction']['ServiceKlarnakpCaptureid'] = 'top-level';

        $this->assertSame('top-level', $this->makePush($data)->getServiceKlarnakpCaptureid());
    }

    /**
     * validate() used to discard its $store argument, so the SDK client was always built from the
     * ambient store - the default store view on the push route - and a push for another store view
     * was checked with the wrong secret key on a multi-store install.
     */
    public function testForwardsTheStoreToTheValidatorInsteadOfDroppingIt(): void
    {
        $validator = $this->createMock(PushSDK::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with($this->isType('array'), 2)
            ->willReturn(true);

        $request = new JsonPushRequest(['Transaction' => []], $validator);

        $this->assertTrue($request->validate(2));
    }

    public function testForwardsANullStoreUnchanged(): void
    {
        $validator = $this->createMock(PushSDK::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with($this->isType('array'), null)
            ->willReturn(false);

        $request = new JsonPushRequest(['Transaction' => []], $validator);

        $this->assertFalse($request->validate());
    }

    /**
     * @param array $data
     *
     * @return JsonPushRequest
     */
    private function makePush(array $data): JsonPushRequest
    {
        return new JsonPushRequest($data, $this->getFakeMock(PushSDK::class)->getMock());
    }
}

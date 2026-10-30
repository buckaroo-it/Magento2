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

namespace Buckaroo\Magento2\Test\Unit\Model\Validator;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Model\Validator\Push;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Store\Api\Data\StoreInterface;

/**
 * The HTTP-post signature algorithm now lives in the Buckaroo PHP SDK (see the SDK's
 * HttpPostTest / SignatureSecurityTest). This class only covers the plugin's remaining
 * responsibilities: the status-code mapping and delegating signature validation to the SDK.
 */
class PushTest extends BaseTest
{
    /**
     * @var string
     */
    protected $instanceClass = Push::class;

    public function testValidateThrowsBecauseSignatureValidationHasItsOwnEntryPoint()
    {
        $instance = $this->getInstance();
        $this->expectException(\LogicException::class);
        $instance->validate(null);
    }

    /* ---------------------------------------------------------------------
     * validateSignature() — delegation to the SDK adapter
     * ------------------------------------------------------------------ */

    public function testValidateSignatureDelegatesToSdkAdapterAndReturnsItsResult()
    {
        $payload = ['brq_amount' => '1.00', 'brq_signature' => 'sig'];

        $adapter = $this->createMock(BuckarooAdapter::class);
        $adapter->expects($this->once())
            ->method('validate')
            ->with($payload, null, null, null)
            ->willReturn(true);

        $instance = $this->getInstance(['sdkAdapter' => $adapter]);

        $this->assertTrue($instance->validateSignature($payload, $payload));
    }

    public function testValidateSignatureReturnsAdapterFalse()
    {
        $payload = ['brq_amount' => '1.00', 'brq_signature' => 'sig'];

        $adapter = $this->createMock(BuckarooAdapter::class);
        $adapter->method('validate')->willReturn(false);

        $instance = $this->getInstance(['sdkAdapter' => $adapter]);

        $this->assertFalse($instance->validateSignature($payload, $payload));
    }

    public function testValidateSignatureShortCircuitsWhenSignatureFieldMissing()
    {
        $adapter = $this->createMock(BuckarooAdapter::class);
        $adapter->expects($this->never())->method('validate');

        $instance = $this->getInstance(['sdkAdapter' => $adapter]);

        $this->assertFalse($instance->validateSignature(['brq_amount' => '1.00'], ['brq_amount' => '1.00']));
    }

    public function testValidateSignaturePassesOrderStoreIdToAdapter()
    {
        $payload = ['brq_signature' => 'sig'];

        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(5);

        $adapter = $this->createMock(BuckarooAdapter::class);
        $adapter->expects($this->once())
            ->method('validate')
            ->with($payload, null, null, 5)
            ->willReturn(true);

        $instance = $this->getInstance(['sdkAdapter' => $adapter]);

        $this->assertTrue($instance->validateSignature($payload, $payload, $store));
    }

    public function testValidateSignatureReturnsFalseWhenAdapterThrows()
    {
        $payload = ['brq_signature' => 'sig'];

        $adapter = $this->createMock(BuckarooAdapter::class);
        $adapter->method('validate')->willThrowException(new \RuntimeException('gateway error'));

        $instance = $this->getInstance(['sdkAdapter' => $adapter]);

        $this->assertFalse($instance->validateSignature($payload, $payload));
    }

    /* ---------------------------------------------------------------------
     * validateStatusCode()
     * ------------------------------------------------------------------ */

    public function testValidateStatusCodeReturnsMessageAndStatusForKnownCode()
    {
        $helperMock = $this->getFakeMock(Data::class)->getMock();
        $helperMock->method('getStatusByValue')
            ->with(190)
            ->willReturn('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');

        $instance = $this->getInstance(['helper' => $helperMock]);

        $this->assertSame(
            [
                'message' => 'Success',
                'status'  => 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS',
                'code'    => 190,
            ],
            $instance->validateStatusCode(190)
        );
    }

    public function testValidateStatusCodeReturnsNeutralForUnknownCode()
    {
        $helperMock = $this->getFakeMock(Data::class)->getMock();
        $helperMock->method('getStatusByValue')->willReturn(null);

        $instance = $this->getInstance(['helper' => $helperMock]);

        $this->assertSame(
            [
                'message' => 'Onbekende responsecode: 999',
                'status'  => 'BUCKAROO_MAGENTO2_STATUSCODE_NEUTRAL',
                'code'    => 999,
            ],
            $instance->validateStatusCode(999)
        );
    }
}

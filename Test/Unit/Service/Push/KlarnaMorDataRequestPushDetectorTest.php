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

namespace Buckaroo\Magento2\Test\Unit\Service\Push;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Service\Push\KlarnaMorDataRequestPushDetector;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class KlarnaMorDataRequestPushDetectorTest extends TestCase
{
    /**
     * @var KlarnaMorDataRequestPushDetector
     */
    private KlarnaMorDataRequestPushDetector $detector;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->detector = new KlarnaMorDataRequestPushDetector();
    }

    public function testAcknowledgesPlazaKlarnaDataRequestWithoutOrderReference(): void
    {
        $pushRequest = $this->createPlazaPushMock();

        $this->assertTrue($this->detector->shouldAcknowledgeWithoutOrder($pushRequest));
    }

    public function testDoesNotAcknowledgeMagentoInitiatedPush(): void
    {
        $pushRequest = $this->createPlazaPushMock();
        $pushRequest->method('getAdditionalInformation')
            ->with('initiated_by_magento')
            ->willReturn('1');

        $this->assertFalse($this->detector->shouldAcknowledgeWithoutOrder($pushRequest));
    }

    public function testDoesNotAcknowledgeWhenOrderNumberIsPresent(): void
    {
        $pushRequest = $this->createPlazaPushMock();
        $pushRequest->method('getOrderNumber')->willReturn('000000123');

        $this->assertFalse($this->detector->shouldAcknowledgeWithoutOrder($pushRequest));
    }

    /**
     * @return PushRequestInterface|MockObject
     */
    private function createPlazaPushMock()
    {
        $pushRequest = $this->getMockBuilder(PushRequestInterface::class)
            ->addMethods(['get'])
            ->getMock();

        $pushRequest->method('getDatarequest')->willReturn('DD0A97CDF9F84ADD88CA923278A16C5D');
        $pushRequest->method('getOrderNumber')->willReturn(null);
        $pushRequest->method('getInvoiceNumber')->willReturn(null);
        $pushRequest->method('getStatusCode')->willReturn((string)BuckarooStatusCode::SUCCESS);
        $pushRequest->method('getTransactionMethod')->willReturn(null);
        $pushRequest->method('getAdditionalInformation')
            ->with('initiated_by_magento')
            ->willReturn(null);
        $pushRequest->method('get')->willReturnCallback(static function (string $property): ?string {
            return $property === 'PrimaryService' ? 'klarna' : null;
        });

        return $pushRequest;
    }
}

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

namespace Buckaroo\Magento2\Test\Unit\Controller\Applepay;

use Buckaroo\Magento2\Controller\Applepay\SaveOrder;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Service\Applepay\SaveOrderProcessor;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

/**
 * The express button needs the reason an order was not placed, so it can show it to the shopper.
 */
class SaveOrderTest extends TestCase
{
    private const PAYLOAD = ['payment' => ['token' => 'x'], 'extra' => ['method' => 'x']];

    /**
     * @var array|null
     */
    private $responseData;

    public function testOrderPlacedReturnsSuccess(): void
    {
        $processor = $this->createMock(SaveOrderProcessor::class);
        $processor->method('place')->willReturn(['RequiredAction' => ['RedirectURL' => 'https://shop/success']]);

        $this->createController($processor)->execute();

        $this->assertSame(
            ['success' => true, 'data' => ['RequiredAction' => ['RedirectURL' => 'https://shop/success']]],
            $this->responseData
        );
    }

    public function testRejectedOrderReturnsTheReason(): void
    {
        $processor = $this->createMock(SaveOrderProcessor::class);
        $processor->method('place')->willThrowException(
            new LocalizedException(__('This card is not supported for this payment method. Please choose another card.'))
        );

        $this->createController($processor)->execute();

        $this->assertSame(
            [
                'success' => false,
                'error'   => 'This card is not supported for this payment method. Please choose another card.',
            ],
            $this->responseData
        );
    }

    private function createController(SaveOrderProcessor $processor): SaveOrder
    {
        $json = $this->createMock(Json::class);
        $json->method('setData')->willReturnCallback(function ($data) use ($json) {
            $this->responseData = $data;
            return $json;
        });

        $jsonFactory = $this->createMock(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($json);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParams')->willReturn(self::PAYLOAD);

        return new SaveOrder(
            $jsonFactory,
            $request,
            $this->createMock(BuckarooLoggerInterface::class),
            $processor
        );
    }
}

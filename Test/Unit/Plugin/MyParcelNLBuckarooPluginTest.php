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

namespace Buckaroo\Magento2\Test\Unit\Plugin;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Plugin\MyParcelNLBuckarooPlugin;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MyParcelNLBuckarooPluginTest extends TestCase
{
    /**
     * @var Http|MockObject
     */
    private $requestMock;

    /**
     * @var Session|MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var MyParcelNLBuckarooPlugin
     */
    private MyParcelNLBuckarooPlugin $plugin;

    protected function setUp(): void
    {
        $this->requestMock = $this->createMock(Http::class);
        // setMyParcelNLBuckarooData() is a SessionManager magic setter, so it is exercised
        // through __call; asserting on __call keeps the test valid across PHPUnit versions.
        $this->checkoutSessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();

        $json = new Json();

        $this->plugin = new MyParcelNLBuckarooPlugin(
            $this->checkoutSessionMock,
            $this->requestMock,
            $json,
            $this->createMock(BuckarooLoggerInterface::class)
        );
    }

    public function testStoresPickupLocationFromDeliveryOptions(): void
    {
        // Arrange
        $body = json_encode([
            'deliveryOptions' => [
                ['deliveryType' => 'pickup', 'pickupLocation' => ['location_code' => 'NL-123']],
            ],
        ]);
        $this->requestMock->method('getContent')->willReturn($body);

        // Assert
        $this->checkoutSessionMock->expects($this->once())
            ->method('__call')
            ->with('setMyParcelNLBuckarooData', [json_encode(['location_code' => 'NL-123'])]);

        // Act
        $this->plugin->beforeGetFromDeliveryOptions();
    }

    public function testIgnoresEmptyBody(): void
    {
        $this->requestMock->method('getContent')->willReturn('');
        $this->checkoutSessionMock->expects($this->never())->method('__call');

        $this->plugin->beforeGetFromDeliveryOptions();
    }

    public function testIgnoresMalformedJson(): void
    {
        $this->requestMock->method('getContent')->willReturn('not-json{');
        $this->checkoutSessionMock->expects($this->never())->method('__call');

        $this->plugin->beforeGetFromDeliveryOptions();
    }

    public function testIgnoresNonPickupDeliveryType(): void
    {
        $body = json_encode([
            'deliveryOptions' => [
                ['deliveryType' => 'delivery', 'pickupLocation' => ['x' => 1]],
            ],
        ]);
        $this->requestMock->method('getContent')->willReturn($body);
        $this->checkoutSessionMock->expects($this->never())->method('__call');

        $this->plugin->beforeGetFromDeliveryOptions();
    }

    public function testIgnoresScalarJson(): void
    {
        $this->requestMock->method('getContent')->willReturn('"just-a-string"');
        $this->checkoutSessionMock->expects($this->never())->method('__call');

        $this->plugin->beforeGetFromDeliveryOptions();
    }
}

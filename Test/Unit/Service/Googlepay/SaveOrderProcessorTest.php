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
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Service\Googlepay;

use Buckaroo\Magento2\Service\Googlepay\SaveOrderProcessor;
use Buckaroo\Magento2\Test\Unit\Stubs\AddressStub;
use Magento\Quote\Model\Quote;

/**
 * Shipping rates are collected for the address the order is placed with, before a method is set.
 */
class SaveOrderProcessorTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = SaveOrderProcessor::class;

    /** @var string[] */
    private $calls = [];

    public function testRatesAreCollectedForTheFinalAddressBeforeTheChosenMethodIsSet(): void
    {
        $address = $this->buildAddress([]);

        $this->invokeArgs(
            'setQuoteShippingMethod',
            [$this->buildQuote($address), ['extra' => ['shippingMethod' => ['identifier' => 'tablerate_bestway']]]],
            $this->buildProcessor()
        );

        $this->assertSame(
            ['setCollectShippingRates', 'collectShippingRates', 'setShippingMethod:tablerate_bestway'],
            $this->calls
        );
    }

    public function testWithoutAChosenMethodTheFirstRateForTheFinalAddressIsUsed(): void
    {
        $address = $this->buildAddress([new \Magento\Framework\DataObject(['code' => 'flatrate_flatrate'])]);

        $this->invokeArgs('setQuoteShippingMethod', [$this->buildQuote($address), ['extra' => []]], $this->buildProcessor());

        $this->assertSame(
            ['setCollectShippingRates', 'collectShippingRates', 'setShippingMethod:flatrate_flatrate'],
            $this->calls
        );
    }

    private function buildProcessor(): SaveOrderProcessor
    {
        $processor = (new \ReflectionClass(SaveOrderProcessor::class))->newInstanceWithoutConstructor();
        $this->setProperty(
            'logger',
            $this->getFakeMock(\Buckaroo\Magento2\Logging\BuckarooLoggerInterface::class)->getMock(),
            $processor
        );

        return $processor;
    }

    private function buildAddress(array $rates)
    {
        $address = $this->getFakeMock(AddressStub::class)
            ->onlyMethods(['setCollectShippingRates', 'collectShippingRates', 'setShippingMethod', 'getAllShippingRates'])
            ->disableOriginalConstructor()
            ->getMock();

        $address->method('setCollectShippingRates')->willReturnCallback(function () use ($address) {
            $this->calls[] = 'setCollectShippingRates';
            return $address;
        });
        $address->method('collectShippingRates')->willReturnCallback(function () use ($address) {
            $this->calls[] = 'collectShippingRates';
            return $address;
        });
        $address->method('setShippingMethod')->willReturnCallback(function ($method) use ($address) {
            $this->calls[] = 'setShippingMethod:' . $method;
            return $address;
        });
        $address->method('getAllShippingRates')->willReturn($rates);

        return $address;
    }

    private function buildQuote($address)
    {
        $quote = $this->getFakeMock(Quote::class)
            ->onlyMethods(['getShippingAddress'])
            ->disableOriginalConstructor()
            ->getMock();
        $quote->method('getShippingAddress')->willReturn($address);

        return $quote;
    }
}

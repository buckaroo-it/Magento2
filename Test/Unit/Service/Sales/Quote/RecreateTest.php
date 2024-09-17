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
namespace Buckaroo\Magento2\Test\Unit\Service\Sales\Quote;

use Magento\Checkout\Model\Cart;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Quote\Model\Quote\Address;

class RecreateTest extends BaseTest
{
    protected $instanceClass = Recreate::class;

    public function testRecreate()
    {
        // Mock the Quote object directly without loading by ID
        $quoteMock = $this->getFakeMock(Quote::class)
            ->setMethods(['save', 'getBillingAddress', 'getShippingAddress', 'removeAddress'])
            ->getMock();
        $quoteMock->expects($this->any())->method('getBillingAddress')->willReturn($this->createMock(Address::class));
        $quoteMock->expects($this->any())->method('getShippingAddress')->willReturn($this->createMock(Address::class));
        $quoteMock->expects($this->any())->method('removeAddress')->willReturnSelf();

        // Mock the Resource Model to avoid "The resource isn't set" error
        $quoteResourceMock = $this->getFakeMock(QuoteResource::class)
            ->setMethods(['save'])
            ->getMock();
        $quoteMock->setResource($quoteResourceMock);

        // Mock the Cart object
        $cartMock = $this->getFakeMock(Cart::class)
            ->setMethods(['setQuote', 'save'])
            ->getMock();
        $cartMock->expects($this->once())->method('setQuote')->with($quoteMock)->willReturnSelf();
        $cartMock->expects($this->once())->method('save');

        // Define a sample response array to pass as the second argument
        $response = [
            'add_service_action_from_magento' => 'payfastcheckout', // Example response value
        ];

        // Instantiate the Recreate class with necessary mocks
        $instance = $this->getInstance(['cart' => $cartMock]);

        // Call the recreate method with the quote and response
        $instance->recreate($quoteMock, $response);

        // Assertions to verify the quote state
        $this->assertTrue($quoteMock->getIsActive());
        $this->assertEquals('1', $quoteMock->getTriggerRecollect());
        $this->assertNull($quoteMock->getReservedOrderId());
        $this->assertNull($quoteMock->getBuckarooFee());
        $this->assertNull($quoteMock->getBaseBuckarooFee());
        $this->assertNull($quoteMock->getBuckarooFeeTaxAmount());
        $this->assertNull($quoteMock->getBuckarooFeeBaseTaxAmount());
        $this->assertNull($quoteMock->getBuckarooFeeInclTax());
        $this->assertNull($quoteMock->getBaseBuckarooFeeInclTax());
    }
}

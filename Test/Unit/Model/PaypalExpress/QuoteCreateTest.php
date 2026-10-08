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
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\PaypalExpress;

use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterface;
use Buckaroo\Magento2\Model\PaypalExpress\QuoteCreate;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Test\Unit\Stubs\AddressStub;
use Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\QuoteRepository;

/**
 * Guest PayPal Express quote setup must accept an estimate-only address without
 * writing placeholder name/email/street onto the quote (those leak into checkout
 * and third-party tools when the shopper cancels before order create).
 */
class QuoteCreateTest extends BaseTest
{
    protected $instanceClass = QuoteCreate::class;

    public function testGuestQuoteSetupIgnoresValidationWithoutWritingPlaceholders(): void
    {
        $shipping = $this->makeGuestAddress();
        $shipping->expects($this->once())->method('setShouldIgnoreValidation')->with(true);
        $shipping->expects($this->never())->method('setFirstname');
        $shipping->expects($this->never())->method('setLastname');
        $shipping->expects($this->never())->method('setStreet');
        $shipping->expects($this->never())->method('setTelephone');
        $shipping->expects($this->never())->method('setEmail');

        $billing = $this->makeGuestAddress();
        $billing->expects($this->once())->method('setShouldIgnoreValidation')->with(true);
        $billing->expects($this->never())->method('setFirstname');
        $billing->expects($this->never())->method('setEmail');

        $quote = $this->getFakeMock(QuoteStub::class)->getMock();
        $quote->method('getShippingAddress')->willReturn($shipping);
        $quote->method('getBillingAddress')->willReturn($billing);
        $quote->method('getId')->willReturn(15);
        $quote->expects($this->never())->method('setCustomerEmail');

        $quoteRepository = $this->getFakeMock(QuoteRepository::class)->getMock();
        $quoteRepository->expects($this->once())->method('save')->with($quote);

        $estimation = $this->getFakeMock(ShipmentEstimationInterface::class)->getMock();
        $estimation->expects($this->once())
            ->method('estimateByExtendedAddress')
            ->with(15, $shipping)
            ->willReturn([]);

        $session = $this->getFakeMock(CustomerSession::class)
            ->onlyMethods(['isLoggedIn'])
            ->getMock();
        $session->method('isLoggedIn')->willReturn(false);

        $instance = $this->makeQuoteCreate($session, $quoteRepository, $estimation);
        $this->setProperty('quote', $quote, $instance);
        $this->invokeArgs('addAddressToQuote', [$this->makeShippingRequest()], $instance);

        $this->assertSame('NL', $shipping->getCountryId());
        $this->assertSame('1012AB', $shipping->getPostcode());
        $this->assertSame('Amsterdam', $shipping->getCity());
        $this->assertSame('NH', $shipping->getData('region'));
    }

    public function testLoggedInQuoteSetupKeepsTheCustomerAddress(): void
    {
        $shipping = $this->makeAddress();
        $shipping->expects($this->once())->method('setShouldIgnoreValidation')->with(true);
        $shipping->setData('firstname', 'Jane');
        $shipping->setData('lastname', 'Doe');
        $shipping->setData('street', ['Hoofdstraat 1']);
        $shipping->setData('telephone', '+31612345678');
        $shipping->setData('email', 'jane@example.com');

        $billing = $this->makeAddress();
        $billing->expects($this->once())->method('setShouldIgnoreValidation')->with(true);

        $customer = $this->getFakeMock(CustomerInterface::class)->getMock();

        $quote = $this->getFakeMock(QuoteStub::class)->getMock();
        $quote->method('getShippingAddress')->willReturn($shipping);
        $quote->method('getBillingAddress')->willReturn($billing);
        $quote->method('getId')->willReturn(15);
        $quote->expects($this->once())->method('assignCustomerWithAddressChange')->with($customer);
        $quote->expects($this->never())->method('setCustomerEmail');

        $session = $this->getFakeMock(CustomerSession::class)
            ->onlyMethods(['isLoggedIn', 'getCustomerId'])
            ->getMock();
        $session->method('isLoggedIn')->willReturn(true);
        $session->method('getCustomerId')->willReturn(42);

        $customerRepository = $this->getFakeMock(CustomerRepositoryInterface::class)->getMock();
        $customerRepository->method('getById')->with(42)->willReturn($customer);

        $quoteRepository = $this->getFakeMock(QuoteRepository::class)->getMock();
        $quoteRepository->expects($this->once())->method('save');

        $estimation = $this->getFakeMock(ShipmentEstimationInterface::class)->getMock();
        $estimation->method('estimateByExtendedAddress')->willReturn([]);

        $instance = $this->makeQuoteCreate($session, $quoteRepository, $estimation, $customerRepository);
        $this->setProperty('quote', $quote, $instance);
        $this->invokeArgs('addAddressToQuote', [$this->makeShippingRequest()], $instance);

        $this->assertSame('Jane', $shipping->getFirstname());
        $this->assertSame('Doe', $shipping->getLastname());
        $this->assertSame(['Hoofdstraat 1'], $shipping->getStreet());
        $this->assertSame('+31612345678', $shipping->getTelephone());
        $this->assertSame('jane@example.com', $shipping->getEmail());
    }

    private function makeAddress(): AddressStub
    {
        return $this->getFakeMock(AddressStub::class)
            ->onlyMethods(['setShouldIgnoreValidation'])
            ->getMock();
    }

    private function makeGuestAddress(): AddressStub
    {
        return $this->getFakeMock(AddressStub::class)
            ->onlyMethods([
                'setShouldIgnoreValidation',
                'setFirstname',
                'setLastname',
                'setStreet',
                'setTelephone',
                'setEmail',
            ])
            ->getMock();
    }

    private function makeShippingRequest(): ShippingAddressRequestInterface
    {
        $request = $this->getFakeMock(ShippingAddressRequestInterface::class)->getMock();
        $request->method('getCountryCode')->willReturn('NL');
        $request->method('getPostalCode')->willReturn('1012AB');
        $request->method('getCity')->willReturn('Amsterdam');
        $request->method('getState')->willReturn('NH');

        return $request;
    }

    private function makeQuoteCreate(
        CustomerSession $session,
        QuoteRepository $quoteRepository,
        ShipmentEstimationInterface $estimation,
        ?CustomerRepositoryInterface $customerRepository = null
    ): QuoteCreate {
        return $this->getObject(QuoteCreate::class, [
            'customerSession' => $session,
            'customerRepository' => $customerRepository
                ?? $this->getFakeMock(CustomerRepositoryInterface::class)->getMock(),
            'quoteRepository' => $quoteRepository,
            'shipmentEstimation' => $estimation,
        ]);
    }
}

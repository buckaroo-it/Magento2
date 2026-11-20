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

namespace Buckaroo\Magento2\Test\Unit\Model\PaypalExpress;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Logging\Mail;
use Buckaroo\Magento2\Model\PaypalExpress\OrderCreate;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\TestCase;

class OrderCreateAddressTest extends TestCase
{
    public function testEnsureRequiredAddressFieldsFillsOnlyEmptyFields(): void
    {
        $shipping = $this->address([
            'firstname' => 'Jane', 'lastname' => '', 'street' => '', 'telephone' => ' ',
            'email' => 'jane@example.com', 'city' => 'Utrecht', 'postcode' => '3512AB', 'country_id' => 'NL',
        ]);
        $billing = $this->address([]);
        $quote = $this->quote($shipping, $billing, null);

        $this->invoke($this->orderCreate(), 'ensureRequiredAddressFields', [$quote]);

        $this->assertSame('Jane', $shipping->getFirstname());
        $this->assertSame('Customer', $shipping->getLastname());
        $this->assertSame(['Pending'], $shipping->getStreet());
        $this->assertSame('000-000-0000', $shipping->getTelephone());
        $this->assertSame('jane@example.com', $shipping->getEmail());

        $this->assertSame('PayPal', $billing->getFirstname());
        $this->assertSame('000-000-0000', $billing->getTelephone());
        $this->assertSame('Utrecht', $billing->getCity());
        $this->assertSame('3512AB', $billing->getPostcode());
        $this->assertSame('NL', $billing->getCountryId());

        $this->assertSame('pending@paypal.customer', $quote->getCustomerEmail());
    }

    public function testClearPendingAddressFieldsRemovesOnlyTheFilledPlaceholders(): void
    {
        $shipping = $this->address([
            'firstname' => '', 'lastname' => '', 'street' => '', 'telephone' => '', 'email' => '',
        ]);
        $billing = $this->address([
            'firstname' => 'PayPal', 'lastname' => 'Customer', 'street' => 'Hoofdstraat 1',
            'telephone' => '000-000-0000', 'email' => 'jane@example.com',
        ]);
        $quote = $this->quote($shipping, $billing, null);

        $repository = $this->createMock(CartRepositoryInterface::class);
        $repository->expects($this->once())->method('save')->with($quote);
        $orderCreate = $this->orderCreate($repository);

        $this->invoke($orderCreate, 'ensureRequiredAddressFields', [$quote]);
        $this->invoke($orderCreate, 'clearPendingAddressFields', [$quote]);

        $this->assertSame('', $shipping->getFirstname());
        $this->assertSame('', $shipping->getLastname());
        $this->assertSame('', implode('', $shipping->getStreet()));
        $this->assertSame('', $shipping->getTelephone());
        $this->assertSame('', $shipping->getEmail());
        $this->assertSame('', $quote->getCustomerEmail());

        $this->assertSame('PayPal', $billing->getFirstname());
        $this->assertSame('Customer', $billing->getLastname());
        $this->assertSame(['Hoofdstraat 1'], $billing->getStreet());
        $this->assertSame('000-000-0000', $billing->getTelephone());
        $this->assertSame('jane@example.com', $billing->getEmail());
    }

    public function testBillingInAnotherCountryDoesNotGetShippingLocationFields(): void
    {
        $shipping = $this->address([
            'firstname' => 'Jane', 'lastname' => 'Doe', 'street' => 'Main St 1', 'telephone' => '1',
            'email' => 'jane@example.com', 'city' => 'Los Angeles', 'postcode' => '90001', 'country_id' => 'US',
        ]);
        $billing = $this->address([
            'firstname' => 'Jane', 'lastname' => 'Doe', 'street' => 'Hoofdstraat 1', 'telephone' => '1',
            'email' => 'jane@example.com', 'country_id' => 'NL',
        ]);

        $this->invoke($this->orderCreate(), 'ensureRequiredAddressFields', [$this->quote($shipping, $billing, 'a@b.c')]);

        $this->assertSame('NL', $billing->getCountryId());
        $this->assertNull($billing->getCity());
        $this->assertNull($billing->getPostcode());
    }

    public function testClearPendingAddressFieldsIgnoresSaveErrors(): void
    {
        $quote = $this->quote($this->address([]), $this->address([]), null);
        $repository = $this->createMock(CartRepositoryInterface::class);
        $repository->method('save')->willThrowException(new \RuntimeException('DB error'));
        $orderCreate = $this->orderCreate($repository);

        $this->invoke($orderCreate, 'ensureRequiredAddressFields', [$quote]);
        $this->invoke($orderCreate, 'clearPendingAddressFields', [$quote]);

        $this->assertSame('', $quote->getCustomerEmail());
    }

    private function orderCreate(?CartRepositoryInterface $repository = null): OrderCreate
    {
        $log = $this->createMock(Log::class);
        $mail = new \ReflectionProperty(Log::class, 'mail');
        $mail->setAccessible(true);
        $mail->setValue($log, $this->createMock(Mail::class));

        $orderCreate = (new \ReflectionClass(OrderCreate::class))->newInstanceWithoutConstructor();
        $this->setProperty($orderCreate, 'quoteRepository', $repository ?? $this->createMock(CartRepositoryInterface::class));
        $this->setProperty($orderCreate, 'logger', $log);

        return $orderCreate;
    }

    private function setProperty(object $object, string $name, $value): void
    {
        $property = new \ReflectionProperty($object, $name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    private function address(array $data): Address
    {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRegionId', 'setRegionId', 'getRegion', 'setRegion'])
            ->getMock();
        $address->setData($data);

        return $address;
    }

    private function quote(Address $shipping, Address $billing, ?string $customerEmail): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress', 'getBillingAddress'])
            ->getMock();
        $quote->method('getShippingAddress')->willReturn($shipping);
        $quote->method('getBillingAddress')->willReturn($billing);
        $quote->setData('customer_email', $customerEmail);

        return $quote;
    }

    private function invoke(object $object, string $method, array $args)
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }
}

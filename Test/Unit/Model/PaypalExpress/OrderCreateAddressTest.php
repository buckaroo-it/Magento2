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

use Magento\Framework\DataObject;

/**
 * The cart's shipping estimate leaves the name and street empty. Magento rejects such an address
 * when the order is placed, so PayPal Express fills placeholders that PayPal's data replaces later.
 */
class OrderCreateAddressTest extends \Buckaroo\Magento2\Test\BaseTest
{
    private const ORDER_CREATE = 'Buckaroo\Magento2\Model\PaypalExpress\OrderCreate';

    /**
     * Magento trims a value before it checks it, so a value of only spaces counts as empty.
     */
    public function testAValueOfOnlySpacesGetsThePlaceholder(): void
    {
        $address = new DataObject([
            'firstname' => ' ',
            'lastname'  => '  ',
            'street'    => [' '],
            'telephone' => ' ',
            'email'     => ' ',
        ]);

        $this->setPendingFields($address);

        $this->assertSame('PayPal', $address->getFirstname());
        $this->assertSame('Customer', $address->getLastname());
        $this->assertSame(['Pending'], $address->getStreet());
        $this->assertSame('000-000-0000', $address->getTelephone());
        $this->assertSame('pending@paypal.customer', $address->getEmail());
    }

    public function testAnEmptyAddressGetsThePlaceholder(): void
    {
        $address = new DataObject(['firstname' => '', 'lastname' => null, 'street' => []]);

        $this->setPendingFields($address);

        $this->assertSame('PayPal', $address->getFirstname());
        $this->assertSame('Customer', $address->getLastname());
        $this->assertSame(['Pending'], $address->getStreet());
    }

    public function testRealValuesAreKept(): void
    {
        $address = new DataObject([
            'firstname' => 'Veronica',
            'lastname'  => 'Costello',
            'street'    => ['6146 Honey Bluff Parkway'],
            'telephone' => '5552293',
            'email'     => 'roni@example.com',
        ]);

        $this->setPendingFields($address);

        $this->assertSame('Veronica', $address->getFirstname());
        $this->assertSame('Costello', $address->getLastname());
        $this->assertSame(['6146 Honey Bluff Parkway'], $address->getStreet());
        $this->assertSame('5552293', $address->getTelephone());
        $this->assertSame('roni@example.com', $address->getEmail());
    }

    /**
     * @param bool $isGuest
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('guestProvider')]
    public function testTheOrderUpdateIsToldWhetherTheOrderIsAGuestOrder(bool $isGuest): void
    {
        $shipping = new DataObject(['address_type' => 'shipping']);
        $billing = new DataObject(['address_type' => 'billing']);

        $order = $this->getFakeMock('Magento\Sales\Model\Order')
            ->onlyMethods(['getShippingAddress', 'getBillingAddress', 'getCustomerIsGuest'])
            ->getMock();
        $order->method('getShippingAddress')->willReturn($shipping);
        $order->method('getBillingAddress')->willReturn($billing);
        $order->method('getCustomerIsGuest')->willReturn($isGuest ? 1 : 0);

        $orderUpdate = $this->getFakeMock('Buckaroo\Magento2\Model\PaypalExpress\OrderUpdate')
            ->onlyMethods(['updateAddress', 'updateEmail', 'updateCustomerName'])
            ->getMock();
        $orderUpdate->expects($this->exactly(2))
            ->method('updateAddress')
            ->willReturnCallback(function ($address, $guestFlag) use ($isGuest) {
                $this->assertSame($isGuest, $guestFlag);
                return $address;
            });

        $factory = $this->getFakeMock('Buckaroo\Magento2\Model\PaypalExpress\OrderUpdateFactory')
            ->onlyMethods(['create'])
            ->getMock();
        $factory->method('create')->willReturn($orderUpdate);

        $instance = $this->getFakeMock(self::ORDER_CREATE)->onlyMethods([])->getMock();
        $this->setProperty('orderUpdateFactory', $factory, $instance);
        $this->setProperty(
            'orderRepository',
            $this->getFakeMock('Magento\Sales\Api\OrderRepositoryInterface')->getMock(),
            $instance
        );

        (new \ReflectionMethod(self::ORDER_CREATE, 'updateOrder'))->invoke($instance, $order);
    }

    public static function guestProvider(): array
    {
        return [
            'guest order'     => [true],
            'logged-in order' => [false],
        ];
    }

    /**
     * @param DataObject $address
     */
    private function setPendingFields(DataObject $address): void
    {
        $instance = $this->getFakeMock(self::ORDER_CREATE)->onlyMethods([])->getMock();

        (new \ReflectionMethod(self::ORDER_CREATE, 'setPendingFieldsOnAddress'))->invoke($instance, $address);
    }
}

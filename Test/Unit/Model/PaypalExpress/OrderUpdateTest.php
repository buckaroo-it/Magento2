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

use Buckaroo\Magento2\Model\PaypalExpress\OrderUpdate;
use Magento\Framework\DataObject;

/**
 * PayPal returns the shopper's name and the address chosen in PayPal; the order addresses must
 * end up with those values.
 */
class OrderUpdateTest extends \Buckaroo\Magento2\Test\BaseTest
{
    private const PAYPAL_DATA = [
        'payerFirstname' => 'Andrew',
        'payerLastname'  => 'Vozniuk',
        'payerEmail'     => 'andrew@example.com',
        'address_line_1' => 'Paul Krugerkade',
        'admin_area_2'   => 'Heerenveen',
        'postal_code'    => '8441ER',
        'payerCountry'   => 'NL',
    ];

    /**
     * A guest's address only holds what the cart estimate, another module or PayPal's shipping
     * callback put on the quote, so PayPal's data always replaces it.
     */
    public function testAGuestAddressAlwaysGetsThePayPalAddress(): void
    {
        $address = $this->address('PaypalExpress', 'PaypalExpress', ['PaypalExpress'], 'shopper@example.com', '0612345678');

        $this->orderUpdate(self::PAYPAL_DATA)->updateAddress($address, true);

        $this->assertSame('Andrew', $address->getFirstname());
        $this->assertSame('Vozniuk', $address->getLastname());
        $this->assertSame(['Paul Krugerkade'], $address->getStreet());
        $this->assertSame('Heerenveen', $address->getCity());
        $this->assertSame('8441ER', $address->getPostcode());
        $this->assertSame('NL', $address->getCountryId());
        $this->assertSame('andrew@example.com', $address->getEmail());
        $this->assertSame('0612345678', $address->getTelephone(), 'PayPal sends no phone, so the shopper\'s stays');
    }

    public function testALoggedInCustomersOwnAddressIsKept(): void
    {
        $address = $this->address('Veronica', 'Costello', ['6146 Honey Bluff Parkway'], 'roni@example.com', '5552293');

        $this->orderUpdate(self::PAYPAL_DATA)->updateAddress($address, false);

        $this->assertSame('Veronica', $address->getFirstname());
        $this->assertSame(['6146 Honey Bluff Parkway'], $address->getStreet());
        $this->assertSame('roni@example.com', $address->getEmail());
    }

    public function testPlaceholdersAreReplacedForALoggedInCustomer(): void
    {
        $address = $this->address('PayPal', 'Customer', ['Pending'], 'pending@paypal.customer', '000-000-0000');

        $this->orderUpdate(self::PAYPAL_DATA)->updateAddress($address, false);

        $this->assertSame('Andrew', $address->getFirstname());
        $this->assertSame('Vozniuk', $address->getLastname());
        $this->assertSame(['Paul Krugerkade'], $address->getStreet());
    }

    /**
     * Callers that pass no guest flag keep the placeholder-only behaviour.
     */
    public function testWithoutTheGuestFlagOnlyPlaceholdersAreReplaced(): void
    {
        $address = $this->address('PaypalExpress', 'PaypalExpress', ['PaypalExpress'], 'shopper@example.com', '0612345678');

        $this->orderUpdate(self::PAYPAL_DATA)->updateAddress($address);

        $this->assertSame('PaypalExpress', $address->getFirstname());
    }

    public function testAGuestAddressIsLeftAloneWithoutPayPalData(): void
    {
        $address = $this->address('PayPal', 'Customer', ['Pending'], 'pending@paypal.customer', '000-000-0000');

        $this->orderUpdate([])->updateAddress($address, true);

        $this->assertSame('PayPal', $address->getFirstname());
        $this->assertSame(['Pending'], $address->getStreet());
    }

    /**
     * @param array $paypalData
     *
     * @return OrderUpdate
     */
    private function orderUpdate(array $paypalData): OrderUpdate
    {
        $parameters = [];
        foreach ($paypalData as $name => $value) {
            $parameters[] = ['Name' => $name, 'Value' => $value];
        }

        $response = $this->getFakeMock('Buckaroo\Transaction\Response\TransactionResponse')
            ->onlyMethods(['toArray'])
            ->getMock();
        $response->method('toArray')->willReturn(
            $parameters ? ['Services' => [['Name' => 'paypal', 'Parameters' => $parameters]]] : ['Services' => []]
        );

        $responseData = $this->getFakeMock('Buckaroo\Magento2\Api\Data\BuckarooResponseDataInterface')->getMock();
        $responseData->method('getResponse')->willReturn($response);

        return new OrderUpdate(
            $responseData,
            $this->getFakeMock('Buckaroo\Magento2\Logging\BuckarooLoggerInterface')->getMock()
        );
    }

    /**
     * @param string $firstname
     * @param string $lastname
     * @param array  $street
     * @param string $email
     * @param string $telephone
     *
     * @return DataObject
     */
    private function address(string $firstname, string $lastname, array $street, string $email, string $telephone): DataObject
    {
        return new DataObject([
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'street'    => $street,
            'email'     => $email,
            'telephone' => $telephone,
            'city'      => 'Groningen',
            'postcode'  => '9711AB',
            'country_id' => 'NL',
        ]);
    }
}

<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
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

    public function testAllPayPalFieldsAreSanitisedOntoTheAddress(): void
    {
        $address = $this->address('PayPal', 'Customer', ['Pending'], 'pending@paypal.customer', '000-000-0000');
        $address->setData('country_id', 'DE');

        $result = $this->orderUpdate([
            'payerFirstname' => '  Mary   Ann ',
            'payerLastname'  => "O'Brien#1",
            'payerEmail'     => ' mary@example.com ',
            'address_line_1' => 'Paul Krugerkade',
            'address_line_2' => '12a',
            'admin_area_2'   => ' Den  Haag ',
            'postal_code'    => ' 2511  CV ',
            'payerCountry'   => 'NL',
            'payerPhone'     => 'tel: +31 (0)6-1234',
        ])->updateAddress($address, true);

        $this->assertSame($address, $result);
        $this->assertSame('Mary Ann', $address->getFirstname());
        $this->assertSame("O'Brien", $address->getLastname());
        $this->assertSame('mary@example.com', $address->getEmail());
        $this->assertSame(['Paul Krugerkade 12a'], $address->getStreet());
        $this->assertSame('Den Haag', $address->getCity());
        $this->assertSame('2511 CV', $address->getPostcode());
        $this->assertSame('NL', $address->getCountryId());
        $this->assertSame('+31 (0)6-1234', $address->getTelephone());
    }

    /**
     * Each placeholder on its own makes a logged-in customer's address count as a placeholder address.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('singlePlaceholderProvider')]
    public function testASinglePlaceholderValueIsEnoughToReplaceTheAddress(
        string $firstname,
        array $street,
        string $telephone
    ): void {
        $address = $this->address($firstname, 'Costello', $street, 'roni@example.com', $telephone);

        $this->orderUpdate(self::PAYPAL_DATA)->updateAddress($address, false);

        $this->assertSame('Andrew', $address->getFirstname());
        $this->assertSame(['Paul Krugerkade'], $address->getStreet());
    }

    /**
     * @return array<string, array{string, array, string}>
     */
    public static function singlePlaceholderProvider(): array
    {
        return [
            'firstname unknown'      => ['unknown', ['6146 Honey Bluff Parkway'], '5552293'],
            'placeholder telephone'  => ['Veronica', ['6146 Honey Bluff Parkway'], '000-000-0000'],
            'placeholder street'     => ['Veronica', ['Pending', 'Second line'], '5552293'],
        ];
    }

    public function testABlankPayPalPhoneKeepsTheShoppersPhone(): void
    {
        $address = $this->address('PayPal', 'Customer', ['Pending'], 'pending@paypal.customer', '0612345678');

        $this->orderUpdate(['payerFirstname' => 'Andrew', 'payerPhone' => '   '])->updateAddress($address, true);

        $this->assertSame('0612345678', $address->getTelephone());
    }

    public function testAddressIsReadFromPushNotificationFields(): void
    {
        $address = $this->address('PayPal', 'Customer', ['Pending'], 'pending@paypal.customer', '000-000-0000');

        $this->orderUpdateFromResponse([
            'brq_statuscode'                    => '190',
            'brq_SERVICE_paypal_payerFirstname' => 'Andrew',
            'brq_SERVICE_paypal_payerCountry'   => 'BE',
        ])->updateAddress($address, true);

        $this->assertSame('Andrew', $address->getFirstname());
        $this->assertSame('BE', $address->getCountryId());
        $this->assertSame('Customer', $address->getLastname());
    }

    public function testAddressIsReadFromLegacyResponseParameters(): void
    {
        $address = $this->address('PayPal', 'Customer', ['Pending'], 'pending@paypal.customer', '000-000-0000');
        $this->orderUpdateFromResponse([
            'Services' => ['Service' => ['ResponseParameter' => [['Name' => 'payerFirstname', 'Value' => 'Andrew']]]],
        ])->updateAddress($address, true);

        $this->assertSame('Andrew', $address->getFirstname());
    }

    public function testParametersOfAnotherServiceAreNotUsed(): void
    {
        $address = $this->address('PayPal', 'Customer', ['Pending'], 'pending@paypal.customer', '000-000-0000');

        $this->orderUpdateFromResponse([
            'Services' => [
                ['Name' => 'ideal', 'Parameters' => [['Name' => 'payerFirstname', 'Value' => 'Other']]],
                ['Name' => 'paypal'],
            ],
        ])->updateAddress($address, true);

        $this->assertSame('PayPal', $address->getFirstname());
    }

    public function testFormatAddressDataSkipsIncompleteItems(): void
    {
        $this->assertSame(
            ['payerFirstname' => 'Andrew'],
            $this->orderUpdate([])->formatAddressData([
                ['Name' => 'payerFirstname', 'Value' => 'Andrew'],
                ['Name' => 'payerPhone'],
                ['Value' => 'orphan'],
            ])
        );
        $this->assertSame([], $this->orderUpdate([])->formatAddressData('not an array'));
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
     * @param array $raw response as returned by toArray()
     *
     * @return OrderUpdate
     */
    private function orderUpdateFromResponse(array $raw): OrderUpdate
    {
        $response = $this->getFakeMock('Buckaroo\Transaction\Response\TransactionResponse')
            ->onlyMethods(['toArray'])
            ->getMock();
        $response->method('toArray')->willReturn($raw);

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

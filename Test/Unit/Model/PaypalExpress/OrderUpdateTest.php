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
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;

class OrderUpdateTest extends TestCase
{
    public function testUpdateAddressSetsThePhoneFromPaypal(): void
    {
        $address = new DataObject(['telephone' => '000-000-0000']);

        $this->orderUpdate(['payerFirstname' => 'Jane', 'payerPhone' => '+31612345678'])->updateAddress($address);

        $this->assertSame('Jane', $address->getData('firstname'));
        $this->assertSame('+31612345678', $address->getData('telephone'));
    }

    public function testUpdateAddressKeepsThePhoneWhenPaypalSendsNone(): void
    {
        $address = new DataObject(['telephone' => '000-000-0000']);

        $this->orderUpdate(['payerFirstname' => 'Jane', 'payerPhone' => ' '])->updateAddress($address);

        $this->assertSame('000-000-0000', $address->getData('telephone'));
    }

    private function orderUpdate(array $responseFields): OrderUpdate
    {
        $parameters = [];
        foreach ($responseFields as $name => $value) {
            $parameter = new \stdClass();
            $parameter->Name = $name;
            $parameter->_ = $value;
            $parameters[] = $parameter;
        }

        $response = new \stdClass();
        $response->Services = new \stdClass();
        $response->Services->Service = new \stdClass();
        $response->Services->Service->ResponseParameter = $parameters;

        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('buckaroo_response')->willReturn([$response]);

        return new OrderUpdate($registry);
    }
}

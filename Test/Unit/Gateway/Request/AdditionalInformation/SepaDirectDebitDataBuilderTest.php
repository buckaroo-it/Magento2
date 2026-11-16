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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\AdditionalInformation;

use Buckaroo\Magento2\Gateway\Request\AdditionalInformation\SepaDirectDebitDataBuilder;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use PHPUnit\Framework\TestCase;

class SepaDirectDebitDataBuilderTest extends TestCase
{
    public function testIbanAndAccountHolderAreSentWithoutBic(): void
    {
        $result = (new SepaDirectDebitDataBuilder())->build($this->subject('NL91ABNA0417164300', 'J. Jansen', null));

        $this->assertSame(
            ['iban' => 'NL91ABNA0417164300', 'customer' => ['name' => 'J. Jansen']],
            $result
        );
    }

    public function testBicIsAddedNextToIbanAndAccountHolder(): void
    {
        $result = (new SepaDirectDebitDataBuilder())->build(
            $this->subject('DE89370400440532013000', 'Max Mustermann', 'COBADEFFXXX')
        );

        $this->assertSame(
            [
                'iban' => 'DE89370400440532013000',
                'customer' => ['name' => 'Max Mustermann'],
                'bic' => 'COBADEFFXXX',
            ],
            $result
        );
    }

    /**
     * @param string|null $iban
     * @param string|null $accountName
     * @param string|null $bic
     *
     * @return array
     */
    private function subject(?string $iban, ?string $accountName, ?string $bic): array
    {
        $payment = $this->createStub(InfoInterface::class);
        $payment->method('getAdditionalInformation')->willReturnMap([
            ['customer_bic', $bic],
            ['customer_iban', $iban],
            ['customer_account_name', $accountName],
        ]);

        $paymentDO = $this->createStub(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);

        return ['payment' => $paymentDO];
    }
}

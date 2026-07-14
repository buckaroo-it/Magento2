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

namespace Buckaroo\Magento2\Test\Unit\Block;

use Buckaroo\Magento2\Block\Info;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Buckaroo\Magento2\Service\LogoService;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\InfoInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InfoTest extends TestCase
{
    /** @var Info */
    private $block;

    /** @var InfoInterface&MockObject */
    private $paymentInfoMock;

    protected function setUp(): void
    {
        $this->block = new Info(
            $this->createMock(Context::class),
            $this->createMock(PaymentGroupTransaction::class),
            $this->createMock(GiftcardCollection::class),
            $this->createMock(LogoService::class),
            $this->createMock(UrlInterface::class)
        );

        $this->paymentInfoMock = $this->createMock(InfoInterface::class);
        $this->block->setData('info', $this->paymentInfoMock);
    }

    public function testGetPaymentTransactionReferenceReturnsTransactionKey(): void
    {
        $this->paymentInfoMock->method('getAdditionalInformation')
            ->with(BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY)
            ->willReturn('TEST123KEY');

        $this->assertSame('TEST123KEY', $this->block->getPaymentTransactionReference());
    }

    /**
     * @dataProvider missingTransactionKeyProvider
     *
     * @param mixed $storedValue
     */
    public function testGetPaymentTransactionReferenceReturnsNullWhenKeyIsMissing($storedValue): void
    {
        $this->paymentInfoMock->method('getAdditionalInformation')
            ->with(BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY)
            ->willReturn($storedValue);

        $this->assertNull($this->block->getPaymentTransactionReference());
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function missingTransactionKeyProvider(): array
    {
        return [
            'null value'       => [null],
            'empty string'     => [''],
            'whitespace only'  => ['   '],
            'non-string value' => [['TEST123KEY']],
        ];
    }
}

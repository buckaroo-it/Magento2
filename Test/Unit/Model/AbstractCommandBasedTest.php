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
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model;

use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Base test class for command-based request tests
 */
abstract class AbstractCommandBasedTest extends TestCase
{
    /**
     * @var MockObject|StoreManagerInterface
     */
    protected $storeManagerMock;

    /**
     * @var MockObject|CommandPoolInterface
     */
    protected $commandPoolMock;

    /**
     * @var MockObject|PaymentDataObjectFactory
     */
    protected $paymentDataObjectFactoryMock;

    /**
     * @var MockObject|StoreInterface
     */
    protected $storeMock;

    /**
     * @var MockObject|Quote
     */
    protected $quoteMock;

    /**
     * @var MockObject|Payment
     */
    protected $paymentMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->commandPoolMock  = $this->createMock(CommandPoolInterface::class);
        $this->paymentDataObjectFactoryMock = $this->createMock(PaymentDataObjectFactory::class);
        $this->storeMock  = $this->createMock(StoreInterface::class);

        // Build a Quote double that explicitly allows stubbing getPayment()
        // and (optionally) getGrandTotal().
        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])   // make getPayment stub-able
            ->addMethods(['getGrandTotal']) // add if your Magento version lacks it
            ->getMock();

        // Payment: allow chaining setAdditionalInformation()
        $this->paymentMock = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setAdditionalInformation', 'getAdditionalInformation'])
            ->getMock();
        $this->paymentMock->method('setAdditionalInformation')->willReturnSelf();

        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);
    }

    /**
     * Setup successful command execution
     */
    // AbstractCommandBasedTest.php
    protected function setupSuccessfulCommandExecution(string $commandKey, float $grandTotal = 100.0): void
    {
        $this->quoteMock->method('getGrandTotal')->willReturn($grandTotal);
        $this->quoteMock->method('getPayment')->willReturn($this->paymentMock);

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $this->paymentDataObjectFactoryMock->method('create')
            ->with($this->identicalTo($this->paymentMock))
            ->willReturn($paymentDO);

        $command = $this->createMock(CommandInterface::class);
        $this->commandPoolMock->method('get')->with($commandKey)->willReturn($command);

        $command->method('execute')->with([
            'payment' => $paymentDO,
            'amount'  => $grandTotal,
        ]);
    }
}

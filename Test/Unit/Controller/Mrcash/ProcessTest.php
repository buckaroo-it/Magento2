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

namespace Buckaroo\Magento2\Test\Unit\Controller\Mrcash;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Controller\Mrcash\Process;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
use Buckaroo\Magento2\Model\LockManagerWrapper;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\RequestPush\RequestPushFactory;
use Buckaroo\Magento2\Model\Service\Order as OrderService;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate;
use Buckaroo\Magento2\Service\SpamLimitService;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ProcessTest extends TestCase
{
    /**
     * @var Http|MockObject
     */
    private $requestMock;

    /**
     * @var FormKeyValidator|MockObject
     */
    private $formKeyValidatorMock;

    /**
     * @var TransactionRepositoryInterface|MockObject
     */
    private $transactionRepositoryMock;

    /**
     * @var Process
     */
    private Process $controller;

    protected function setUp(): void
    {
        $this->requestMock = $this->createMock(Http::class);
        $this->formKeyValidatorMock = $this->createMock(FormKeyValidator::class);
        $this->transactionRepositoryMock = $this->createMock(TransactionRepositoryInterface::class);

        $contextMock = $this->createMock(Context::class);
        $contextMock->method('getRequest')->willReturn($this->requestMock);
        $contextMock->method('getMessageManager')->willReturn($this->createMock(MessageManagerInterface::class));

        $requestPushFactoryMock = $this->createMock(RequestPushFactory::class);
        $requestPushFactoryMock->method('create')->willReturn($this->createMock(PushRequestInterface::class));

        $this->controller = new Process(
            $contextMock,
            $this->createMock(BuckarooLoggerInterface::class),
            $this->createMock(Quote::class),
            $this->createMock(AccountConfig::class),
            $this->createMock(OrderRequestService::class),
            $this->createMock(OrderStatusFactory::class),
            $this->createMock(CheckoutSession::class),
            $this->createMock(CustomerSession::class),
            $this->createMock(CustomerRepositoryInterface::class),
            $this->createMock(OrderService::class),
            $this->createMock(ManagerInterface::class),
            $this->createMock(Recreate::class),
            $requestPushFactoryMock,
            $this->createMock(SearchCriteriaBuilder::class),
            $this->createMock(OrderRepositoryInterface::class),
            $this->transactionRepositoryMock,
            $this->createMock(LockManagerWrapper::class),
            $this->createMock(SpamLimitService::class),
            $this->createMock(CartRepositoryInterface::class),
            $this->createMock(OrderPaymentRepositoryInterface::class),
            $this->formKeyValidatorMock
        );
    }

    public function testRejectsNonPostRequestBeforeTouchingAnyTransaction(): void
    {
        // Arrange: a GET request (the reported unauthenticated cancel vector)
        $this->requestMock->method('isPost')->willReturn(false);
        $this->formKeyValidatorMock->method('validate')->willReturn(true);

        // Assert: it never looks up a transaction/order
        $this->transactionRepositoryMock->expects($this->never())->method('getList');

        // Act
        $this->expectException(NotFoundException::class);
        $this->controller->execute();
    }

    public function testRejectsPostWithInvalidFormKey(): void
    {
        // Arrange: a POST that does not carry a valid form key
        $this->requestMock->method('isPost')->willReturn(true);
        $this->formKeyValidatorMock->method('validate')->willReturn(false);

        // Assert
        $this->transactionRepositoryMock->expects($this->never())->method('getList');

        // Act
        $this->expectException(NotFoundException::class);
        $this->controller->execute();
    }
}

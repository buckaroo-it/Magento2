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

namespace Buckaroo\Magento2\Test\Unit\Controller\Checkout;

use Buckaroo\Magento2\Controller\Checkout\SecondChance;
use Buckaroo\Magento2\Model\SecondChanceRepository;
use Buckaroo\Magento2\Logging\Log;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Controller\Result\Redirect;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SecondChanceTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = SecondChance::class;

    /** @var Log|\PHPUnit\Framework\MockObject\MockObject */
    private $logger;

    /** @var SecondChanceRepository|\PHPUnit\Framework\MockObject\MockObject */
    private $secondChanceRepository;

    /** @var Context|\PHPUnit\Framework\MockObject\MockObject */
    private $context;

    /** @var RequestInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $request;

    /** @var ManagerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $messageManager;

    /** @var CheckoutSession|\PHPUnit\Framework\MockObject\MockObject */
    private $checkoutSession;

    /** @var CustomerSession|\PHPUnit\Framework\MockObject\MockObject */
    private $customerSession;

    public function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->getFakeMock(Log::class)->getMock();
        $this->secondChanceRepository = $this->getFakeMock(SecondChanceRepository::class)->getMock();
        $this->checkoutSession = $this->getFakeMock(CheckoutSession::class)->getMock();
        $this->customerSession = $this->getFakeMock(CustomerSession::class)->getMock();
        $this->context = $this->getFakeMock(Context::class)->getMock();
        $this->request = $this->getFakeMock(RequestInterface::class)->getMock();
        $this->messageManager = $this->getFakeMock(ManagerInterface::class)->getMock();

        $this->context->expects($this->any())->method('getRequest')->willReturn($this->request);
        $this->context->expects($this->any())->method('getMessageManager')->willReturn($this->messageManager);
    }

    private function buildInstance(array $mockMethods = []): SecondChance
    {
        return $this->getMockBuilder(SecondChance::class)
            ->setConstructorArgs([
                $this->context,
                $this->logger,
                $this->secondChanceRepository,
                $this->checkoutSession,
                $this->customerSession,
            ])
            ->onlyMethods($mockMethods ?: ['handleRedirect'])
            ->getMock();
    }

    public function testExecuteWithValidToken()
    {
        $token = 'valid_token_123';

        $this->request->method('getParam')->with('token')->willReturn($token);
        $this->request->method('getParams')->willReturn(['token' => $token]);

        $this->secondChanceRepository->method('getSecondChanceByToken')->with($token);

        $quoteMock = $this->createMock(\Magento\Quote\Model\Quote::class);
        $quoteMock->method('getId')->willReturn(123);
        $this->checkoutSession->method('getQuote')->willReturn($quoteMock);

        $redirectMock = $this->getFakeMock(Redirect::class)->getMock();

        $instance = $this->buildInstance();
        $instance->expects($this->once())
            ->method('handleRedirect')
            ->with('checkout', ['_query' => [], '_fragment' => 'payment'])
            ->willReturn($redirectMock);

        $this->assertInstanceOf(Redirect::class, $instance->execute());
    }

    public function testExecuteForwardsOnlyUtmParams()
    {
        $token = 'valid_token_123';

        $this->request->method('getParam')->with('token')->willReturn($token);
        $this->request->method('getParams')->willReturn([
            'token'        => $token,
            '___store'     => 'nl',
            'utm_source'   => 'magento',
            'utm_medium'   => 'email',
            'utm_campaign' => 'transactional-email',
            'utm_content'  => 'buckaroo-payment-reminder-1',
            'fbclid'       => 'tracking123',
        ]);

        $this->secondChanceRepository->method('getSecondChanceByToken')->with($token);

        $quoteMock = $this->createMock(\Magento\Quote\Model\Quote::class);
        $quoteMock->method('getId')->willReturn(123);
        $this->checkoutSession->method('getQuote')->willReturn($quoteMock);

        $redirectMock = $this->getFakeMock(Redirect::class)->getMock();

        $instance = $this->buildInstance();
        $instance->expects($this->once())
            ->method('handleRedirect')
            ->with('checkout', [
                '_query' => [
                    'utm_source'   => 'magento',
                    'utm_medium'   => 'email',
                    'utm_campaign' => 'transactional-email',
                    'utm_content'  => 'buckaroo-payment-reminder-1',
                ],
                '_fragment' => 'payment',
            ])
            ->willReturn($redirectMock);

        $this->assertInstanceOf(Redirect::class, $instance->execute());
    }

    public function testExecuteWithInvalidToken()
    {
        $token = 'invalid_token_456';

        $this->request->method('getParam')->with('token')->willReturn($token);

        $this->secondChanceRepository->method('getSecondChanceByToken')
            ->with($token)
            ->willThrowException(new \Exception('Invalid token'));

        $this->logger->expects($this->once())
            ->method('addWarning')
            ->with('SecondChance: invalid or expired token');

        $this->messageManager->expects($this->once())
            ->method('addErrorMessage')
            ->with(__('Invalid or expired link. Please try again.'));

        $redirectMock = $this->getFakeMock(Redirect::class)->getMock();

        $instance = $this->buildInstance();
        $instance->expects($this->once())
            ->method('handleRedirect')
            ->with('checkout/cart', [])
            ->willReturn($redirectMock);

        $this->assertInstanceOf(Redirect::class, $instance->execute());
    }

    public function testExecuteWithNoToken()
    {
        $this->request->method('getParam')->with('token')->willReturn(null);

        $this->secondChanceRepository->expects($this->never())->method('getSecondChanceByToken');

        $this->logger->expects($this->once())
            ->method('addWarning')
            ->with('SecondChance: No token provided');

        $this->messageManager->expects($this->once())
            ->method('addErrorMessage')
            ->with(__('Invalid link. Please try again.'));

        $redirectMock = $this->getFakeMock(Redirect::class)->getMock();

        $instance = $this->buildInstance();
        $instance->expects($this->once())
            ->method('handleRedirect')
            ->with('checkout/cart', [])
            ->willReturn($redirectMock);

        $this->assertInstanceOf(Redirect::class, $instance->execute());
    }

    public function testExecuteWithEmptyToken()
    {
        $this->request->method('getParam')->with('token')->willReturn('');

        $this->secondChanceRepository->expects($this->never())->method('getSecondChanceByToken');

        $this->messageManager->expects($this->once())
            ->method('addErrorMessage')
            ->with(__('Invalid link. Please try again.'));

        $redirectMock = $this->getFakeMock(Redirect::class)->getMock();

        $instance = $this->buildInstance();
        $instance->expects($this->once())
            ->method('handleRedirect')
            ->with('checkout/cart', [])
            ->willReturn($redirectMock);

        $this->assertInstanceOf(Redirect::class, $instance->execute());
    }

    public function testExecuteWithNoQuoteAfterRestore()
    {
        $token = 'valid_token_123';

        $this->request->method('getParam')->with('token')->willReturn($token);
        $this->secondChanceRepository->method('getSecondChanceByToken')->with($token);

        $quoteMock = $this->createMock(\Magento\Quote\Model\Quote::class);
        $quoteMock->method('getId')->willReturn(null);
        $this->checkoutSession->method('getQuote')->willReturn($quoteMock);

        $this->logger->expects($this->once())
            ->method('addError')
            ->with('SecondChance: No quote in session after restoration');

        $this->messageManager->expects($this->once())
            ->method('addErrorMessage')
            ->with(__('Unable to restore your cart. Please try again or contact support.'));

        $redirectMock = $this->getFakeMock(Redirect::class)->getMock();

        $instance = $this->buildInstance();
        $instance->expects($this->once())
            ->method('handleRedirect')
            ->with('checkout/cart', [])
            ->willReturn($redirectMock);

        $this->assertInstanceOf(Redirect::class, $instance->execute());
    }
}

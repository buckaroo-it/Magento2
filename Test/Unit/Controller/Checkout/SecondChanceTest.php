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
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Controller\Result\Redirect;

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

    public function setUp(): void
    {
        parent::setUp();
        
        $this->logger = $this->getFakeMock(Log::class)->getMock();
        $this->secondChanceRepository = $this->getFakeMock(SecondChanceRepository::class)->getMock();
        $this->context = $this->getFakeMock(Context::class)->getMock();
        $this->request = $this->getFakeMock(RequestInterface::class)->getMock();
        $this->messageManager = $this->getFakeMock(ManagerInterface::class)->getMock();
        
        $this->context->expects($this->any())->method('getRequest')->willReturn($this->request);
        $this->context->expects($this->any())->method('getMessageManager')->willReturn($this->messageManager);
    }

    public function testExecuteWithValidToken()
    {
        $token = 'valid_token_123';
        
        $this->request->expects($this->once())
            ->method('getParam')
            ->with('token')
            ->willReturn($token);
        
        $secondChance = $this->getFakeMock(\Buckaroo\Magento2\Api\Data\SecondChanceInterface::class)->getMock();
        
        $this->secondChanceRepository->expects($this->once())
            ->method('getSecondChanceByToken')
            ->with($token)
            ->willReturn($secondChance);
        
        $this->messageManager->expects($this->once())
            ->method('addSuccessMessage')
            ->with(__('Your cart has been restored. You can now complete your purchase.'));

        $instance = $this->getInstance([
            'context' => $this->context,
            'logger' => $this->logger,
            'secondChanceRepository' => $this->secondChanceRepository,
        ]);
        
        // Mock the redirect response
        $redirectMock = $this->getFakeMock(Redirect::class)->getMock();
        $instance = $this->getMockBuilder(SecondChance::class)
            ->setConstructorArgs([
                $this->context,
                $this->logger,
                $this->secondChanceRepository
            ])
            ->setMethods(['handleRedirect'])
            ->getMock();
            
        $instance->expects($this->once())
            ->method('handleRedirect')
            ->with('checkout', ['_fragment' => 'payment'])
            ->willReturn($redirectMock);

        $result = $instance->execute();
        $this->assertInstanceOf(Redirect::class, $result);
    }

    public function testExecuteWithInvalidToken()
    {
        $token = 'invalid_token_456';
        
        $this->request->expects($this->once())
            ->method('getParam')
            ->with('token')
            ->willReturn($token);
        
        $this->secondChanceRepository->expects($this->once())
            ->method('getSecondChanceByToken')
            ->with($token)
            ->willThrowException(new \Exception('Invalid token'));
        
        $this->logger->expects($this->once())
            ->method('addError')
            ->with($this->stringContains('SecondChance token error'));
        
        $this->messageManager->expects($this->once())
            ->method('addErrorMessage')
            ->with(__('Invalid or expired link. Please try again.'));

        $instance = $this->getMockBuilder(SecondChance::class)
            ->setConstructorArgs([
                $this->context,
                $this->logger,
                $this->secondChanceRepository
            ])
            ->setMethods(['handleRedirect'])
            ->getMock();
            
        $redirectMock = $this->getFakeMock(Redirect::class)->getMock();
        $instance->expects($this->once())
            ->method('handleRedirect')
            ->with('checkout', ['_fragment' => 'payment'])
            ->willReturn($redirectMock);

        $result = $instance->execute();
        $this->assertInstanceOf(Redirect::class, $result);
    }

    public function testExecuteWithNoToken()
    {
        $this->request->expects($this->once())
            ->method('getParam')
            ->with('token')
            ->willReturn(null);
        
        $this->secondChanceRepository->expects($this->never())
            ->method('getSecondChanceByToken');
        
        $this->messageManager->expects($this->once())
            ->method('addErrorMessage')
            ->with(__('Invalid link. Please try again.'));

        $instance = $this->getMockBuilder(SecondChance::class)
            ->setConstructorArgs([
                $this->context,
                $this->logger,
                $this->secondChanceRepository
            ])
            ->setMethods(['handleRedirect'])
            ->getMock();
            
        $redirectMock = $this->getFakeMock(Redirect::class)->getMock();
        $instance->expects($this->once())
            ->method('handleRedirect')
            ->with('checkout', ['_fragment' => 'payment'])
            ->willReturn($redirectMock);

        $result = $instance->execute();
        $this->assertInstanceOf(Redirect::class, $result);
    }

    public function testExecuteWithEmptyToken()
    {
        $this->request->expects($this->once())
            ->method('getParam')
            ->with('token')
            ->willReturn('');
        
        $this->secondChanceRepository->expects($this->never())
            ->method('getSecondChanceByToken');
        
        $this->messageManager->expects($this->once())
            ->method('addErrorMessage')
            ->with(__('Invalid link. Please try again.'));

        $instance = $this->getMockBuilder(SecondChance::class)
            ->setConstructorArgs([
                $this->context,
                $this->logger,
                $this->secondChanceRepository
            ])
            ->setMethods(['handleRedirect'])
            ->getMock();
            
        $redirectMock = $this->getFakeMock(Redirect::class)->getMock();
        $instance->expects($this->once())
            ->method('handleRedirect')
            ->with('checkout', ['_fragment' => 'payment'])
            ->willReturn($redirectMock);

        $result = $instance->execute();
        $this->assertInstanceOf(Redirect::class, $result);
    }

    public function testHandleRedirect()
    {
        $path = 'checkout';
        $arguments = ['_fragment' => 'payment'];
        
        $redirectMock = $this->getFakeMock(\Magento\Framework\Controller\Result\RedirectFactory::class)->getMock();
        
        $instance = $this->getInstance([
            'context' => $this->context,
            'logger' => $this->logger,
            'secondChanceRepository' => $this->secondChanceRepository,
        ]);

        // Test that handleRedirect calls _redirect with correct parameters
        $instance = $this->getMockBuilder(SecondChance::class)
            ->setConstructorArgs([
                $this->context,
                $this->logger,
                $this->secondChanceRepository
            ])
            ->setMethods(['_redirect'])
            ->getMock();
            
        $instance->expects($this->once())
            ->method('_redirect')
            ->with($path, $arguments)
            ->willReturn($redirectMock);

        $result = $instance->handleRedirect($path, $arguments);
        $this->assertEquals($redirectMock, $result);
    }
} 
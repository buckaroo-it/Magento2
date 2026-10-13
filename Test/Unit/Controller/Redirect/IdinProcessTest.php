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

namespace Buckaroo\Magento2\Test\Unit\Controller\Redirect;

use Buckaroo\Magento2\Controller\Redirect\IdinProcess;
use Buckaroo\Magento2\Model\RequestPush\RequestPushFactory;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class IdinProcessTest extends BaseTest
{
    protected $instanceClass = IdinProcess::class;

    /**
     * An unsigned (forged) iDIN return must be rejected before any verification state is written
     * to the checkout session.
     */
    public function testForgedRequestWithInvalidSignatureDoesNotVerifyTheCustomer(): void
    {
        // Arrange: a push that carries data but fails signature validation
        $pushRequestMock = $this->getMockBuilder(PushRequestInterfaceStub::class)->getMock();
        $pushRequestMock->method('getData')->willReturn(['brq_primary_service' => 'IDIN']);
        $pushRequestMock->method('getOriginalRequest')->willReturn([]);
        $pushRequestMock->method('validate')->willReturn(false);

        $requestPushFactoryMock = $this->createMock(RequestPushFactory::class);
        $requestPushFactoryMock->method('createFromFormPost')->willReturn($pushRequestMock);
        // A return is a form post; the push request that also accepts a JSON body is never used
        $requestPushFactoryMock->expects($this->never())->method('create');

        // The checkout session must never receive an iDIN verification write on this path.
        $checkoutSessionMock = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();
        $checkoutSessionMock->expects($this->never())->method('__call');

        $instance = $this->getInstance([
            'context' => $this->buildContextMock(),
            'redirectFactory' => $this->buildRedirectFactoryMock(),
            'requestPushFactory' => $requestPushFactoryMock,
            'checkoutSession' => $checkoutSessionMock,
        ]);

        // Act
        $result = $instance->execute();

        // Assert
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    /**
     * Only the verification this session started is accepted.
     *
     * @param string|null $sessionNonce
     * @param string|null $requestNonce
     */
    #[DataProvider('foreignResultProvider')]
    public function testAResultThatThisSessionDidNotStartIsRejected(?string $sessionNonce, ?string $requestNonce): void
    {
        $instance = $this->buildVerifiedResultInstance($sessionNonce, $requestNonce, null, null);

        $instance->execute();

        $this->assertSame([], $this->checkoutSessionWrites);
        $this->assertSame([], $this->savedAttributes);
    }

    public static function foreignResultProvider(): array
    {
        return [
            'nonce of another session'     => ['session-nonce-0000000000000000000', 'other-nonce-00000000000000000000'],
            'no verification was started'  => [null, 'other-nonce-00000000000000000000'],
            'result carries no nonce'      => ['session-nonce-0000000000000000000', null],
        ];
    }

    public function testTheSessionThatStartedTheVerificationIsVerifiedOnce(): void
    {
        $nonce = 'session-nonce-0000000000000000000';
        $instance = $this->buildVerifiedResultInstance($nonce, $nonce, null, null);

        $instance->execute();

        $this->assertSame(['setCustomerIDIN', 'setCustomerIDINIsEighteenOrOlder'], $this->checkoutSessionWrites);
        $this->assertSame(['getBuckarooIdinNonce', 'unsBuckarooIdinNonce'], $this->nonceCalls, 'the nonce is read and cleared');
    }

    /**
     * @param string|null $signedCustomerId
     * @param int|null    $sessionCustomerId
     */
    #[DataProvider('otherAccountProvider')]
    public function testAResultIsNeverStoredOnAnotherAccount(?string $signedCustomerId, ?int $sessionCustomerId): void
    {
        $nonce = 'session-nonce-0000000000000000000';
        $instance = $this->buildVerifiedResultInstance($nonce, $nonce, $signedCustomerId, $sessionCustomerId);

        $instance->execute();

        $this->assertSame([], $this->checkoutSessionWrites);
        $this->assertSame([], $this->savedAttributes);
    }

    public static function otherAccountProvider(): array
    {
        return [
            'guest result while logged in'          => [null, 5],
            'empty guest id while logged in'        => ['', 5],
            'customer result without a login'       => ['5', null],
            'customer result for another customer'  => ['7', 5],
        ];
    }

    public function testACustomerResultIsStoredOnThatCustomer(): void
    {
        $nonce = 'session-nonce-0000000000000000000';
        $instance = $this->buildVerifiedResultInstance($nonce, $nonce, '5', 5);

        $instance->execute();

        $this->assertSame(['setCustomerIDIN', 'setCustomerIDINIsEighteenOrOlder'], $this->checkoutSessionWrites);
        $this->assertSame(['buckaroo_idin', 'buckaroo_idin_iseighteenorolder'], $this->savedAttributes);
    }

    /** @var string[] */
    private $checkoutSessionWrites = [];

    /** @var string[] */
    private $savedAttributes = [];

    /** @var string[] */
    private $nonceCalls = [];

    /**
     * A correctly signed iDIN result for an adult, as Buckaroo returns it.
     */
    private function buildVerifiedResultInstance(
        ?string $sessionNonce,
        ?string $requestNonce,
        ?string $signedCustomerId,
        ?int $sessionCustomerId
    ): IdinProcess {
        $pushRequestMock = $this->getMockBuilder(PushRequestInterfaceStub::class)->getMock();
        $pushRequestMock->method('getData')->willReturn(['brq_primary_service' => 'IDIN']);
        $pushRequestMock->method('getOriginalRequest')->willReturn([]);
        $pushRequestMock->method('validate')->willReturn(true);
        $pushRequestMock->method('hasPostData')->willReturnCallback(
            fn ($key, $value) => $key === 'primary_service' && $value === 'IDIN'
        );
        $pushRequestMock->method('getServiceIdinConsumerbin')->willReturn('BIN-123');
        $pushRequestMock->method('getServiceIdinIseighteenorolder')->willReturn('True');
        $pushRequestMock->method('getAdditionalInformation')->willReturnMap([
            ['idin_nonce', $requestNonce],
            ['idin_cid', $signedCustomerId],
        ]);

        $requestPushFactoryMock = $this->createMock(RequestPushFactory::class);
        $requestPushFactoryMock->method('createFromFormPost')->willReturn($pushRequestMock);
        // A return is a form post; the push request that also accepts a JSON body is never used
        $requestPushFactoryMock->expects($this->never())->method('create');

        $checkoutSessionMock = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call', 'restoreQuote'])
            ->getMock();
        $checkoutSessionMock->method('__call')->willReturnCallback(function ($method) use ($checkoutSessionMock) {
            $this->checkoutSessionWrites[] = $method;
            return $checkoutSessionMock;
        });

        $customerSessionMock = $this->getMockBuilder(CustomerSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCustomerId', '__call'])
            ->getMock();
        $customerSessionMock->method('getCustomerId')->willReturn($sessionCustomerId);
        $customerSessionMock->method('__call')->willReturnCallback(function ($method) use ($sessionNonce) {
            $this->nonceCalls[] = $method;
            return $method === 'getBuckarooIdinNonce' ? $sessionNonce : null;
        });

        $customer = $this->getFakeMock(\Magento\Customer\Model\Customer::class)
            ->onlyMethods(['setData'])
            ->getMock();
        $customerRegistry = $this->getFakeMock(\Magento\Customer\Model\CustomerRegistry::class)
            ->onlyMethods(['retrieve'])
            ->getMock();
        $customerRegistry->method('retrieve')->willReturn($customer);

        $customerResource = $this->getFakeMock(\Magento\Customer\Model\ResourceModel\Customer::class)
            ->onlyMethods(['saveAttribute'])
            ->getMock();
        $customerResource->method('saveAttribute')->willReturnCallback(function (...$args) use ($customerResource) {
            $this->savedAttributes[] = $args[1];
            return $customerResource;
        });
        $customerFactory = $this->getFakeMock(\Magento\Customer\Model\ResourceModel\CustomerFactory::class)
            ->onlyMethods(['create'])
            ->getMock();
        $customerFactory->method('create')->willReturn($customerResource);

        return $this->getInstance([
            'context' => $this->buildContextMock(),
            'redirectFactory' => $this->buildRedirectFactoryMock(),
            'requestPushFactory' => $requestPushFactoryMock,
            'checkoutSession' => $checkoutSessionMock,
            'customerSession' => $customerSessionMock,
            'customerRegistry' => $customerRegistry,
            'customerFactory' => $customerFactory,
        ]);
    }

    /**
     * @return Context
     */
    private function buildContextMock(): Context
    {
        $response = $this->getFakeMock(ResponseInterface::class)->getMock();

        $request = $this->getFakeMock(RequestInterface::class)->getMock();
        $request->method('getParams')->willReturn([]);

        $redirect = $this->getFakeMock(RedirectInterface::class)->getMock();
        $redirect->method('redirect');

        $messageManagerMock = $this->getFakeMock(ManagerInterface::class)->getMock();
        $messageManagerMock->method('addErrorMessage');

        $contextMock = $this->getFakeMock(Context::class)
            ->onlyMethods(['getRequest', 'getRedirect', 'getResponse', 'getMessageManager'])
            ->getMock();
        $contextMock->method('getRequest')->willReturn($request);
        $contextMock->method('getRedirect')->willReturn($redirect);
        $contextMock->method('getResponse')->willReturn($response);
        $contextMock->method('getMessageManager')->willReturn($messageManagerMock);

        return $contextMock;
    }

    /**
     * @return RedirectFactory
     */
    private function buildRedirectFactoryMock(): RedirectFactory
    {
        $redirectMock = $this->createMock(Redirect::class);
        $redirectFactoryMock = $this->createMock(RedirectFactory::class);
        $redirectFactoryMock->method('create')->willReturn($redirectMock);

        return $redirectFactoryMock;
    }
}

<?php
declare(strict_types=1);

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

namespace Buckaroo\Magento2\Test\Unit\Controller\CredentialsChecker;

use Buckaroo\Magento2\Controller\CredentialsChecker\GetToken;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcards;
use Buckaroo\Magento2\Service\CheckoutTokenRequestGuard;
use Buckaroo\Magento2\Service\Creditcard\HostedFieldsTokenClient;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The controller has to be built with mocks of all its constructor dependencies.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GetTokenTest extends BaseTest
{
    protected $instanceClass = GetToken::class;

    /**
     * @var mixed
     */
    private $capturedData;

    /**
     * @var mixed
     */
    private $capturedCode;

    /**
     * The endpoint must be POST-only and handle CSRF itself, so Magento's AJAX form-key bypass does not apply.
     */
    public function testControllerIsPostOnlyAndCsrfAware(): void
    {
        $instance = $this->buildInstance($this->createGuardMock(true));

        $this->assertInstanceOf(HttpPostActionInterface::class, $instance);
        $this->assertInstanceOf(CsrfAwareActionInterface::class, $instance);
    }

    /**
     * An unauthorized request is rejected with a 403 before credentials are decrypted
     * or the upstream auth endpoint is called.
     */
    public function testExecuteRejectsUnauthorizedRequest(): void
    {
        $tokenClient = $this->createMock(HostedFieldsTokenClient::class);
        $tokenClient->expects($this->never())->method('fetchToken');

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->never())->method('decrypt');

        $guard = $this->createGuardMock(false);
        $guard->expects($this->never())->method('consumeRateLimit');

        $this->buildInstance($guard, $tokenClient, $encryptor)->execute();

        $this->assertSame(403, $this->capturedCode);
        $this->assertTrue($this->capturedData['error']);
        $this->assertSame('Unauthorized request', $this->capturedData['message']);
    }

    /**
     * Once the rate limit is reached, no more upstream token calls are made.
     */
    public function testExecuteRejectsWhenRateLimited(): void
    {
        $tokenClient = $this->createMock(HostedFieldsTokenClient::class);
        $tokenClient->expects($this->never())->method('fetchToken');

        $this->buildInstance($this->createGuardMock(true, false), $tokenClient)->execute();

        $this->assertSame(429, $this->capturedCode);
        $this->assertTrue($this->capturedData['error']);
    }

    /**
     * An authorized request within budget returns the minted token.
     */
    public function testExecuteReturnsTokenForAuthorizedRequest(): void
    {
        $tokenClient = $this->createMock(HostedFieldsTokenClient::class);
        $tokenClient->expects($this->once())
            ->method('fetchToken')
            ->with('client-id', 'client-secret')
            ->willReturn(['access_token' => 'token', 'expires_in' => 840]);

        $this->buildInstance($this->createGuardMock(true), $tokenClient)->execute();

        $this->assertFalse($this->capturedData['error']);
        $this->assertSame('token', $this->capturedData['data']['access_token']);
        $this->assertSame(840, $this->capturedData['data']['expires_in']);
    }

    /**
     * A failed form-key check yields a JSON 403 response.
     */
    public function testCsrfValidationRejectsInvalidFormKey(): void
    {
        $request = $this->createMock(Http::class);

        $guard = $this->createMock(CheckoutTokenRequestGuard::class);
        $guard->expects($this->once())->method('hasValidFormKey')->with($request)->willReturn(false);

        $instance = $this->buildInstance($guard);

        $this->assertFalse($instance->validateForCsrf($request));
        $this->assertInstanceOf(InvalidRequestException::class, $instance->createCsrfValidationException($request));
        $this->assertSame(403, $this->capturedCode);
        $this->assertSame('Unauthorized request', $this->capturedData['message']);
    }

    /**
     * @param CheckoutTokenRequestGuard    $guard
     * @param HostedFieldsTokenClient|null $tokenClient
     * @param EncryptorInterface|null      $encryptor
     *
     * @return GetToken
     */
    private function buildInstance(
        CheckoutTokenRequestGuard $guard,
        ?HostedFieldsTokenClient $tokenClient = null,
        ?EncryptorInterface $encryptor = null
    ): GetToken {
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->createMock(Http::class));

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($this->createMock(StoreInterface::class));

        $config = $this->createMock(Creditcards::class);
        $config->method('getHostedFieldsClientId')->willReturn('enc-client-id');
        $config->method('getHostedFieldsClientSecret')->willReturn('enc-client-secret');
        $config->method('getSupportedServices')->willReturn(['Visa']);

        if ($encryptor === null) {
            $encryptor = $this->createMock(EncryptorInterface::class);
            $encryptor->method('decrypt')->willReturnCallback(static function ($value) {
                return ['enc-client-id' => 'client-id', 'enc-client-secret' => 'client-secret'][$value] ?? '';
            });
        }

        return new GetToken(
            $context,
            $this->createJsonFactoryMock(),
            $this->createMock(BuckarooLoggerInterface::class),
            $config,
            $encryptor,
            $storeManager,
            $tokenClient ?? $this->createMock(HostedFieldsTokenClient::class),
            $guard
        );
    }

    /**
     * @param bool $authorized
     * @param bool $withinRateLimit
     *
     * @return CheckoutTokenRequestGuard|\PHPUnit\Framework\MockObject\MockObject
     */
    private function createGuardMock(bool $authorized, bool $withinRateLimit = true)
    {
        $guard = $this->createMock(CheckoutTokenRequestGuard::class);
        $guard->method('isAuthorized')->willReturn($authorized);
        $guard->method('consumeRateLimit')->willReturn($withinRateLimit);

        return $guard;
    }

    /**
     * JsonFactory mock whose results record the data and HTTP code they receive.
     *
     * @return JsonFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private function createJsonFactoryMock()
    {
        $jsonResult = $this->createMock(Json::class);
        $jsonResult->method('setData')->willReturnCallback(function ($data) use ($jsonResult) {
            $this->capturedData = $data;
            return $jsonResult;
        });
        $jsonResult->method('setHttpResponseCode')->willReturnCallback(function ($code) use ($jsonResult) {
            $this->capturedCode = $code;
            return $jsonResult;
        });

        $factory = $this->createMock(JsonFactory::class);
        $factory->method('create')->willReturn($jsonResult);

        return $factory;
    }
}

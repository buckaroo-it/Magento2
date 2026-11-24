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

namespace Buckaroo\Magento2\Test\Unit\Controller\Clicktopay;

use Buckaroo\Magento2\Controller\Clicktopay\Token;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Clicktopay as ClicktopayConfig;
use Buckaroo\Magento2\Service\CheckoutTokenRequestGuard;
use Buckaroo\Magento2\Service\OauthTokenService;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Encryption\EncryptorInterface;

class TokenTest extends BaseTest
{
    protected $instanceClass = Token::class;

    /**
     * Decrypted credentials must be passed to the token service with the
     * Click to Pay scope, and its payload returned as-is.
     */
    public function testExecuteReturnsTokenFromService(): void
    {
        $tokenService = $this->createMock(OauthTokenService::class);
        $tokenService->expects($this->once())
            ->method('getToken')
            ->with('client-id', 'client-secret', 'clicktopay:save')
            ->willReturn(['access_token' => 'service-token', 'expires_in' => 3540]);

        $capturedData = null;
        $resultJsonFactory = $this->createJsonFactoryMock($capturedData);

        $instance = $this->getInstance([
            'resultJsonFactory' => $resultJsonFactory,
            'request'           => $this->createMock(Http::class),
            'requestGuard'      => $this->createGuardMock(true),
            'config'            => $this->createConfigMock(),
            'tokenService'      => $tokenService,
            'logger'            => $this->createMock(BuckarooLoggerInterface::class),
            'encryptor'         => $this->createEncryptorMock(),
        ]);

        $instance->execute();

        $this->assertSame('service-token', $capturedData['access_token']);
        $this->assertSame(3540, $capturedData['expires_in']);
    }

    /**
     * A token service failure must map to an error response.
     */
    public function testExecuteReturnsErrorWhenServiceFails(): void
    {
        $tokenService = $this->createMock(OauthTokenService::class);
        $tokenService->method('getToken')->willReturn(null);

        $capturedData = null;
        $resultJsonFactory = $this->createJsonFactoryMock($capturedData);

        $instance = $this->getInstance([
            'resultJsonFactory' => $resultJsonFactory,
            'request'           => $this->createMock(Http::class),
            'requestGuard'      => $this->createGuardMock(true),
            'config'            => $this->createConfigMock(),
            'tokenService'      => $tokenService,
            'logger'            => $this->createMock(BuckarooLoggerInterface::class),
            'encryptor'         => $this->createEncryptorMock(),
        ]);

        $instance->execute();

        $this->assertArrayHasKey('error', $capturedData);
    }

    /**
     * Missing credentials must produce an error response without hitting the service.
     */
    public function testExecuteReturnsErrorWhenCredentialsMissing(): void
    {
        $config = $this->createMock(ClicktopayConfig::class);
        $config->method('getClientId')->willReturn('');
        $config->method('getClientSecret')->willReturn('');

        $tokenService = $this->createMock(OauthTokenService::class);
        $tokenService->expects($this->never())->method('getToken');

        $capturedData = null;
        $resultJsonFactory = $this->createJsonFactoryMock($capturedData);

        $instance = $this->getInstance([
            'resultJsonFactory' => $resultJsonFactory,
            'request'           => $this->createMock(Http::class),
            'requestGuard'      => $this->createGuardMock(true),
            'config'            => $config,
            'tokenService'      => $tokenService,
            'logger'            => $this->createMock(BuckarooLoggerInterface::class),
            'encryptor'         => $this->createEncryptorMock(),
        ]);

        $instance->execute();

        $this->assertArrayHasKey('error', $capturedData);
    }

    /**
     * A credential that fails to decrypt must be treated as missing.
     */
    public function testExecuteReturnsErrorWhenDecryptionFails(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willThrowException(new \Exception('decryption failed'));

        $tokenService = $this->createMock(OauthTokenService::class);
        $tokenService->expects($this->never())->method('getToken');

        $capturedData = null;
        $resultJsonFactory = $this->createJsonFactoryMock($capturedData);

        $instance = $this->getInstance([
            'resultJsonFactory' => $resultJsonFactory,
            'request'           => $this->createMock(Http::class),
            'requestGuard'      => $this->createGuardMock(true),
            'config'            => $this->createConfigMock(),
            'tokenService'      => $tokenService,
            'logger'            => $this->createMock(BuckarooLoggerInterface::class),
            'encryptor'         => $encryptor,
        ]);

        $instance->execute();

        $this->assertArrayHasKey('error', $capturedData);
    }

    /**
     * An unauthenticated request (no valid form key / no owning checkout session) must be
     * rejected with a 403 before the merchant credentials are ever used.
     */
    public function testExecuteRejectsUntrustedRequest(): void
    {
        $tokenService = $this->createMock(OauthTokenService::class);
        $tokenService->expects($this->never())->method('getToken');

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->never())->method('decrypt');

        $guard = $this->createGuardMock(false);
        $guard->expects($this->never())->method('consumeRateLimit');

        $capturedData = null;
        $capturedCode = null;
        $resultJsonFactory = $this->createJsonFactoryMock($capturedData, $capturedCode);

        $instance = $this->getInstance([
            'resultJsonFactory' => $resultJsonFactory,
            'request'           => $this->createMock(Http::class),
            'requestGuard'      => $guard,
            'config'            => $this->createConfigMock(),
            'tokenService'      => $tokenService,
            'logger'            => $this->createMock(BuckarooLoggerInterface::class),
            'encryptor'         => $encryptor,
        ]);

        $instance->execute();

        $this->assertSame(403, $capturedCode);
        $this->assertSame('Unauthorized request', $capturedData['error']);
    }

    /**
     * Once the session/IP budget is used up, no further upstream token calls are made.
     */
    public function testExecuteRejectsWhenRateLimited(): void
    {
        $tokenService = $this->createMock(OauthTokenService::class);
        $tokenService->expects($this->never())->method('getToken');

        $capturedData = null;
        $capturedCode = null;
        $resultJsonFactory = $this->createJsonFactoryMock($capturedData, $capturedCode);

        $instance = $this->getInstance([
            'resultJsonFactory' => $resultJsonFactory,
            'request'           => $this->createMock(Http::class),
            'requestGuard'      => $this->createGuardMock(true, false),
            'config'            => $this->createConfigMock(),
            'tokenService'      => $tokenService,
            'logger'            => $this->createMock(BuckarooLoggerInterface::class),
            'encryptor'         => $this->createEncryptorMock(),
        ]);

        $instance->execute();

        $this->assertSame(429, $capturedCode);
        $this->assertArrayHasKey('error', $capturedData);
    }

    /**
     * The form key must be validated even for AJAX requests (which Magento's default
     * CSRF check would otherwise let through), and a failure must yield a JSON 403.
     */
    public function testCsrfValidationDelegatesToFormKeyCheck(): void
    {
        $request = $this->createMock(Http::class);

        $guard = $this->createMock(CheckoutTokenRequestGuard::class);
        $guard->expects($this->once())->method('hasValidFormKey')->with($request)->willReturn(false);

        $capturedData = null;
        $capturedCode = null;

        $instance = $this->getInstance([
            'resultJsonFactory' => $this->createJsonFactoryMock($capturedData, $capturedCode),
            'request'           => $request,
            'requestGuard'      => $guard,
        ]);

        $this->assertFalse($instance->validateForCsrf($request));
        $this->assertInstanceOf(InvalidRequestException::class, $instance->createCsrfValidationException($request));
        $this->assertSame(403, $capturedCode);
        $this->assertSame('Unauthorized request', $capturedData['error']);
    }

    /**
     * Request guard mock.
     *
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
     * Config mock returning encrypted credentials.
     *
     * @return ClicktopayConfig|\PHPUnit\Framework\MockObject\MockObject
     */
    private function createConfigMock()
    {
        $config = $this->createMock(ClicktopayConfig::class);
        $config->method('getClientId')->willReturn('enc-client-id');
        $config->method('getClientSecret')->willReturn('enc-client-secret');

        return $config;
    }

    /**
     * Encryptor mock that decrypts the credential ciphertexts.
     *
     * @return EncryptorInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function createEncryptorMock()
    {
        $map = [
            'enc-client-id'     => 'client-id',
            'enc-client-secret' => 'client-secret',
        ];

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnCallback(function ($value) use ($map) {
            return $map[$value] ?? '';
        });

        return $encryptor;
    }

    /**
     * JsonFactory mock whose Json result captures the data passed to setData.
     *
     * @param mixed $capturedData populated by reference on setData
     * @param mixed $capturedCode populated by reference on setHttpResponseCode
     *
     * @return JsonFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private function createJsonFactoryMock(&$capturedData, &$capturedCode = null)
    {
        $jsonResult = $this->createMock(Json::class);
        $jsonResult->method('setData')->willReturnCallback(
            function ($data) use (&$capturedData, $jsonResult) {
                $capturedData = $data;
                return $jsonResult;
            }
        );
        $jsonResult->method('setHttpResponseCode')->willReturnCallback(
            function ($code) use (&$capturedCode, $jsonResult) {
                $capturedCode = $code;
                return $jsonResult;
            }
        );

        $factory = $this->createMock(JsonFactory::class);
        $factory->method('create')->willReturn($jsonResult);

        return $factory;
    }
}

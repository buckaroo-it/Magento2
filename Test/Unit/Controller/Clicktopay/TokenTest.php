<?php
declare(strict_types=1);

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

namespace Buckaroo\Magento2\Test\Unit\Controller\Clicktopay;

use Buckaroo\Magento2\Controller\Clicktopay\Token;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Clicktopay as ClicktopayConfig;
use Buckaroo\Magento2\Service\OauthTokenService;
use Buckaroo\Magento2\Test\BaseTest;
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
            'config'            => $this->createConfigMock(),
            'tokenService'      => $tokenService,
            'logger'            => $this->createMock(BuckarooLoggerInterface::class),
            'encryptor'         => $encryptor,
        ]);

        $instance->execute();

        $this->assertArrayHasKey('error', $capturedData);
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
     *
     * @return JsonFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private function createJsonFactoryMock(&$capturedData)
    {
        $jsonResult = $this->createMock(Json::class);
        $jsonResult->method('setData')->willReturnCallback(
            function ($data) use (&$capturedData, $jsonResult) {
                $capturedData = $data;
                return $jsonResult;
            }
        );
        $jsonResult->method('setHttpResponseCode')->willReturnSelf();

        $factory = $this->createMock(JsonFactory::class);
        $factory->method('create')->willReturn($jsonResult);

        return $factory;
    }
}

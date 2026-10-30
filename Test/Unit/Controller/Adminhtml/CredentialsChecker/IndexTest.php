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

namespace Buckaroo\Magento2\Test\Unit\Controller\Adminhtml\CredentialsChecker;

use Buckaroo\Magento2\Controller\Adminhtml\CredentialsChecker\Index;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    /**
     * @var Http|MockObject
     */
    private $requestMock;

    /**
     * @var BuckarooAdapter|MockObject
     */
    private $clientMock;

    /**
     * @var Json|MockObject
     */
    private $jsonResultMock;

    /**
     * @var Index
     */
    private Index $controller;

    protected function setUp(): void
    {
        $this->requestMock = $this->createMock(Http::class);
        $this->clientMock = $this->createMock(BuckarooAdapter::class);

        $this->jsonResultMock = $this->createMock(Json::class);
        $this->jsonResultMock->method('setData')->willReturnSelf();

        $resultFactoryMock = $this->createMock(ResultFactory::class);
        $resultFactoryMock->method('create')->with(ResultFactory::TYPE_JSON)->willReturn($this->jsonResultMock);

        $contextMock = $this->createMock(Context::class);
        $contextMock->method('getRequest')->willReturn($this->requestMock);
        $contextMock->method('getResultFactory')->willReturn($resultFactoryMock);

        $this->controller = new Index(
            $contextMock,
            $this->createMock(EncryptorInterface::class),
            $this->clientMock,
            $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class),
            $this->createMock(\Buckaroo\Magento2\Service\Config\AdminConfigScope::class)
        );
    }

    public function testIsGuardedByTheBuckarooConfigurationAclResource(): void
    {
        $this->assertSame('Buckaroo_Magento2::configuration', Index::ADMIN_RESOURCE);
    }

    public function testDoesNotCallTheGatewayWhenCredentialsAreMissing(): void
    {
        // Arrange
        $this->requestMock->method('getParam')->willReturnMap([
            ['secretKey', '', ''],
            ['merchantKey', '', ''],
        ]);

        // Assert: no live credential round-trip is attempted
        $this->clientMock->expects($this->never())->method('confirmCredential');
        $this->jsonResultMock->expects($this->once())
            ->method('setData')
            ->with($this->callback(static fn ($data) => $data['success'] === false));

        // Act
        $this->assertSame($this->jsonResultMock, $this->controller->execute());
    }

    public function testValidatesSuppliedCredentialsAgainstTheGateway(): void
    {
        // Arrange
        $this->requestMock->method('getParam')->willReturnMap([
            ['secretKey', '', 'plain-secret'],
            ['merchantKey', '', 'plain-merchant'],
        ]);
        $this->clientMock->expects($this->once())
            ->method('confirmCredential')
            ->with('plain-merchant', 'plain-secret')
            ->willReturn(true);

        // Assert
        $this->jsonResultMock->expects($this->once())
            ->method('setData')
            ->with(['success' => true]);

        // Act
        $this->assertSame($this->jsonResultMock, $this->controller->execute());
    }

    /**
     * All-asterisk input means "the stored key"; it is read at exactly the scope the form shows,
     * not at the website's default store view, which may carry its own override.
     */
    public function testMaskedCredentialsAreReadAtTheEditedScope(): void
    {
        $this->requestMock->method('getParam')->willReturnMap([
            ['secretKey', '', '******'],
            ['merchantKey', '', '******'],
        ]);

        $scope = $this->createMock(\Buckaroo\Magento2\Service\Config\AdminConfigScope::class);
        $scope->method('getScope')->willReturn(['websites', 2]);

        $scopeConfig = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            ['buckaroo_magento2/account/secret_key', 'websites', 2, 'enc-secret'],
            ['buckaroo_magento2/account/merchant_key', 'websites', 2, 'enc-merchant'],
        ]);

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnCallback(static fn ($value) => 'plain-' . $value);

        $contextMock = $this->createMock(Context::class);
        $contextMock->method('getRequest')->willReturn($this->requestMock);
        $resultFactoryMock = $this->createMock(ResultFactory::class);
        $resultFactoryMock->method('create')->willReturn($this->jsonResultMock);
        $contextMock->method('getResultFactory')->willReturn($resultFactoryMock);

        $this->clientMock->expects($this->once())
            ->method('confirmCredential')
            ->with('plain-enc-merchant', 'plain-enc-secret')
            ->willReturn(true);

        (new Index($contextMock, $encryptor, $this->clientMock, $scopeConfig, $scope))->execute();
    }
}

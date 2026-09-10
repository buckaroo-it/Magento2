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
use Buckaroo\Magento2\Model\ConfigProvider\Account;
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
            $this->createMock(Account::class),
            $this->clientMock
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
}

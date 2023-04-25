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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Request\BasicParameter\DescriptionDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;

class DescriptionDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var MockObject|Account
     */
    private $configProviderAccountMock;

    /**
     * @var DescriptionDataBuilder
     */
    private $descriptionDataBuilder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->configProviderAccountMock = $this->getMockBuilder(Account::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->descriptionDataBuilder = new DescriptionDataBuilder(
            $this->configProviderAccountMock
        );
    }

    public function testBuild(): void
    {
        $store = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderMock->expects($this->once())->method('getStore')->willReturn($store);

        $this->configProviderAccountMock->expects($this->once())
            ->method('getParsedLabel')
            ->with($store, $this->orderMock)
            ->willReturn('Sample description');

        $result = $this->descriptionDataBuilder->build(['payment' => $this->getPaymentDOMock()]);
        $this->assertEquals(['description' => 'Sample description'], $result);
    }
}
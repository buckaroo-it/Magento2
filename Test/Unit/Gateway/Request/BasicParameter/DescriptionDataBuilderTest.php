<?php
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
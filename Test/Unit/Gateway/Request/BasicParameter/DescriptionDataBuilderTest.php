<?php
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

        $this->configProviderAccountMock = $this->createMock(Account::class);

        $this->descriptionDataBuilder = new DescriptionDataBuilder(
            $this->configProviderAccountMock
        );
    }

    public function testBuild(): void
    {
        $store = $this->createMock(Store::class);

        $this->orderMock->method('getStore')->willReturn($store);

        $this->configProviderAccountMock->method('getParsedLabel')
            ->with($store, $this->orderMock)
            ->willReturn('Sample description');

        $result = $this->descriptionDataBuilder->build(['payment' => $this->getPaymentDOMock()]);
        $this->assertEquals(['description' => 'Sample description'], $result);
    }
}

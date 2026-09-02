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

use Buckaroo\Magento2\Gateway\Request\BasicParameter\PushUrlDataBuilder;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;

class PushUrlDataBuilderTest extends TestCase
{
    /**
     * @var PushUrlDataBuilder
     */
    private $pushUrlDataBuilder;

    /**
     * @var UrlInterface|MockObject
     */
    private $urlBuilderMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);

        $this->pushUrlDataBuilder = new PushUrlDataBuilder($this->urlBuilderMock);
    }

    public function testBuild(): void
    {
        $pushUrl = 'https://buckaroo.com/rest/V1/buckaroo/push';
        $pushUrlFailure = 'https://buckaroo.com/rest/V1/buckaroo/push';

        $this->urlBuilderMock->method('getDirectUrl')
            ->willReturnMap(
                [
                    ['rest/V1/buckaroo/push', [], $pushUrl],
                    ['rest/V1/buckaroo/push', [], $pushUrlFailure],
                ]
            );

        $result = $this->pushUrlDataBuilder->build([]);

        $this->assertEquals(
            [
                'pushURL'        => $pushUrl,
                'pushURLFailure' => $pushUrlFailure
            ],
            $result
        );
    }
}

<?php

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
    private PushUrlDataBuilder $pushUrlDataBuilder;

    /**
     * @var UrlInterface|MockObject
     */
    private $urlBuilderMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->urlBuilderMock = $this->getMockBuilder(UrlInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->pushUrlDataBuilder = new PushUrlDataBuilder($this->urlBuilderMock);
    }

    public function testBuild(): void
    {
        $pushUrl = 'https://buckaroo.com/rest/V1/buckaroo/push';
        $pushUrlFailure = 'https://buckaroo.com/rest/V1/buckaroo/push';

        $this->urlBuilderMock->expects($this->exactly(2))
            ->method('getDirectUrl')
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
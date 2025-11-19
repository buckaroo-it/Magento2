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

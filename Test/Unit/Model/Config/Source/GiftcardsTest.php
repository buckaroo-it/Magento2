<?php
// phpcs:ignoreFile
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
namespace Buckaroo\Magento2\Test\Unit\Model\Config\Source;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Model\Config\Source\Giftcards;
use Buckaroo\Magento2\Test\BaseTest;

class GiftcardsTest extends BaseTest
{
    protected $instanceClass = Giftcards::class;

    /**
     * @return array
     */
    public static function toOptionArrayProvider()
    {
        return [
            'no giftcards' => [
                [],
                [
                    [
                        'value' => '',
                        'label' => __('You have not yet added any giftcards')
                    ]
                ]
            ],
            'single giftcard' => [
                [
                    [
                        'servicecode' => 'code1',
                        'label' => 'giftcard 1'
                    ]
                ],
                [
                    [
                        'value' => 'code1',
                        'label' => 'giftcard 1'
                    ]
                ]
            ],
            'multiple giftcard' => [
                [
                    [
                        'servicecode' => 'code2',
                        'label' => 'giftcard 2'
                    ],
                    [
                        'servicecode' => 'code3',
                        'label' => 'giftcard 3'
                    ],
                    [
                        'servicecode' => 'code4',
                        'label' => 'giftcard 4'
                    ]
                ],
                [
                    [
                        'value' => 'code2',
                        'label' => 'giftcard 2'
                    ],
                    [
                        'value' => 'code3',
                        'label' => 'giftcard 3'
                    ],
                    [
                        'value' => 'code4',
                        'label' => 'giftcard 4'
                    ]
                ]
            ]
        ];
    }

    /**
     * @param $giftcardData
     * @param $expected
     *
     */
    #[DataProvider('toOptionArrayProvider')]
    public function testToOptionArray($giftcardData, $expected)
    {
        $sortOrderBuilderMock = $this->getFakeMock(\Magento\Framework\Api\SortOrderBuilder::class)
            ->onlyMethods(['setField', 'setAscendingDirection', 'create'])
            ->getMock();
        $sortOrderBuilderMock->method('setField')->with('label')->willReturnSelf();
        $sortOrderBuilderMock->method('setAscendingDirection')->willReturnSelf();
        $sortOrderBuilderMock->method('create')->willReturnSelf();

        $searchCriteriaMock = $this->getFakeMock(\Magento\Framework\Api\SearchCriteria::class)->getMock();

        $searchCriteriaBuilderMock = $this->getFakeMock(\Magento\Framework\Api\SearchCriteriaBuilder::class)
            ->onlyMethods(['create'])
            ->getMock();
        $searchCriteriaBuilderMock->method('create')->willReturn($searchCriteriaMock);

        $modelsResult = [];

        foreach ($giftcardData as $giftcard) {
            $modelMock = $this->getFakeMock(\Buckaroo\Magento2\Api\Data\GiftcardInterface::class)->getMock();
            $modelMock->method('getServicecode')->willReturn($giftcard['servicecode']);
            $modelMock->method('getLabel')->willReturn($giftcard['label']);
            $modelsResult[] = $modelMock;
        }

        $searchResult = $this->getObject(\Magento\Framework\Api\SearchResults::class);
        $searchResult->setItems($modelsResult);
        $searchResult->setTotalCount(count($modelsResult));

        $giftcardRepositoryMock = $this->getFakeMock(\Buckaroo\Magento2\Api\GiftcardRepositoryInterface::class)->getMock();
        $giftcardRepositoryMock->method('getList')
            ->with($searchCriteriaMock)
            ->willReturn($searchResult);

        $instance = $this->getInstance([
            'sortOrderBuilder' => $sortOrderBuilderMock,
            'searchCriteriaBuilder' => $searchCriteriaBuilderMock,
            'giftcardRepository' => $giftcardRepositoryMock
        ]);
        $result = $instance->toOptionArray();

        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }
}

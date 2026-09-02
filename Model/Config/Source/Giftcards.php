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

namespace Buckaroo\Magento2\Model\Config\Source;

use Buckaroo\Magento2\Api\Data\GiftcardInterface;
use Buckaroo\Magento2\Api\GiftcardRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Data\OptionSourceInterface;

class Giftcards implements OptionSourceInterface
{
    /**
     * @var SortOrderBuilder
     */
    private $sortOrderBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var GiftcardRepositoryInterface
     */
    private $giftcardRepository;

    /**
     * Constructor
     *
     * @param SortOrderBuilder $sortOrderBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param GiftcardRepositoryInterface $giftcardRepository
     */
    public function __construct(
        SortOrderBuilder $sortOrderBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        GiftcardRepositoryInterface $giftcardRepository
    ) {
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->giftcardRepository = $giftcardRepository;
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $giftcardData = $this->getGiftcardData();

        $options = [];

        if (count($giftcardData) <= 0) {
            $options[] = [
                'value' => '',
                'label' => __('You have not yet added any giftcards')
            ];

            return $options;
        }

        /** @var GiftcardInterface $model */
        foreach ($giftcardData as $model) {
            $options[] = [
                'value' => $model->getServicecode(),
                'label' => $model->getLabel()
            ];
        }

        return $options;
    }

    /**
     * Get a list of all stored certificates
     *
     * @return array
     */
    protected function getGiftcardData(): array
    {
        $sortOrder = $this->sortOrderBuilder->setField('label')->setAscendingDirection();
        $searchCriteria = $this->searchCriteriaBuilder->addSortOrder($sortOrder->create());
        $list = $this->giftcardRepository->getList($searchCriteria->create());

        if (!$list->getTotalCount()) {
            return [];
        }

        return $list->getItems();
    }
}

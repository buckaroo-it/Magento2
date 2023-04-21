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
    private SortOrderBuilder $sortOrderBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var GiftcardRepositoryInterface
     */
    private GiftcardRepositoryInterface $giftcardRepository;

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

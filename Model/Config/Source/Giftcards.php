<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
namespace TIG\Buckaroo\Model\Config\Source;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Option\ArrayInterface;
use TIG\Buckaroo\Api\GiftcardRepositoryInterface;

class Giftcards implements ArrayInterface
{
    /** @var SortOrderBuilder */
    private $sortOrderBuilder;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var GiftcardRepositoryInterface */
    private $giftcardRepository;

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
    public function toOptionArray()
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

        /** @var \TIG\Buckaroo\Api\Data\GiftcardInterface $model */
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
    protected function getGiftcardData()
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

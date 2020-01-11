<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
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

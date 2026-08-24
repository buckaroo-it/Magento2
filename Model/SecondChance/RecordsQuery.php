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

namespace Buckaroo\Magento2\Model\SecondChance;

use Buckaroo\Magento2\Model\ResourceModel\SecondChance\CollectionFactory;

class RecordsQuery
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Check whether SecondChance records match all provided filters.
     *
     * @param array $filters
     * @return bool
     */
    public function hasRecords(array $filters): bool
    {
        $collection = $this->collectionFactory->create();
        foreach ($filters as $field => $condition) {
            $collection->addFieldToFilter($field, $condition);
        }

        return $collection->getSize() > 0;
    }
}

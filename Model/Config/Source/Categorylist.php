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

namespace Buckaroo\Magento2\Model\Config\Source;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class Categorylist implements OptionSourceInterface
{
    /**
     * @var array
     */
    protected $categoriesCache = [];
    /**
     * @var CategoryFactory
     */
    protected $categoryFactory;
    /**
     * @var CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @param CategoryFactory   $categoryFactory
     * @param CollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        CategoryFactory $categoryFactory,
        CollectionFactory $categoryCollectionFactory
    ) {
        $this->categoryFactory = $categoryFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * Return array of product categories as value-label pairs
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $arr = $this->toArray();
        $ret = [];

        foreach ($arr as $key => $value) {
            $ret[] = [
                'value' => $key,
                'label' => $value,
            ];
        }

        return $ret;
    }

    /**
     * Transform collection into array
     *
     * @return array
     */
    private function toArray(): array
    {
        $categories = $this->getCategoryCollection(true, false, false, false);

        $catagoryList = [];
        foreach ($categories as $category) {
            $catagoryList[$category->getEntityId()] = __(
                $this->getParentName($category->getPath()) . $category->getName()
            );
        }

        return $catagoryList;
    }

    /**
     * Get collection of product categories
     *
     * @param  bool  $isActive
     * @param  bool  $level
     * @param  bool  $sortBy
     * @param  bool  $pageSize
     * @return mixed
     */
    public function getCategoryCollection($isActive = true, $level = false, $sortBy = false, $pageSize = false)
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('*');

        // select only active categories
        if ($isActive) {
            $collection->addIsActiveFilter();
        }

        // select categories of certain level
        if ($level) {
            $collection->addLevelFilter($level);
        }

        // sort categories by some value
        if ($sortBy) {
            $collection->addOrderField($sortBy);
        }

        // select certain number of categories
        if ($pageSize) {
            $collection->setPageSize($pageSize);
        }

        return $collection;
    }

    /**
     * Get parent category name
     *
     * @param  string $path
     * @return string
     */
    private function getParentName($path = '')
    {
        $parentName = '';
        $rootCats = [1, 2];

        $catTree = explode("/", $path);
        array_pop($catTree);

        if ($catTree && (count($catTree) > count($rootCats))) {
            $categoryObj = $this->categoryFactory->create();
            foreach ($catTree as $catId) {
                if (!in_array($catId, $rootCats)) {
                    if (!isset($this->categoriesCache[$catId])) {
                        $category = $categoryObj->load($catId);
                        $this->categoriesCache[$catId] = $category->getName();
                    }
                    $parentName .= $this->categoriesCache[$catId] . ' -> ';
                }
            }
        }

        return $parentName;
    }
}

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

namespace Buckaroo\Magento2\Plugin;

use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoExtensionFactory;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\CreditmemoSearchResultInterface;

class CreditmemoExtensionAttribute
{
    /**
     * @var array|string[]
     */
    private $buckarooFieldNames = [
        'buckaroo_fee',
        'base_buckaroo_fee',
        'buckaroo_fee_tax_amount',
        'buckaroo_fee_base_tax_amount',
        'buckaroo_fee_incl_tax',
        'base_buckaroo_fee_incl_tax',
        'buckaroo_push_data',
        'buckaroo_already_paid',
    ];

    /**
     * @var CreditmemoExtensionFactory
     */
    private $extensionFactory;

    /**
     * @param CreditmemoExtensionFactory $extensionFactory
     */
    public function __construct(CreditmemoExtensionFactory $extensionFactory)
    {
        $this->extensionFactory = $extensionFactory;
    }

    /**
     * Add Buckaroo specific extension attributes to a list of credit memos after get.
     *
     * @param  CreditmemoRepositoryInterface   $subject
     * @param  CreditmemoSearchResultInterface $searchResult
     * @return CreditmemoSearchResultInterface
     */
    public function afterGetList(
        CreditmemoRepositoryInterface $subject,
        CreditmemoSearchResultInterface $searchResult
    ): CreditmemoSearchResultInterface {
        $orders = $searchResult->getItems();

        foreach ($orders as $order) {
            $this->afterGet($subject, $order);
        }

        return $searchResult;
    }

    /**
     * Add Buckaroo specific extension attributes to a single credit memo after get.
     *
     * @param  CreditmemoRepositoryInterface $subject
     * @param  CreditmemoInterface           $creditmemo
     * @return CreditmemoInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGet(
        CreditmemoRepositoryInterface $subject,
        CreditmemoInterface $creditmemo
    ): CreditmemoInterface {
        $extensionAttributes = $creditmemo->getExtensionAttributes();

        if (!$extensionAttributes) {
            $extensionAttributes = $this->extensionFactory->create();
        }

        foreach ($this->buckarooFieldNames as $fieldName) {
            $fieldValue = $creditmemo->getData($fieldName);
            $extensionAttributes->setData($fieldName, $fieldValue);
        }

        $creditmemo->setExtensionAttributes($extensionAttributes);

        return $creditmemo;
    }
}

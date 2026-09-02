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
     * @param CreditmemoRepositoryInterface   $subject
     * @param CreditmemoSearchResultInterface $searchResult
     *
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
     * @param CreditmemoRepositoryInterface $subject
     * @param CreditmemoInterface           $creditmemo
     *
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

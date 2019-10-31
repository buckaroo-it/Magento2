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
namespace TIG\Buckaroo\Model\Plugin;

use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class OrderExtensionAttribute
{
    private $buckarooFieldNames = [
        'buckaroo_fee',
        'base_buckaroo_fee',
        'buckaroo_fee_tax_amount',
        'buckaroo_fee_base_tax_amount',
        'buckaroo_fee_incl_tax',
        'base_buckaroo_fee_incl_tax',
        'buckaroo_push_data'
    ];

    /** @var OrderExtensionFactory */
    private $extensionFactory;

    public function __construct(OrderExtensionFactory $extensionFactory)
    {
        $this->extensionFactory = $extensionFactory;
    }

    /**
     * @param OrderRepositoryInterface $subject
     * @param OrderInterface           $order
     *
     * @return OrderInterface
     */
    public function afterGet(OrderRepositoryInterface $subject, OrderInterface $order)
    {
        $extensionAttributes = $order->getExtensionAttributes();

        if (!$extensionAttributes) {
            $extensionAttributes = $this->extensionFactory->create();
        }

        foreach ($this->buckarooFieldNames as $fieldName) {
            $fieldValue = $order->getData($fieldName);
            $extensionAttributes->setData($fieldName, $fieldValue);
        }

        $order->setExtensionAttributes($extensionAttributes);

        return $order;
    }

    /**
     * @param OrderRepositoryInterface   $subject
     * @param OrderSearchResultInterface $searchResult
     *
     * @return OrderSearchResultInterface
     */
    public function afterGetList(OrderRepositoryInterface $subject, OrderSearchResultInterface $searchResult)
    {
        $orders = $searchResult->getItems();

        foreach ($orders as $order) {
            $this->afterGet($subject, $order);
        }

        return $searchResult;
    }
}

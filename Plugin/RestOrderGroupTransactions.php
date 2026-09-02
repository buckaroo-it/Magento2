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
namespace Buckaroo\Magento2\Plugin;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Buckaroo\Magento2\Api\Data\BuckarooRestOrderDataInterfaceFactory;

class RestOrderGroupTransactions
{
    /**
     * @var BuckarooRestOrderDataInterfaceFactory
     */
    private $dataFactory;

    /**
     * @param BuckarooRestOrderDataInterfaceFactory $dataFactory
     */
    public function __construct(BuckarooRestOrderDataInterfaceFactory $dataFactory)
    {
        $this->dataFactory = $dataFactory;
    }

    /**
     * Add Buckaroo group transaction data to the order extension attributes.
     *
     * @param OrderRepositoryInterface $subject
     * @param OrderInterface           $entity
     *
     * @return OrderInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGet(
        OrderRepositoryInterface $subject,
        OrderInterface $entity
    ) {

        if ($this->isBuckaroo($entity)) {
            $ourCustomData = $this->dataFactory->create(["orderIncrementId" => $entity->getIncrementId()]);

            $extensionAttributes = $entity->getExtensionAttributes(); /** get current extension attributes from entity **/

            $extensionAttributes->setBuckaroo($ourCustomData);
            $entity->setExtensionAttributes($extensionAttributes);
        }

        return $entity;
    }

    /**
     * Check whether the order was paid with a Buckaroo payment method.
     *
     * @param OrderInterface $entity
     * @return bool
     */
    private function isBuckaroo(OrderInterface $entity)
    {
        return strpos($entity->getPayment()->getMethod(), "buckaroo_magento2_") !== false;
    }
}

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
namespace Buckaroo\Magento2\Plugin;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Buckaroo\Magento2\Api\Data\BuckarooRestOrderDataInterfaceFactory;


class RestOrderGroupTransactions
{
    private $dataFactory;

    public function __construct(BuckarooRestOrderDataInterfaceFactory $dataFactory) {
        $this->dataFactory = $dataFactory;
    }
    public function afterGet(
        OrderRepositoryInterface $subject,
        OrderInterface $entity
    ) {
        $ourCustomData = $this->dataFactory->create(["orderIncrementId" => $entity->getIncrementId()]);

        $extensionAttributes = $entity->getExtensionAttributes(); /** get current extension attributes from entity **/

        $extensionAttributes->setBuckaroo($ourCustomData);
        $entity->setExtensionAttributes($extensionAttributes);

        return $entity;
    }
}
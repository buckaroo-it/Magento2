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

namespace Buckaroo\Magento2\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;


class PaymentFlow extends Value
{
    private const CONFIG_PATH_CREATE_INVOICE_AFTER_SHIPMENT = 'payment/buckaroo_magento2_afterpay20/create_invoice_after_shipment';
    private const PAYMENT_FLOW_COMBINED = 'order';

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @param Context                 $context
     * @param Registry                $registry
     * @param ScopeConfigInterface    $config
     * @param TypeListInterface       $cacheTypeList
     * @param WriterInterface         $configWriter
     * @param AbstractResource|null   $resource
     * @param AbstractDb|null         $resourceCollection
     * @param array                   $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        WriterInterface $configWriter,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->configWriter = $configWriter;
    }

    /**
     * Disables "create invoice after shipment" if payment flow is not "authorize".
     *
     * @return $this
     */
    public function afterSave()
    {
        $flow = (string) $this->getValue();

        if ($flow === self::PAYMENT_FLOW_COMBINED) {
            $this->configWriter->save(
                self::CONFIG_PATH_CREATE_INVOICE_AFTER_SHIPMENT,
                0,
                $this->getScope(),
                (int)$this->getScopeId()
            );
        }

        return parent::afterSave();
    }
}

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
namespace Buckaroo\Magento2\Model\Order;

use Magento\Sales\Model\Order\CreditmemoFactory as MagentoCreditmemoFactory;

/**
 * Factory class for @see \Magento\Sales\Model\Order\Creditmemo
 */
class CreditmemoFactory extends MagentoCreditmemoFactory
{
    /**
     * Order convert object.
     *
     * @var \Magento\Sales\Model\Convert\Order
     */
    protected $convertor;

    /**
     * @var \Magento\Tax\Model\Config
     */
    protected $taxConfig;

    /**
     * @var \Magento\Framework\Unserialize\Unserialize
     * @deprecated 101.0.0
     */
    protected $unserialize;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $serializer;

    protected $logger;

    /**
     * Factory constructor
     *
     * @param \Magento\Sales\Model\Convert\OrderFactory $convertOrderFactory
     * @param \Magento\Tax\Model\Config $taxConfig
     * @param \Magento\Framework\Serialize\Serializer\Json $serializer
     */
    public function __construct(
        \Magento\Sales\Model\Convert\OrderFactory $convertOrderFactory,
        \Magento\Tax\Model\Config $taxConfig,
        \Buckaroo\Magento2\Logging\Log $logger,
        \Magento\Framework\Serialize\Serializer\Json $serializer = null
    ) {
        $this->logger = $logger;
        parent::__construct($convertOrderFactory, $taxConfig, $serializer);
    }

    /**
     * Prepare order creditmemo based on order items and requested params
     *
     * @param \Magento\Sales\Model\Order $order
     * @param array $data
     * @return Creditmemo
     */
    public function createByOrder(\Magento\Sales\Model\Order $order, array $data = [])
    {
        $creditmemo = $this->convertor->toCreditmemo($order);
        $this->initBuckarooFeeData($creditmemo, $data, $order);
        return parent::createByOrder($order, $data);
    }

    /**
     * Prepare order creditmemo based on invoice and requested params
     *
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     * @param array $data
     * @return Creditmemo
     */
    public function createByInvoice(\Magento\Sales\Model\Order\Invoice $invoice, array $data = [])
    {
        $order      = $invoice->getOrder();
        $creditmemo = $this->convertor->toCreditmemo($order);
        $this->initBuckarooFeeData($creditmemo, $data, $invoice);
        return parent::createByInvoice($invoice, $data);
    }

    /**
     * Initialize creditmemo state based on requested parameters
     *
     * @param Creditmemo $creditmemo
     * @param array $data
     * @param Order|Invoice $salesModel
     * @return void
     */
    public function initBuckarooFeeData($creditmemo, $data, $salesModel)
    {
        if (isset($data['extension_attributes']['buckaroo_fee'])) {
            $salesModel->setBuckarooFee((double) $data['extension_attributes']['buckaroo_fee']);
        }

        if (isset($data['extension_attributes']['base_buckaroo_fee'])) {
            $salesModel->setBaseBuckarooFee((double) $data['extension_attributes']['base_buckaroo_fee']);
        }
    }
}

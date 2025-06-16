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

namespace Buckaroo\Magento2\Model\Order;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Unserialize\Unserialize;
use Magento\Sales\Model\Convert\Order as ConvertOrder;
use Magento\Sales\Model\Convert\OrderFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\CreditmemoFactory as MagentoCreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Tax\Model\Config;

/**
 * Factory class for @see Creditmemo
 */
class CreditmemoFactory extends MagentoCreditmemoFactory
{
    /**
     * Order convert object.
     *
     * @var ConvertOrder
     */
    protected $convertor;

    /**
     * @var Config
     */
    protected $taxConfig;

    /**
     * @var Unserialize
     * @deprecated 101.0.0
     */
    protected $unserialize;

    /**
     * @var BuckarooLoggerInterface
     */
    protected BuckarooLoggerInterface $logger;

    /**
     * Factory constructor
     *
     * @param OrderFactory $convertOrderFactory
     * @param Config $taxConfig
     * @param BuckarooLoggerInterface $logger
     * @param Json|null $serializer
     */
    public function __construct(
        OrderFactory $convertOrderFactory,
        Config $taxConfig,
        BuckarooLoggerInterface $logger,
        ?Json $serializer = null
    ) {
        $this->logger = $logger;
        parent::__construct($convertOrderFactory, $taxConfig, $serializer);
    }

    /**
     * Prepare order creditmemo based on order items and requested params
     *
     * @param Order $order
     * @param array $data
     * @return Creditmemo
     */
    public function createByOrder(Order $order, array $data = []): Creditmemo
    {
        $creditmemo = $this->convertor->toCreditmemo($order);
        $this->initBuckarooFeeData($creditmemo, $data, $order);
        return parent::createByOrder($order, $data);
    }

    /**
     * Initialize creditmemo state based on requested parameters
     *
     * @param Creditmemo $creditmemo
     * @param array $data
     * @param Order|Invoice $salesModel
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function initBuckarooFeeData(Creditmemo $creditmemo, array $data, $salesModel)
    {
        if (isset($data['extension_attributes']['buckaroo_fee'])) {
            $salesModel->setBuckarooFee((double)$data['extension_attributes']['buckaroo_fee']);
        }

        if (isset($data['extension_attributes']['base_buckaroo_fee'])) {
            $salesModel->setBaseBuckarooFee((double)$data['extension_attributes']['base_buckaroo_fee']);
        }
    }

    /**
     * Prepare order creditmemo based on invoice and requested params
     *
     * @param Invoice $invoice
     * @param array $data
     * @return Creditmemo
     */
    public function createByInvoice(Invoice $invoice, array $data = []): Creditmemo
    {
        $order = $invoice->getOrder();
        $creditmemo = $this->convertor->toCreditmemo($order);
        $this->initBuckarooFeeData($creditmemo, $data, $invoice);
        return parent::createByInvoice($invoice, $data);
    }
}

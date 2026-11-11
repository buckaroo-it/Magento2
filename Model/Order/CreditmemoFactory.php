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

namespace Buckaroo\Magento2\Model\Order;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\Serialize\Serializer\Json;
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
     * @var BuckarooLoggerInterface
     */
    protected $logger;

    /**
     * Factory constructor
     *
     * @param OrderFactory            $convertOrderFactory
     * @param Config                  $taxConfig
     * @param BuckarooLoggerInterface $logger
     * @param Json|null               $serializer
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
     *
     * @return Creditmemo
     */
    public function createByOrder(Order $order, array $data = []): Creditmemo
    {
        $this->initBuckarooFeeData($data, $order);
        return parent::createByOrder($order, $data);
    }

    /**
     * Initialize creditmemo state based on requested parameters
     *
     * @param array         $data
     * @param Order|Invoice $salesModel
     */
    public function initBuckarooFeeData(array $data, $salesModel)
    {
        if (isset($data['extension_attributes']['buckaroo_fee'])) {
            $salesModel->setBuckarooFee((float)$data['extension_attributes']['buckaroo_fee']);
        }

        if (isset($data['extension_attributes']['base_buckaroo_fee'])) {
            $salesModel->setBaseBuckarooFee((float)$data['extension_attributes']['base_buckaroo_fee']);
        }
    }

    /**
     * Prepare order creditmemo based on invoice and requested params
     *
     * @param Invoice $invoice
     * @param array   $data
     *
     * @return Creditmemo
     */
    public function createByInvoice(Invoice $invoice, array $data = []): Creditmemo
    {
        $this->initBuckarooFeeData($data, $invoice);
        $creditmemo = parent::createByInvoice($invoice, $data);
        $this->castItemQtysToFloat($creditmemo);

        return $creditmemo;
    }

    /**
     * Store every creditmemo item qty as a float
     *
     * Core takes the item qty from min() of a float and the invoice item qty, which the database returns as a
     * string. On PHP 8.4+ min() can return that string, and the strict-typed QuantityValidator then throws a
     * TypeError when refunding through POST /V1/invoice/:id/refund (magento/magento2#40302).
     *
     * @param Creditmemo $creditmemo
     *
     * @return void
     */
    private function castItemQtysToFloat(Creditmemo $creditmemo): void
    {
        foreach ($creditmemo->getAllItems() as $item) {
            $item->setQty((float)$item->getQty());
        }
    }
}

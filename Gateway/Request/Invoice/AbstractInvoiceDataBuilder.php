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

namespace Buckaroo\Magento2\Gateway\Request\Invoice;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Helper\Data as BuckarooHelper;

abstract class AbstractInvoiceDataBuilder extends AbstractDataBuilder
{
    /**
     * @var int
     */
    protected int $numberOfInvoices;

    /**
     * @var bool
     */
    protected bool $capturePartial = true;

    /**
     * @var float
     */
    protected float $currentInvoiceTotal;

    /**
     * @var BuckarooHelper
     */
    private BuckarooHelper $buckarooHelper;

    /**
     * @param BuckarooHelper $buckarooHelper
     */
    public function __construct(BuckarooHelper $buckarooHelper)
    {
        $this->buckarooHelper = $buckarooHelper;
    }

    /**
     * Initializes the payment information for a Buckaroo payment.
     *
     * @param array $buildSubject
     * @return array
     */
    public function initialize(array $buildSubject): array
    {
        $data = parent::initialize($buildSubject);

        $order = $this->getOrder();

        $totalOrder = $order->getBaseGrandTotal();
        $this->numberOfInvoices = $order->getInvoiceCollection()->count();
        $this->currentInvoiceTotal = 0;

        // loop through invoices to get the last one (=current invoice)
        if ($this->numberOfInvoices) {
            $invoiceCollection = $order->getInvoiceCollection();
            $currentInvoice = $invoiceCollection->getLastItem();
            $this->currentInvoiceTotal = $currentInvoice->getGrandTotal();
        }

        if ($this->buckarooHelper->areEqualAmounts($totalOrder, $this->currentInvoiceTotal)
            && $this->numberOfInvoices == 1) {
            $this->capturePartial = false; //full capture
        }

        $data['capturePartial'] = $this->capturePartial;
        $data['currentInvoiceTotal'] = $this->currentInvoiceTotal;
        $data['numberOfInvoices'] = $this->numberOfInvoices;

        return $data;
    }
}

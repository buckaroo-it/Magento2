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
use Magento\Sales\Model\Order\Invoice;

abstract class AbstractInvoiceDataBuilder extends AbstractDataBuilder
{
    /**
     * @var int
     */
    protected $numberOfInvoices;

    /**
     * @var bool
     */
    protected $capturePartial = true;

    /**
     * @var float
     */
    protected $currentInvoiceTotal;

    /**
     * @var BuckarooHelper
     */
    private $buckarooHelper;

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
     *
     * @return array
     */
    public function initialize(array $buildSubject): array
    {
        $data = parent::initialize($buildSubject);

        $order = $this->getOrder();

        $totalOrder = $order->getBaseGrandTotal();
        $this->numberOfInvoices = $order->getInvoiceCollection()->count();
        $this->currentInvoiceTotal = 0;
        $currentInvoice = $this->resolveCurrentInvoice();
        if ($currentInvoice) {
            $this->currentInvoiceTotal = (float)$currentInvoice->getGrandTotal();
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

    /**
     * Prefer the invoice currently being captured to avoid stale "last item" lookups.
     *
     * @return Invoice|null
     */
    private function resolveCurrentInvoice(): ?Invoice
    {
        $payment = $this->getPayment();
        if (method_exists($payment, 'getCreatedInvoice')) {
            $createdInvoice = $payment->getCreatedInvoice();
            if ($createdInvoice instanceof Invoice && $createdInvoice->getEntityId()) {
                return $createdInvoice;
            }
            if ($createdInvoice instanceof Invoice && $createdInvoice->getItemsCount() > 0) {
                return $createdInvoice;
            }
        }

        if ($this->numberOfInvoices > 0) {
            return $this->getOrder()->getInvoiceCollection()->getLastItem();
        }

        return null;
    }
}

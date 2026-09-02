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

namespace Buckaroo\Magento2\Block\Adminhtml\Sales\Order\Creditmemo\Create;

use Buckaroo\Magento2\Model\RefundFieldsFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Block\Adminhtml\Order\Payment;

class BankFields extends Template
{
    /**
     * @var string
     */
    protected $orderPaymentBlock = 'order_payment';

    /**
     * @var RefundFieldsFactory
     */
    protected $refundFieldsFactory;

    /**
     * @param Context                  $context
     * @param RefundFieldsFactory|null $refundFieldsFactory
     */
    public function __construct(
        Context $context,
        ?RefundFieldsFactory $refundFieldsFactory = null
    ) {
        $this->refundFieldsFactory = $refundFieldsFactory;
        parent::__construct($context);
    }

    /**
     * Get the payment method and dynamically find which extra fields (if any) need to be shown.
     *
     * @throws LocalizedException
     *
     * @return array
     */
    public function getExtraFields()
    {
        $extraFields = [];
        $paymentMethod = $this->getPaymentMethod();

        /**
         * If no payment method is found, return the empty array.
         */
        if (!$paymentMethod) {
            return $extraFields;
        }

        /**
         * get both the field codes and labels. These are used for the Buckaroo request (codes)
         * and human readability (labels)
         */
        $fields = $this->refundFieldsFactory->get($paymentMethod);

        /**
         * Parse the code and label in the same array, to keep the data paired.
         */
        if ($fields) {
            foreach ($fields as $field) {
                $extraFields[$field['label']] = $field['code'];
            }
        }

        return $extraFields;
    }

    /**
     * Returns the Payment Method name. If something goes wrong, this will return false.
     *
     * @throws LocalizedException
     *
     * @return string|false (when not found)
     */
    public function getPaymentMethod()
    {
        $paymentMethod = false;

        $layout = $this->getLayout();
        /**
         * @var Payment $paymentBlock
         */
        $paymentBlock = $layout->getBlock($this->orderPaymentBlock);

        if ($paymentBlock) {
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $paymentMethod = $paymentBlock->getPayment()->getMethod();
        }

        return $paymentMethod;
    }
}

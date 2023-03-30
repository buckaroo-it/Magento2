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
     * @param Context $context
     * @param RefundFieldsFactory|null $refundFieldsFactory
     */
    public function __construct(
        Context $context,
        RefundFieldsFactory $refundFieldsFactory = null
    ) {
        $this->refundFieldsFactory = $refundFieldsFactory;
        parent::__construct($context);
    }

    /**
     * Get the payment method and dynamically find which extra fields (if any) need to be shown.
     *
     * @return array
     * @throws LocalizedException
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
     * @return string | false (when not found)
     * @throws LocalizedException
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

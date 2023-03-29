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

namespace Buckaroo\Magento2\Block\Adminhtml\Sales\Order\Invoice;

class KlarnaDiscountPartialInformation extends \Magento\Framework\View\Element\Template
{
    protected $order;

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory
     */
    protected $configProviderFactory;

    /**
     * RoundingWarning constructor.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface            $order
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory $configProviderFactory
     * @param \Magento\Backend\Block\Template\Context           $context
     * @param array                                             $data
     */
    public function __construct(
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory $configProviderFactory,
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->order                 = $order;
        $this->configProviderFactory = $configProviderFactory;
    }

    /**
     * @return bool
     * @throws \LogicException
     * @throws \Buckaroo\Magento2\Exception
     */
    protected function shouldShowWarning()
    {
        if ($order_id = $this->getRequest()->getParam('order_id')) {
            $order   = $this->order->load($order_id);
            $payment = $order->getPayment();

            /**
             * The warning should only be shown for partial invoices
             */
            if ($payment->canCapturePartial()) {
                return false;
            }

            /**
             * The warning should only be shown for Klarna Buckaroo payment methods.
             */
            $paymentMethod = $payment->getMethod();
            if (strpos($paymentMethod, 'buckaroo_magento2_klarna') === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml()
    {
        if (!$this->shouldShowWarning()) {
            return '';
        }

        return parent::_toHtml();
    }
}

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

namespace Buckaroo\Magento2\Block\Adminhtml\Sales\Order\Invoice;

use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use LogicException;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Api\Data\OrderInterface;

class KlarnaDiscountPartialInformation extends Template
{
    /**
     * @var OrderInterface
     */
    protected $order;

    /**
     * @var Factory
     */
    protected $configProviderFactory;

    /**
     * RoundingWarning constructor.
     *
     * @param OrderInterface $order
     * @param Factory        $configProviderFactory
     * @param Context        $context
     * @param array          $data
     */
    public function __construct(
        OrderInterface $order,
        Factory $configProviderFactory,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->order = $order;
        $this->configProviderFactory = $configProviderFactory;
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

    /**
     * Should show the warning regarding partial discount
     *
     * @return bool
     * @throws LogicException|LocalizedException
     */
    protected function shouldShowWarning()
    {
        if ($orderId = $this->getRequest()->getParam('order_id')) {
            $order = $this->order->load($orderId);
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
}

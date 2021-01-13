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


namespace Buckaroo\Magento2\Block\Widget\Button;

use Magento\Backend\Block\Widget\Button\Toolbar as ToolbarContext;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Backend\Block\Widget\Button\ButtonList;

/**
 * Class Toolbar
 * @package Buckaroo\Magento2\Block\Widget\Button
 */
class Toolbar
{
    /**
     * @var array
     */
    private $allowedMethods = [
        'buckaroo_magento2_afterpay',
        'buckaroo_magento2_afterpay2',
        'buckaroo_magento2_afterpay20',
        'buckaroo_magento2_billink',
        'buckaroo_magento2_payperemail',
        'buckaroo_magento2_paylink',
        'buckaroo_magento2_creditcard',
        'buckaroo_magento2_creditcards',
        'buckaroo_magento2_ideal',
        'buckaroo_magento2_idealprocessing',
        'buckaroo_magento2_mrcash',
        'buckaroo_magento2_paypal',
        'buckaroo_magento2_payconiq',
        'buckaroo_magento2_sepadirectdebit',
        'buckaroo_magento2_sofortbanking',
        'buckaroo_magento2_transfer',
        'buckaroo_magento2_paymentguarantee',
        'buckaroo_magento2_eps',
        'buckaroo_magento2_giropay',
        'buckaroo_magento2_kbc',
        'buckaroo_magento2_klarnakp',
        'buckaroo_magento2_emandate',
        'buckaroo_magento2_applepay',
        'buckaroo_magento2_capayablein3',
        'buckaroo_magento2_capayablepostpay',
        'buckaroo_magento2_alipay',
        'buckaroo_magento2_wechatpay',
        'buckaroo_magento2_p24',
        'buckaroo_magento2_trustly',
        'buckaroo_magento2_rtp',
        'buckaroo_magento2_pospayment',
        'buckaroo_magento2_tinka'
    ];

    /**
     * @param ToolbarContext $toolbar
     * @param AbstractBlock  $context
     * @param ButtonList     $buttonList
     * @return array
     */
    public function beforePushButtons(
        ToolbarContext $toolbar,
        \Magento\Framework\View\Element\AbstractBlock $context,
        \Magento\Backend\Block\Widget\Button\ButtonList $buttonList
    ) {
        if ($this->_isOrderInvoiceView($context)) {
            return $this->_creditMemoNotAllowed($context, $buttonList);
        }

        return [$context, $buttonList];
    }

    /**
     * @param $context
     * @return bool
     */
    private function _isOrderView($context)
    {
        if ($context instanceof \Magento\Sales\Block\Adminhtml\Order\View) {
            return true;
        }
    }

    /**
     * @param $context
     * @return bool
     */
    private function _isOrderInvoiceView($context)
    {
        if ($context instanceof \Magento\Sales\Block\Adminhtml\Order\Invoice\View) {
            return true;
        }
    }

    /**
     * @param $context
     * @param $buttonList
     * @return array
     */
    private function _creditMemoNotAllowed($context, $buttonList)
    {
        $orderPayment = $context->getInvoice()->getOrder();
        $paymentMethod = $orderPayment->getPayment()->getMethod();

        if ($orderPayment->getBaseBuckarooFee() > 0 && !in_array($paymentMethod, $this->allowedMethods)) {
            $message = __(
                'Cannot Refund via Magento Backend. ' .
                'Partial refunds combined with a payment fee can only be refunded via the Buckaroo Payment Plaza, ' .
                'see also the ' .
                '<a href="https://support.buckaroo.nl" target="_blank">KB article</a>.<br>' .
                '<a href="https://plaza.buckaroo.nl" target="_blank">' .
                'Open a new window to the Buckaroo Payment Plaza</a>.'
            );
            $onClick = "confirmSetLocation('{$message}', '#')";

            $buttonList->update('capture', 'onclick', $onClick);
        }

        $orderKeyCM3 = $orderPayment->getPayment()->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if (isset($orderKeyCM3) && strlen($orderKeyCM3) > 0) {
            $message = __(
                'Cannot refund this order via Magento Backend for now, we are working on a solution! ' .
                'Credit Management orders can only be refunded via the Buckaroo Payment Plaza.' .
                '<br>' .
                '<a href="https://plaza.buckaroo.nl" target="_blank">' .
                'Open a new window to the Buckaroo Payment Plaza</a>.'
            );
            $onClick = "confirmSetLocation('{$message}', '#')";

            $buttonList->update('capture', 'onclick', $onClick);
        }

        return [$context, $buttonList];
    }
}

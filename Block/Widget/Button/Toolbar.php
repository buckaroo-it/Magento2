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


namespace TIG\Buckaroo\Block\Widget\Button;

use Magento\Backend\Block\Widget\Button\Toolbar as ToolbarContext;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Backend\Block\Widget\Button\ButtonList;

/**
 * Class Toolbar
 * @package TIG\Buckaroo\Block\Widget\Button
 */
class Toolbar
{
    /**
     * @var array
     */
    private $allowedMethods = [
        'tig_buckaroo_afterpay',
        'tig_buckaroo_afterpay2',
        'tig_buckaroo_afterpay20',
        'tig_buckaroo_payperemail',
        'tig_buckaroo_creditcard',
        'tig_buckaroo_creditcards',
        'tig_buckaroo_ideal',
        'tig_buckaroo_idealprocessing',
        'tig_buckaroo_mrcash',
        'tig_buckaroo_paypal',
        'tig_buckaroo_payconiq',
        'tig_buckaroo_sepadirectdebit',
        'tig_buckaroo_sofortbanking',
        'tig_buckaroo_transfer',
        'tig_buckaroo_paymentguarantee',
        'tig_buckaroo_eps',
        'tig_buckaroo_giropay',
        'tig_buckaroo_kbc',
        'tig_buckaroo_klarna',
        'tig_buckaroo_emandate',
        'tig_buckaroo_applepay',
        'tig_buckaroo_capayablein3',
        'tig_buckaroo_capayablepostpay',
        'tig_buckaroo_alipay',
        'tig_buckaroo_wechatpay',
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
                '<a href="https://confluence.tig.nl/x/L4aC" target="_blank">KB article</a>.<br>' .
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

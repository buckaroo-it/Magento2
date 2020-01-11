<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */


namespace TIG\Buckaroo\Block\Widget\Button;

use Magento\Backend\Block\Widget\Button\Toolbar as ToolbarContext;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Backend\Block\Widget\Button\ButtonList;

class Toolbar
{
    private $allowedMethods = [
        'tig_buckaroo_afterpay',
        'tig_buckaroo_afterpay2',
        'tig_buckaroo_payperemail',
        'tig_buckaroo_creditcard',
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
        'tig_buckaroo_giropay'
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
        if (!$context instanceof \Magento\Sales\Block\Adminhtml\Order\Invoice\View) {
            return [$context, $buttonList];
        }

        $orderPayment = $context->getInvoice()->getOrder();
        $paymentMethod = $orderPayment->getPayment()->getMethod();

        if ($orderPayment->getBaseBuckarooFee() > 0 && !in_array($paymentMethod, $this->allowedMethods)) {
            $message = __(
                'Cannot Refund via Magento Backend. ' .
                'Partial refunds combined with a payment fee can only be refunded via the Buckaroo Payment Plaza, ' .
                'see also the ' .
                '<a href="http://servicedesk.tig.nl/hc/nl/articles/217984838" target="_blank">KB article</a>.<br>' .
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

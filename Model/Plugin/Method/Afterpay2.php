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

namespace TIG\Buckaroo\Model\Plugin\Method;

/**
 * Class Afterpay2
 *
 * @package TIG\Buckaroo\Model\Plugin\Method
 */
class Afterpay2
{
    const AFTERPAY_METHOD_NAME = 'tig_buckaroo_afterpay2';

    /**
     * \TIG\Buckaroo\Model\Method\Afterpay2
     *
     * @var bool
     */
    public $afterpayMethod = false;

    /**
     * @param \TIG\Buckaroo\Model\Method\Afterpay2 $afterpay
     */
    public function __construct(\TIG\Buckaroo\Model\Method\Afterpay2 $afterpay)
    {
        $this->afterpayMethod = $afterpay;
    }

    /**
     * @param \Magento\Sales\Model\Order $subject
     *
     * @return \Magento\Sales\Model\Order
     */
    public function afterCancel(
        \Magento\Sales\Model\Order $subject
    ) {
        $payment = $subject->getPayment();
        $orderIsCanceled = $payment->getOrder()->isCanceled();
        $orderIsVoided = ($payment->getAdditionalInformation('voided_by_buckaroo') === true);

        if ($payment->getMethod() !== self::AFTERPAY_METHOD_NAME || $orderIsCanceled || $orderIsVoided) {
            return $subject;
        }

        $this->afterpayMethod->cancel($payment);

        return $this;
    }
}

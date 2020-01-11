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

namespace TIG\Buckaroo\Model\Service\Plugin\PaypalSellersProtection;

use TIG\Buckaroo\Model\ConfigProvider\Method\Paypal;

class Push
{
    /**#@+
     * PayPal Seller's Protection eligibility types.
     */
    const ELIGIBILITY_INELIGIBLE                = 'Ineligible';
    const ELIGIBILITY_TYPE_ELIGIBLE             = 'Eligible';
    const ELIGIBILITY_TYPE_ITEM_NOT_RECEIVED    = 'ItemNotReceivedEligible';
    const ELIGIBILITY_TYPE_UNAUTHORIZED_PAYMENT = 'UnauthorizedPaymentEligible';
    const ELIGIBILITY_TYPE_NONE                 = 'None';
    /**#@-*/

    /**
     * @var Paypal
     */
    protected $configProviderPaypal;

    /**
     * @param Paypal $congigProviderPaypal
     */
    public function __construct(
        Paypal $congigProviderPaypal
    ) {
        $this->configProviderPaypal = $congigProviderPaypal;
    }

    /**
     * @param \TIG\Buckaroo\Model\Push $push
     * @param boolean                  $result
     *
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function afterProcessSucceededPush(
        \TIG\Buckaroo\Model\Push $push,
        $result
    ) {
        if (empty($push->postData['brq_service_paypal_protectioneligibility'])
            || empty($push->postData['brq_service_paypal_protectioneligibilitytype'])
        ) {
            return $result;
        }

        $eligibility = $push->postData['brq_service_paypal_protectioneligibility'];
        if ($eligibility == self::ELIGIBILITY_INELIGIBLE) {
            $eligibilityType = self::ELIGIBILITY_TYPE_NONE;
        } else {
            $eligibilityType = $push->postData['brq_service_paypal_protectioneligibilitytype'];
        }

        $order = $push->order;
        switch ($eligibilityType) {
            case self::ELIGIBILITY_TYPE_ELIGIBLE:
                $comment = __(
                    "Merchant is protected by PayPal Seller Protection Policy for both Unauthorized Payment and Item" .
                    " Not Received."
                );

                $status = $this->configProviderPaypal->getSellersProtectionEligible();
                break;
            case self::ELIGIBILITY_TYPE_ITEM_NOT_RECEIVED:
                $comment = __("Merchant is protected by Paypal Seller Protection Policy for Item Not Received.");

                $status = $this->configProviderPaypal->getSellersProtectionItemnotreceivedEligible();
                break;
            case self::ELIGIBILITY_TYPE_UNAUTHORIZED_PAYMENT:
                $comment = __("Merchant is protected by Paypal Seller Protection Policy for Unauthorized Payment.");

                $status = $this->configProviderPaypal->getSellersProtectionUnauthorizedpaymentEligible();
                break;
            case self::ELIGIBILITY_TYPE_NONE:
                $comment = __("Merchant is not protected under the Seller Protection Policy.");

                $status = $this->configProviderPaypal->getSellersProtectionIneligible();
                break;
            default:
                throw new \InvalidArgumentException("Invalid eligibility type.");
        }
        $order->addStatusHistoryComment($comment, $status ? $status : false);

        return $result;
    }
}

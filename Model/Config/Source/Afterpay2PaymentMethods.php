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

namespace TIG\Buckaroo\Model\Config\Source;

/**
 * Class AfterpayPaymentMethods
 *
 * @package TIG\Buckaroo\Model\Config\Source
 */
class Afterpay2PaymentMethods implements \Magento\Framework\Option\ArrayInterface
{
    const PAYMENT_METHOD_ACCEPTGIRO = 1;
    const PAYMENT_METHOD_DIGIACCEPT = 2;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];

        // These are the paymethods available at Afterpay
        $options[] = ['value' => self::PAYMENT_METHOD_ACCEPTGIRO, 'label' => __('Acceptgiro')];
        $options[] = ['value' => self::PAYMENT_METHOD_DIGIACCEPT, 'label' => __('Digiaccept')];

        return $options;
    }
}

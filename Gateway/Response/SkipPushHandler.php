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
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class SkipPushHandler implements HandlerInterface
{
    /**
     * Handles response
     *
     * @param  array      $handlingSubject
     * @param  array      $response
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        /** @var OrderPaymentInterface $payment */
        $payment = $paymentDO->getPayment();

        $skipFirstPush = $payment->getAdditionalInformation('skip_push');
        if (is_array($skipFirstPush)) {
            $skipFirstPush = array_shift($skipFirstPush);
        }
        /**
         * Buckaroo Push is send before Response, for correct flow we skip the first push
         * for some payment methods
         *
         * @todo when buckaroo changes the push / response order this can be removed
         */
        if ($skipFirstPush > 0) {
            $payment->setAdditionalInformation('skip_push', $skipFirstPush - 1);
            if (!empty($payment->getOrder()) && !empty($payment->getOrder()->getId())) {
                // Only save payment if order is already saved, this to avoid foreign key constraint error
                // on table sales_order_payment, column parent_id.
                $payment->save();
            }
        }
    }
}

<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;

class SkipPushHandler implements HandlerInterface
{
    /**
     * @var OrderPaymentRepositoryInterface
     */
    private OrderPaymentRepositoryInterface $paymentRepository;

    /**
     * @param OrderPaymentRepositoryInterface $paymentRepository
     */
    public function __construct(OrderPaymentRepositoryInterface $paymentRepository)
    {
        $this->paymentRepository = $paymentRepository;
    }

    /**
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $response
     *
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
                $this->paymentRepository->save($payment);
            }
        }
    }
}

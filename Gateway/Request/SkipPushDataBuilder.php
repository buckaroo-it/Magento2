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

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Http\Client\TransactionType;
use Buckaroo\Magento2\Service\PayReminderService;
use Magento\Payment\Gateway\Request\BuilderInterface;

class SkipPushDataBuilder implements BuilderInterface
{
    public const BUCKAROO_SKIP_PUSH_KEY = 'skip_push';

    /**
     * @var PayReminderService
     */
    private $payReminderService;

    /**
     * @param PayReminderService $payReminderService
     */
    public function __construct(PayReminderService $payReminderService)
    {
        $this->payReminderService = $payReminderService;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $orderId = $paymentDO->getOrder()->getOrderIncrementId();

        $serviceAction = $this->payReminderService->getServiceAction($orderId);

        if (!in_array($serviceAction, [TransactionType::PAY_REMAINDER, TransactionType::PAY_REMAINDER_ENCRYPTED], true)) {
            $payment->setAdditionalInformation(self::BUCKAROO_SKIP_PUSH_KEY, 1);
            $paymentDO->getOrder()->getOrder()->save();
        }

        return [];
    }
}

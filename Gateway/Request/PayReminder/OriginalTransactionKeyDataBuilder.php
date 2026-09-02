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

namespace Buckaroo\Magento2\Gateway\Request\PayReminder;

use Buckaroo\Magento2\Gateway\Http\Client\TransactionType;
use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Service\PayReminderService;

class OriginalTransactionKeyDataBuilder extends AbstractDataBuilder
{
    /**
     * @var PayReminderService
     */
    private $payReminderService;

    /**
     * @param PayReminderService $payReminderService
     */
    public function __construct(
        PayReminderService $payReminderService
    ) {
        $this->payReminderService = $payReminderService;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $serviceAction = $this->payReminderService->getServiceAction($this->getOrder()->getIncrementId());

        $remainderActions = [
            TransactionType::PAY_REMAINDER,
            TransactionType::PAY_REMAINDER_ENCRYPTED,
            TransactionType::PAY_REMAINDER_WITH_TOKEN,
        ];

        if (in_array($serviceAction, $remainderActions, true)) {
            $originalTransactionKey = $this->payReminderService->getOriginalTransactionKey($this->getOrder());

            if (empty($originalTransactionKey)) {
                return [];
            }

            return ['originalTransactionKey' => $originalTransactionKey];
        }

        return [];
    }
}

<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\PayReminder;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Service\PayReminderService;

class AmountDataBuilder extends AbstractDataBuilder
{
    /**
     * @var PayReminderService
     */
    private PayReminderService $payReminderService;

    /**
     * @param PayReminderService $payReminderService
     */
    public function __construct(PayReminderService $payReminderService)
    {
        $this->payReminderService = $payReminderService;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        if ($this->payReminderService->getServiceAction($this->getOrder()->getIncrementId()) == 'payRemainder') {
            return ['amountDebit' => $this->payReminderService->getPayRemainder($this->getOrder())];
        }

        return [];
    }
}

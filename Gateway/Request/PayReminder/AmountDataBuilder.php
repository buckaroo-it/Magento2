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

namespace Buckaroo\Magento2\Gateway\Request\PayReminder;

use Buckaroo\Magento2\Gateway\Http\Client\TransactionType;
use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Service\PayReminderService;

class AmountDataBuilder extends AbstractDataBuilder
{
    /**
     * @var PayReminderService
     */
    private PayReminderService $payReminderService;

    private string $serviceAction;
    private string $newServiceAction;

    /**
     * @param PayReminderService $payReminderService
     * @param string $serviceAction
     * @param string $newServiceAction
     */
    public function __construct(
        PayReminderService $payReminderService,
        string $serviceAction = TransactionType::PAY,
        string $newServiceAction = TransactionType::PAY_REMAINDER
    ) {
        $this->payReminderService = $payReminderService;
        $this->serviceAction = $serviceAction;
        $this->newServiceAction = $newServiceAction;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $serviceAction = $this->payReminderService->getServiceAction(
            $this->getOrder()->getIncrementId(),
            $this->serviceAction,
            $this->newServiceAction
        );

        if (in_array($serviceAction, [TransactionType::PAY_REMAINDER, TransactionType::PAY_REMAINDER_ENCRYPTED])) {
            return ['amountDebit' => $this->payReminderService->getPayRemainder($this->getOrder())];
        }

        return [];
    }
}

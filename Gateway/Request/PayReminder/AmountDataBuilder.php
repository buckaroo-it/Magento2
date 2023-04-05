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
     * @inheritdoc
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

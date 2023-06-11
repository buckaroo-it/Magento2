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

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Http\Client\TransactionType;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Service\PayReminderService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;

class SkipPushDataBuilder implements BuilderInterface
{
    public const BUCKAROO_SKIP_PUSH_KEY = 'skip_push';

    /**
     * @var PayReminderService
     */
    private PayReminderService $payReminderService;

    /**
     * @var Log $logging
     */
    public Log $logging;

    /**
     * @param PayReminderService $payReminderService
     */
    public function __construct(
        PayReminderService $payReminderService,
        Log $logging
    ) {
        $this->payReminderService = $payReminderService;
        $this->logging = $logging;
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

        if (!in_array($serviceAction, TransactionType::getPayRemainderActions())) {
            $payment->setAdditionalInformation(self::BUCKAROO_SKIP_PUSH_KEY, 1);
            $paymentDO->getOrder()->getOrder()->save();
        }

        return [];
    }
}

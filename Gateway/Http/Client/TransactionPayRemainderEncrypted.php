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

namespace Buckaroo\Magento2\Gateway\Http\Client;

use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Service\PayReminderService;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Model\Method\Logger;
use Psr\Log\LoggerInterface;

class TransactionPayRemainderEncrypted extends DefaultTransaction
{
    /**
     * @var PayReminderService
     */
    private PayReminderService $payReminderService;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param Logger $customLogger
     * @param BuckarooAdapter $adapter
     * @param PayReminderService $payReminderService
     */
    public function __construct(
        LoggerInterface $logger,
        Logger $customLogger,
        BuckarooAdapter $adapter,
        PayReminderService $payReminderService
    ) {
        parent::__construct($logger, $customLogger, $adapter);
        $this->payReminderService = $payReminderService;
    }

    /**
     * @inheritdoc
     * @throws \Throwable
     */
    protected function process(string $paymentMethod, array $data): TransactionResponse
    {
        $orderIncrementId = $data['invoice'] ?? $data['order'] ?? '';
        $serviceAction = $this->payReminderService->getServiceAction(
            $orderIncrementId,
            'payEncrypted',
            'payRemainderEncrypted'
        );
        return $this->adapter->execute($serviceAction, $paymentMethod, $data);
    }
}

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

namespace Buckaroo\Magento2\Gateway\Request\AdditionalInformation;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Psr\Log\LoggerInterface;

class GooglepayRedirectDataBuilder implements BuilderInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        // For redirect mode, specify Google Pay as the service
        // Buckaroo will handle the payment on their Hosted Payment Page
        $data = [
            'servicesSelectableByClient' => 'googlepay',
            'continueOnIncomplete' => '1',
        ];

        $this->logger->info('GooglePay Redirect Data Builder', [
            'data' => $data,
            'buildSubject' => $buildSubject
        ]);

        return $data;
    }
}

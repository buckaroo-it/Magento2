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

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

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\Exception\LocalizedException;

class DataRequestKeyDataBuilder extends AbstractDataBuilder
{
    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(BuckarooLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     *
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $order = $this->getOrder();
        $dataRequestKey = $order->getBuckarooDatarequestKey();

        if ($dataRequestKey === null) {
            $payment = $order->getPayment();
            $dataRequestKey = $payment->getAdditionalInformation('buckaroo_datarequest_key');

            if ($dataRequestKey === null) {
                $errorMessage = sprintf(
                    'Cannot process Klarna MOR payment for order %s: DataRequest key is missing. '
                    . 'This usually happens when the authorization push was not received or processed.',
                    $order->getIncrementId()
                );

                $this->logger->addError('[KLARNA_MOR] ' . $errorMessage);

                throw new LocalizedException(__($errorMessage));
            }

            $this->logger->addWarning(sprintf(
                '[KLARNA_MOR] DataRequest key for order %s was found in payment additional '
                . 'information but not in order. This indicates a data sync issue. Using value: %s',
                $order->getIncrementId(),
                $dataRequestKey
            ));
        }

        return ['dataRequestKey' => $dataRequestKey];
    }
}

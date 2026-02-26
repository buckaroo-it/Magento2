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
                '[KLARNA_MOR] DataRequest key for order %s was found in payment additional information but not in order. '
                . 'This indicates a data sync issue. Using value: %s',
                $order->getIncrementId(),
                $dataRequestKey
            ));
        }

        return ['dataRequestKey' => $dataRequestKey];
    }
}

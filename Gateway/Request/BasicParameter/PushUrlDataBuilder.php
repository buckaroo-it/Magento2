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

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Service\Store\PushUrlBuilder;
use Magento\Payment\Gateway\Request\BuilderInterface;

class PushUrlDataBuilder implements BuilderInterface
{
    /**
     * @var PushUrlBuilder
     */
    protected $pushUrlBuilder;

    /**
     * TransactionBuilder constructor.
     *
     * @param PushUrlBuilder $pushUrlBuilder
     */
    public function __construct(
        PushUrlBuilder $pushUrlBuilder
    ) {
        $this->pushUrlBuilder = $pushUrlBuilder;
    }

    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function build(array $buildSubject): array
    {
        $pushUrl = $this->pushUrlBuilder->getPushUrl(
            SubjectReader::readPayment($buildSubject)->getOrder()->getStoreId()
        );

        return [
            'pushURL' => $pushUrl,
            'pushURLFailure' => $pushUrl
        ];
    }
}

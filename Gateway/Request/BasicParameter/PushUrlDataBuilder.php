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

use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class PushUrlDataBuilder implements BuilderInterface
{
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * TransactionBuilder constructor.
     *
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        UrlInterface $urlBuilder
    ) {
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function build(array $buildSubject): array
    {
        return [
            'pushURL' => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push'),
            'pushURLFailure' => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push')
        ];
    }
}

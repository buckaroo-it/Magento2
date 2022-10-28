<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class PushUrlDataBuilder implements BuilderInterface
{
    /** @var UrlInterface */
    protected UrlInterface $urlBuilder;

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

    public function build(array $buildSubject): array
    {
        return [
            'pushURL' => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push'),
            'pushURLFailure' => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push')
        ];
    }
}

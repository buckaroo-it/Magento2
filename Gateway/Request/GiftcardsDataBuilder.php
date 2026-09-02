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

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards as GiftcardsConfig;
use Magento\Payment\Gateway\Request\BuilderInterface;

class GiftcardsDataBuilder implements BuilderInterface
{
    /**
     * @var GiftcardsConfig
     */
    protected $giftcardsConfig;

    /**
     * @param GiftcardsConfig $giftcardsConfig
     */
    public function __construct(GiftcardsConfig $giftcardsConfig)
    {
        $this->giftcardsConfig = $giftcardsConfig;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();

        $availableCards = $this->giftcardsConfig->getAllowedGiftcards($order->getStore());

        $availableCards = $paymentDO->getPayment()->getAdditionalInformation('giftcard_method')
            ? $paymentDO->getPayment()->getAdditionalInformation('giftcard_method')
            : $availableCards . ',ideal';

        return [
            'servicesSelectableByClient' => $availableCards,
            'continueOnIncomplete'       => 'RedirectToHTML',
        ];
    }
}

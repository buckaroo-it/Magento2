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
use Magento\Payment\Gateway\Request\BuilderInterface;

class GiftcardInlineDataBuilder implements BuilderInterface
{
    /**
     * Card types mapping
     */
    private array $cardTypes = [
        'tcs' => [
            'number' => 'tcsCardnumber',
            'pin'    => 'tcsValidationCode',
        ],
        'fashioncheque' => [
            'number' => 'fashionChequeCardNumber',
            'pin'    => 'fashionChequePin',
        ]
    ];

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        $cardId = $paymentDO->getPayment()->getAdditionalInformation('giftcard_id');
        $cardNumber = $paymentDO->getPayment()->getAdditionalInformation('giftcard_number');
        $pin = $paymentDO->getPayment()->getAdditionalInformation('giftcard_pin');

        if (!$cardId || !$cardNumber || !$pin) {
            throw new \InvalidArgumentException('Giftcard ID, number and pin are required');
        }

        $cardNumberParam = $this->getParameterNameCardNumber($cardId);
        $pinParam = $this->getParameterNameCardPin($cardId);

        return [
            'payment_method' => 'giftcards',
            'name' => $cardId,
            $cardNumberParam => $cardNumber,
            $pinParam => $pin,
        ];
    }

    /**
     * Determine parameter name for Card number
     */
    private function getParameterNameCardNumber(string $cardId): string
    {
        if (isset($this->cardTypes[$cardId])) {
            return $this->cardTypes[$cardId]['number'];
        }

        if ($this->isCustom($cardId)) {
            return 'intersolveCardnumber';
        }

        return 'cardNumber';
    }

    /**
     * Determine parameter name for Pin
     */
    private function getParameterNameCardPin(string $cardId): string
    {
        if (isset($this->cardTypes[$cardId])) {
            return $this->cardTypes[$cardId]['pin'];
        }

        if ($this->isCustom($cardId)) {
            return 'intersolvePIN';
        }

        return 'pin';
    }

    /**
     * Check if is custom giftcard
     */
    private function isCustom(string $cardId): bool
    {
        return stripos($cardId, 'customgiftcard') === false;
    }
}

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

namespace Buckaroo\Magento2\Model\PartialPayment;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Voucher;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;

/**
 * Knows which gateway service codes are partial payments (a giftcard or a voucher that pays part
 * of an order before the remainder is paid with another method) and how to label them.
 *
 * Giftcards are rows in the buckaroo_magento2_giftcard table. The voucher is not, so every place
 * that recognises or labels a partial payment asks this class instead of the giftcard table.
 */
class PartialPaymentMethods
{
    public const TYPE_GIFTCARD = 'giftcard';
    public const TYPE_VOUCHER = 'voucher';

    /**
     * @var GiftcardCollection
     */
    private $giftcardCollection;

    /**
     * @param GiftcardCollection $giftcardCollection
     */
    public function __construct(GiftcardCollection $giftcardCollection)
    {
        $this->giftcardCollection = $giftcardCollection;
    }

    /**
     * Whether the service code is a giftcard or the voucher
     *
     * @param string|null $serviceCode
     *
     * @return bool
     */
    public function isPartialPayment(?string $serviceCode): bool
    {
        return $this->find($serviceCode) !== null;
    }

    /**
     * Whether the service code is the voucher
     *
     * @param string|null $serviceCode
     *
     * @return bool
     */
    public function isVoucher(?string $serviceCode): bool
    {
        return $serviceCode === Voucher::SERVICE_CODE;
    }

    /**
     * Display label of a partial payment, or null when the service code is not one
     *
     * @param string|null $serviceCode
     *
     * @return string|null
     */
    public function getLabel(?string $serviceCode): ?string
    {
        $method = $this->find($serviceCode);

        return $method === null ? null : $method['label'];
    }

    /**
     * Code, label, logo and type of a partial payment, or null when the service code is not one
     *
     * @param string|null $serviceCode
     *
     * @return array{code: string, label: string, logo: ?string, type: string}|null
     */
    public function find(?string $serviceCode): ?array
    {
        if ($serviceCode === null || $serviceCode === '') {
            return null;
        }

        if ($this->isVoucher($serviceCode)) {
            return [
                'code'  => $serviceCode,
                'label' => (string)__('Voucher'),
                'logo'  => null,
                'type'  => self::TYPE_VOUCHER,
            ];
        }

        $giftcard = $this->giftcardCollection->getItemByColumnValue('servicecode', $serviceCode);
        if ($giftcard === null) {
            return null;
        }

        return [
            'code'  => $serviceCode,
            'label' => (string)$giftcard['label'],
            'logo'  => $giftcard['logo'] ?? null,
            'type'  => self::TYPE_GIFTCARD,
        ];
    }
}

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

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Magento\Payment\Helper\Data as PaymentHelper;

/**
 * Translates a Buckaroo service code into the Magento payment method code that handles it.
 *
 * The two are not the same vocabulary. Buckaroo calls Bancontact "bancontactmrcash" while the method
 * registered here is "buckaroo_magento2_mrcash", and a card is reported per brand ("visa") while a
 * single method handles all of them. Writing the service code onto the order as if it were a method
 * code produces something Magento cannot resolve, which costs the order its online refund.
 *
 * BuckarooAdapter carries the opposite map, but it cannot simply be inverted: several method codes
 * share one service code (afterpay and afterpay2 are both "afterpaydigiaccept"), so the reverse
 * direction is ambiguous. Only translations that are unambiguous belong here; anything unknown
 * returns null, and the caller is expected to leave the order's method alone rather than guess.
 */
class PaymentMethodCodeResolver
{
    /**
     * Prefix every payment method this module registers carries.
     */
    private const METHOD_CODE_PREFIX = 'buckaroo_magento2_';

    /**
     * The method that handles every card brand.
     */
    private const CREDITCARD_METHOD_CODE = 'creditcard';

    /**
     * The method that handles every giftcard brand.
     */
    private const GIFTCARDS_METHOD_CODE = 'giftcards';

    /**
     * @var Creditcard
     */
    private Creditcard $creditcardConfig;

    /**
     * @var PaymentHelper
     */
    private PaymentHelper $paymentHelper;

    /**
     * @var GiftcardCollection
     */
    private GiftcardCollection $giftcardCollection;

    /**
     * @var array
     */
    private array $serviceToMethod;

    /**
     * @param Creditcard         $creditcardConfig
     * @param PaymentHelper      $paymentHelper
     * @param GiftcardCollection $giftcardCollection
     * @param array              $serviceToMethod
     */
    public function __construct(
        Creditcard $creditcardConfig,
        PaymentHelper $paymentHelper,
        GiftcardCollection $giftcardCollection,
        array $serviceToMethod = []
    ) {
        $this->creditcardConfig = $creditcardConfig;
        $this->paymentHelper = $paymentHelper;
        $this->giftcardCollection = $giftcardCollection;
        $this->serviceToMethod = $serviceToMethod;
    }

    /**
     * The full Magento method code for a Buckaroo service code, or null when it is not known.
     *
     * @param string $serviceCode
     *
     * @return string|null
     */
    public function resolve(string $serviceCode): ?string
    {
        $serviceCode = strtolower(trim($serviceCode));

        if ($serviceCode === '') {
            return null;
        }

        if (isset($this->serviceToMethod[$serviceCode])) {
            return self::METHOD_CODE_PREFIX . $this->serviceToMethod[$serviceCode];
        }

        // Most services are named the same as the method that handles them, so no translation is
        // needed. It is still checked against the registered methods rather than assumed, which is
        // the whole point of this class.
        $candidate = self::METHOD_CODE_PREFIX . $serviceCode;

        if ($this->isRegisteredMethod($candidate)) {
            return $candidate;
        }

        if ($this->isCardBrand($serviceCode)) {
            return self::METHOD_CODE_PREFIX . self::CREDITCARD_METHOD_CODE;
        }

        if ($this->isGiftcardBrand($serviceCode)) {
            return self::METHOD_CODE_PREFIX . self::GIFTCARDS_METHOD_CODE;
        }

        return null;
    }

    /**
     * Whether the service code is one of the giftcards the merchant has configured.
     *
     * A push names the giftcard brand it was paid with, "vvvgiftcard" or "boekenbon" and so on, never
     * the generic "giftcard" that the PayLink settings offer. The configured giftcards are the list
     * to check against, so a brand added there needs no second edit.
     *
     * @param string $serviceCode
     *
     * @return bool
     */
    private function isGiftcardBrand(string $serviceCode): bool
    {
        return (bool)$this->giftcardCollection->getItemByColumnValue('servicecode', $serviceCode);
    }

    /**
     * Whether Magento has a payment method registered under this code.
     *
     * Registered rather than active: an order paid through a method the merchant has since switched
     * off must still resolve, or it loses its refund.
     *
     * @param string $methodCode
     *
     * @return bool
     */
    private function isRegisteredMethod(string $methodCode): bool
    {
        return array_key_exists($methodCode, $this->paymentHelper->getPaymentMethods());
    }

    /**
     * Whether the service code is one of the card brands the creditcard method accepts.
     *
     * The brand list is read from the creditcard config provider rather than repeated here, so a
     * brand added there is covered without a second edit.
     *
     * @param string $serviceCode
     *
     * @return bool
     */
    private function isCardBrand(string $serviceCode): bool
    {
        foreach ($this->creditcardConfig->getIssuers() as $issuer) {
            if (isset($issuer['code']) && strtolower((string)$issuer['code']) === $serviceCode) {
                return true;
            }
        }

        return false;
    }
}

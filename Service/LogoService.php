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

namespace Buckaroo\Magento2\Service;

use Buckaroo\Magento2\Model\PaymentMethodCodeResolver;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;

class LogoService
{
    /**
     * @var Repository
     */
    protected $assetRepo;

    /**
     * @var UrlInterface
     */
    protected $baseUrl;

    /**
     * @var PaymentMethodCodeResolver
     */
    private PaymentMethodCodeResolver $paymentMethodCodeResolver;

    /**
     * @param Repository                $assetRepo
     * @param UrlInterface              $baseUrl
     * @param PaymentMethodCodeResolver $paymentMethodCodeResolver
     */
    public function __construct(
        Repository $assetRepo,
        UrlInterface $baseUrl,
        PaymentMethodCodeResolver $paymentMethodCodeResolver
    ) {
        $this->assetRepo = $assetRepo;
        $this->baseUrl = $baseUrl;
        $this->paymentMethodCodeResolver = $paymentMethodCodeResolver;
    }

    /**
     * Get payment method logo
     *
     * Get the logo for a Buckaroo service code.
     *
     * A push reports the service that was used, which is a different vocabulary from the method
     * codes this map is keyed on: Bancontact arrives as "bancontactmrcash" while the logo is filed
     * under "mrcash". Translating first is what makes the lookup succeed.
     *
     * @param string $serviceCode
     * @param bool   $backend
     *
     * @return string
     */
    public function getPaymentByServiceCode(string $serviceCode, bool $backend = false): string
    {
        $methodCode = $this->paymentMethodCodeResolver->resolve($serviceCode);

        if ($methodCode !== null) {
            $serviceCode = str_replace('buckaroo_magento2_', '', $methodCode);
        }

        return $this->getPayment($serviceCode, $backend);
    }

    /**
     * Get the logo for a payment method code
     *
     * @param string $paymentCode
     * @param bool   $backend
     *
     * @return string
     */
    public function getPayment(string $paymentCode, bool $backend = false): string
    {
        // Convert to lowercase for case-insensitive server compatibility
        $paymentCode = strtolower($paymentCode);

        $mappings = [
            "afterpay2"        => "svg/riverty.svg",
            "afterpay20"       => "svg/riverty.svg",
            "capayablein3"     => "svg/in3.svg",
            "capayablepostpay" => "svg/in3.svg",
            "creditcard"       => "svg/creditcards.svg",
            "creditcards"      => "svg/creditcards.svg",
            "giftcards"        => "svg/giftcards.svg",
            "ideal"            => "svg/ideal-wero.svg",
            "klarnakp"         => "svg/klarna.svg",
            "mrcash"           => "svg/bancontact.svg",
            "p24"              => "svg/przelewy24.svg",
            "sepadirectdebit"  => "svg/sepa-directdebit.svg",
            "pospayment"       => "svg/pos.svg",
            "transfer"         => "svg/sepa-credittransfer.svg",
            "buckaroovoucher"  => "svg/vouchers.svg",
            "voucher"          => "svg/vouchers.svg",
            "paybybank"        => "svg/paybybank.svg",
            "knaken"           => "svg/gosettle.svg"
        ];

        if ($backend === true) {
            $mappings = array_merge($mappings, [
                "paybybank" => "svg/paybybank.svg",
            ]);
        }

        $name = "svg/{$paymentCode}.svg";

        if (isset($mappings[$paymentCode])) {
            $name = $mappings[$paymentCode];
        }

        return $this->assetRepo->getUrl("Buckaroo_Magento2::images/{$name}");
    }

    /**
     * Get Bank Transfer payment method logo URL by config option.
     *
     * @param string $option One of TransferPaymentMethodLogo::OPTION_* constants
     * @return string
     */
    public function getTransferLogo(string $option): string
    {
        $path = $option === \Buckaroo\Magento2\Model\Config\Source\TransferPaymentMethodLogo::OPTION_SEPA_CREDIT_TRANSFER
            ? 'images/svg/sepa-directdebit.svg'
            : 'images/svg/sepa-credittransfer.svg';

        return $this->assetRepo->getUrl("Buckaroo_Magento2::{$path}");
    }

    /**
     * Get logo URL for a module asset path.
     *
     * @param string $path
     * @return string
     */
    public function getLogoUrl(string $path): string
    {
        return $this->assetRepo->getUrl("Buckaroo_Magento2::{$path}");
    }

    /**
     * Get gift card logo URL, falling back to the default logo by code.
     *
     * @param array $giftcard
     * @return string
     */
    public function getGiftcardLogo(array $giftcard): string
    {
        if (isset($giftcard['logo']) &&
            is_string($giftcard['logo']) &&
            strlen(trim($giftcard['logo']))
        ) {
            return $this->baseUrl->getDirectUrl(
                $giftcard['logo'],
                ['_type' => UrlInterface::URL_TYPE_MEDIA]
            );
        }

        return $this->getGiftcardLogoDefaults($giftcard['code']);
    }

    /**
     * Get gift card logo url by code
     *
     * @param string $code
     *
     * @return string
     */
    public function getGiftcardLogoDefaults(string $code): string
    {
        // Convert to lowercase for case-insensitive server compatibility
        $code = strtolower($code);

        $name = "svg/giftcards.svg";

        $mappings = [
            "ajaxgiftcard"               => "ajaxgiftcard",
            "boekenbon"                  => "boekenbon",
            "cjpbetalen"                 => "cjp",
            "digitalebioscoopbon"        => "nationaletuinbon",
            "fashioncheque"              => "fashioncheque",
            "fashionucadeaukaart"        => "fashiongiftcard",
            "nationaletuinbon"           => "nationalebioscoopbon",
            "nationaleentertainmentcard" => "nationaleentertainmentcard",
            "podiumcadeaukaart"          => "podiumcadeaukaart",
            "sportfitcadeau"             => "sport-fitcadeau",
            "vvvgiftcard"                => "vvvgiftcard",
            "buckaroovoucher"            => "vouchers"
        ];

        if (isset($mappings[$code])) {
            $name = "giftcards/{$mappings[$code]}.svg";
            if ($mappings[$code] == 'vouchers') {
                $name = "svg/{$mappings[$code]}.svg";
            }
        }

        return $this->assetRepo->getUrl("Buckaroo_Magento2::images/{$name}");
    }

    /**
     * Get creditcard logo by code
     *
     * @param string $code
     *
     * @return string
     */
    public function getCreditcard(string $code): string
    {
        if ($code === 'cartebleuevisa') {
            $code = 'cartebleue';
        }

        // Convert to lowercase for case-insensitive server compatibility
        $code = strtolower($code);

        return $this->assetRepo->getUrl("Buckaroo_Magento2::images/creditcards/{$code}.svg");
    }

    /**
     * Get URL for an arbitrary asset path.
     *
     * @param string $path
     * @return string
     */
    public function getAssetUrl(string $path): string
    {
        return $this->assetRepo->getUrl($path);
    }
}

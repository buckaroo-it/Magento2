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

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;

class LogoService
{
    /**
     * @var Repository
     */
    protected Repository $assetRepo;

    /**
     * @var UrlInterface
     */
    protected UrlInterface $baseUrl;

    /**
     * @param Repository $assetRepo
     * @param UrlInterface $baseUrl
     */
    public function __construct(
        Repository $assetRepo,
        UrlInterface $baseUrl
    ) {
        $this->assetRepo = $assetRepo;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Get payment method logo
     *
     * @param string $paymentCode
     * @param bool $backend
     * @return string
     */
    public function getPayment(string $paymentCode, bool $backend = false): string
    {
        $mappings = [
            "afterpay2"        => "svg/afterpay.svg",
            "afterpay20"       => "svg/afterpay.svg",
            "capayablein3"     => "svg/ideal-in3.svg",
            "capayablepostpay" => "svg/ideal-in3.svg",
            "creditcard"       => "svg/creditcards.svg",
            "creditcards"      => "svg/creditcards.svg",
            "giftcards"        => "svg/giftcards.svg",
            "ideal"            => "svg/ideal.svg",
            "klarnain"         => "svg/klarna.svg",
            "klarnakp"         => "svg/klarna.svg",
            "mrcash"           => "svg/bancontact.svg",
            "p24"              => "svg/przelewy24.svg",
            "sepadirectdebit"  => "svg/sepa-directdebit.svg",
            "sofortbanking"    => "svg/sofort.svg",
            "pospayment"       => "svg/pos.svg",
            "transfer"         => "svg/sepa-credittransfer.svg",
            "buckaroovoucher"  => "svg/vouchers.svg",
            "voucher"          => "svg/vouchers.svg",
            "paybybank"        => "paybybank.gif"
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

    public function getLogoUrl(string $path): string
    {
        return $this->assetRepo->getUrl("Buckaroo_Magento2::{$path}");
    }

    public function getGiftcardLogo(array $giftcard): string
    {
        if (
            isset($giftcard['logo']) &&
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
     * @return string
     */
    public function getGiftcardLogoDefaults(string $code): string
    {
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
     * @return string
     */
    public function getCreditcard(string $code): string
    {
        if ($code === 'cartebleuevisa') {
            $code = 'cartebleue';
        }

        return $this->assetRepo->getUrl("Buckaroo_Magento2::images/creditcards/{$code}.svg");
    }

    public function getAssetUrl(string $path): string
    {
        return $this->assetRepo->getUrl($path);
    }
}

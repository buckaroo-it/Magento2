<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Block;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;

class Info extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'Buckaroo_Magento2::info/payment_method.phtml';
    protected $groupTransaction;
    protected $giftcardCollection;

    protected  Repository $assetRepo;

    protected UrlInterface $baseUrl;

    /**
     * @param \Magento\Framework\View\Element\Template\Context     $context
     * @param array                                                $data
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard $configProvider
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        PaymentGroupTransaction $groupTransaction,
        GiftcardCollection $giftcardCollection,
        Repository $assetRepo,
        UrlInterface $baseUrl,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->groupTransaction = $groupTransaction;
        $this->giftcardCollection = $giftcardCollection;
        $this->assetRepo = $assetRepo;
        $this->baseUrl = $baseUrl;
    }

    public function getGiftCards()
    {
        $result = [];

        if ($this->getInfo()->getOrder() && $this->getInfo()->getOrder()->getIncrementId()) {
            $items = $this->groupTransaction->getGroupTransactionItems($this->getInfo()->getOrder()->getIncrementId());
            foreach ($items as $key => $giftcard) {
                if ($foundGiftcard = $this->giftcardCollection
                    ->getItemByColumnValue('servicecode', $giftcard['servicecode'])
                ) {
                    $result[] = [
                        'code' => $giftcard['servicecode'],
                        'label' => $foundGiftcard['label'],
                        'logo' => $foundGiftcard['logo']
                    ];
                }
            }
        }

        return $result;
    }

    public function getPayPerEmailMethod()
    {
        $payment = $this->getInfo()->getOrder()->getPayment();
        if ($servicecode = $payment->getAdditionalInformation('isPayPerEmail')) {
            return [
                    'label' => __('Buckaroo PayPerEmail'),
                ];
        }
        return false;
    }

    public function getPaymentLogo(string $method): string
    {
        $mappings = [
            "afterpay2" => "svg/riverty.svg",
            "afterpay20" => "svg/riverty.svg",
            "capayablein3" => "svg/in3.svg",
            "capayablepostpay" => "svg/in3.svg",
            "creditcard" => "svg/creditcards.svg",
            "creditcards" => "svg/creditcards.svg",
            "giftcards" => "svg/giftcards.svg",
            "idealprocessing" => "svg/ideal.svg",
            "klarnain" => "svg/klarna.svg",
            "klarnakp" => "svg/klarna.svg",
            "mrcash" => "svg/bancontact.svg",
            "p24" => "svg/przelewy24.svg",
            "sepadirectdebit" => "svg/sepa-directdebit.svg",
            "emandate" => "emandate.png",
            "pospayment" => "pos.png",
            "transfer" => "svg/sepa-credittransfer.svg",
            "paybybank" => "paybybank.gif",
            "knaken" => "svg/gosettle.svg"
        ];

        $name = "svg/{$method}.svg";

        if(isset($mappings[$method])) {
            $name = $mappings[$method];
        }

        return $this->assetRepo->getUrl("Buckaroo_Magento2::images/{$name}");
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
    private function getGiftcardLogoDefaults(string $code) {
        $name = "svg/giftcards.svg";

        $mappings = [
            "ajaxgiftcard" => "ajaxgiftcard",
            "boekenbon" => "boekenbon",
            "cjpbetalen" => "cjp",
            "digitalebioscoopbon" => "nationaletuinbon",
            "fashioncheque" => "fashioncheque",
            "fashionucadeaukaart" => "fashiongiftcard",
            "nationaletuinbon" => "nationalebioscoopbon",
            "nationaleentertainmentcard" => "nationaleentertainmentcard",
            "podiumcadeaukaart" => "podiumcadeaukaart",
            "sportfitcadeau" => "sport-fitcadeau",
            "vvvgiftcard" => "vvvgiftcard"
        ];

        if(isset($mappings[$code])) {
            $name = "giftcards/{$mappings[$code]}.svg";
        }
        return $this->assetRepo->getUrl("Buckaroo_Magento2::images/{$name}");
    }
    public function getCreditcardLogo(string $code): string
    {
        if($code === 'cartebleuevisa') {
            $code = 'cartebleue';
        }

        return $this->assetRepo->getUrl("Buckaroo_Magento2::images/creditcards/{$code}.svg");
    }
}

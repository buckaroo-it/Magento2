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

namespace Buckaroo\Magento2\Block\Info;

use Buckaroo\Magento2\Block\Info;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\UrlInterface;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard as ConfigProviderCreditcard;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Buckaroo\Magento2\Service\LogoService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template\Context;

class Creditcard extends Info
{
    /**
     * @var string|null
     */
    protected $cardType = null;

    /**
     * @var array|null
     */
    protected $mpiStatus = null;

    /**
     * @var ConfigProviderCreditcard
     */
    protected $configProvider;

    /**
     * @param Context                       $context
     * @param PaymentGroupTransaction       $groupTransaction
     * @param GiftcardCollection            $giftcardCollection
     * @param LogoService                   $logoService
     * @param UrlInterface                  $baseUrl
     * @param array                         $data
     * @param ConfigProviderCreditcard|null $configProvider
     */
    public function __construct(
        Context $context,
        PaymentGroupTransaction $groupTransaction,
        GiftcardCollection $giftcardCollection,
        LogoService $logoService,
        UrlInterface $baseUrl,
        array $data = [],
        ?ConfigProviderCreditcard $configProvider = null
    ) {
        parent::__construct(
            $context,
            $groupTransaction,
            $giftcardCollection,
            $logoService,
            $baseUrl,
            $data,
            $configProvider
        );
        $this->configProvider = $configProvider;
    }

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('Buckaroo_Magento2::info/creditcard.phtml');
    }

    /**
     * Get the order's MPI status.
     *
     * @throws LocalizedException
     *
     * @return array
     */
    public function getMpiStatus()
    {
        if ($this->mpiStatus === null) {
            $this->mpiStatus = $this->getInfo()->getAdditionalInformation('buckaroo_mpi_status');
        }
        return $this->mpiStatus;
    }

    /**
     * Get card code or null when the card is unknown.
     *
     * @throws LocalizedException
     *
     * @return string|null
     */
    public function getCardCode(): ?string
    {
        $cardType = $this->getCardType();

        if ($cardType === null) {
            return null;
        }

        return $this->configProvider->getCardCode($cardType);
    }

    /**
     * Get the selected creditcard for this order.
     *
     * @throws LocalizedException
     *
     * @return string|null
     */
    public function getCardType(): ?string
    {
        if ($this->cardType === null) {
            $info = $this->getInfo();
            $cardCode = $info->getAdditionalInformation('card_type');

            if (empty($cardCode)) {
                $cardCode = $info->getAdditionalInformation(BuckarooAdapter::BUCKAROO_ACTUAL_PAYMENT_METHOD);
            }

            $this->cardType = $this->configProvider->getCardName($cardCode);
        }

        return $this->cardType;
    }
}

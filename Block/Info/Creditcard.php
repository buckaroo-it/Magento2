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

namespace Buckaroo\Magento2\Block\Info;

use Buckaroo\Magento2\Block\Info;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\UrlInterface;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard as ConfigProviderCreditcard;
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
        parent::__construct($context, $groupTransaction, $giftcardCollection, $logoService, $baseUrl, $data);
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
     * Get card code
     *
     * @throws LocalizedException
     * @return string
     */
    public function getCardCode()
    {
        $cardType = $this->getCardType();

        return $this->configProvider->getCardCode($cardType);
    }

    /**
     * Get the selected creditcard for this order.
     *
     * @throws LocalizedException
     * @return string
     */
    public function getCardType()
    {
        if ($this->cardType === null) {
            $this->cardType = $this->configProvider->getCardName(
                $this->getInfo()->getAdditionalInformation('card_type')
            );
        }
        return $this->cardType;
    }

    /**
     * Render as PDF
     *
     * @return string
     */
    public function toPdf()
    {
        $this->setTemplate('Magento_OfflinePayments::info/pdf/checkmo.phtml');
        return $this->toHtml();
    }
}

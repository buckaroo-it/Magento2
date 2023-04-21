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
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard as ConfigProviderCreditcard;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\Template\Context;

class Creditcard extends Info
{
    /**
     * @var string
     */
    protected $cardType;

    /**
     * @var array
     */
    protected $mpiStatus;

    /**
     * @var ConfigProviderCreditcard
     */
    protected $configProvider;

    /**
     * @var string
     */
    protected $_template = 'Buckaroo_Magento2::info/creditcard.phtml';

    /**
     * @param Context $context
     * @param PaymentGroupTransaction $groupTransaction
     * @param GiftcardCollection $giftcardCollection
     * @param Repository $assetRepo
     * @param array $data
     * @param ConfigProviderCreditcard|null $configProvider
     */
    public function __construct(
        Context $context,
        PaymentGroupTransaction $groupTransaction,
        GiftcardCollection $giftcardCollection,
        Repository $assetRepo,
        array $data = [],
        ConfigProviderCreditcard $configProvider = null
    ) {
        parent::__construct($context, $groupTransaction, $giftcardCollection, $assetRepo, $data);
        $this->configProvider = $configProvider;
    }

    /**
     * Get the order's MPI status.
     *
     * @return array
     * @throws LocalizedException
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
     * @return string
     * @throws LocalizedException
     */
    public function getCardCode()
    {
        $cardType = $this->getCardType();

        return $this->configProvider->getCardCode($cardType);
    }

    /**
     * Get the selected creditcard for this order.
     *
     * @return string
     * @throws LocalizedException
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

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

namespace Buckaroo\Magento2\Block\Info;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;

class Creditcard extends \Buckaroo\Magento2\Block\Info
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
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard
     */
    protected $configProvider;

    /**
     * @var string
     */
    protected $_template = 'Buckaroo_Magento2::info/creditcard.phtml';

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        PaymentGroupTransaction $groupTransaction,
        GiftcardCollection $giftcardCollection,
        array $data = [],
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard $configProvider = null
    ) {
        parent::__construct($context, $groupTransaction, $giftcardCollection, $data);
        $this->configProvider = $configProvider;
    }

    /**
     * Get the selected creditcard for this order.
     *
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
     * Get the order's MPI status.
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
     * @return string
     */
    public function getCardCode()
    {
        $cardType = $this->getCardType();

        return $this->configProvider->getCardCode($cardType);
    }

    /**
     * @return string
     */
    public function toPdf()
    {
        $this->setTemplate('Magento_OfflinePayments::info/pdf/checkmo.phtml');
        return $this->toHtml();
    }
}

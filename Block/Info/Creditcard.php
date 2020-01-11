<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */

namespace TIG\Buckaroo\Block\Info;

class Creditcard extends \TIG\Buckaroo\Block\Info
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
     * @var \TIG\Buckaroo\Model\ConfigProvider\Method\Creditcard
     */
    protected $configProvider;

    // @codingStandardsIgnoreStart
    /**
     * @var string
     */
    protected $_template = 'TIG_Buckaroo::info/creditcard.phtml';
    // @codingStandardsIgnoreEnd

    /**
     * @param \Magento\Framework\View\Element\Template\Context     $context
     * @param array                                                $data
     * @param \TIG\Buckaroo\Model\ConfigProvider\Method\Creditcard $configProvider
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = [],
        \TIG\Buckaroo\Model\ConfigProvider\Method\Creditcard $configProvider = null
    ) {
        parent::__construct($context, $data);

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

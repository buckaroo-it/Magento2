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

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

class TokenEncryptedDataBuilder extends AbstractDataBuilder
{
    /**
     * Core store config
     *
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $useClientSide = $this->getConfigData('client_side');
        $additionalInformation = $this->getPayment()->getAdditionalInformation();

        if ($useClientSide && isset($additionalInformation['client_side_mode'])
            && ($additionalInformation['client_side_mode'] == 'cc')
        ) {
            if (!isset($additionalInformation['customer_encrypteddata'])) {
                throw new Exception(
                    __('An error occured trying to send the encrypted bancontact data to Buckaroo.')
                );
            }
            return ['encryptedCardData' => $additionalInformation['customer_encrypteddata']];
        } else {
            return ['saveToken' => true];
        }
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param  string                $field
     * @param  int|string|null|Store $storeId
     * @throws LocalizedException
     * @return mixed
     */
    public function getConfigData(string $field, $storeId = null)
    {
        if (null === $storeId) {
            $storeId = $this->getOrder()->getStoreId();
        }
        $path = 'payment/' . $this->getPayment()->getMethodInstance()->getCode() . '/' . $field;
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}

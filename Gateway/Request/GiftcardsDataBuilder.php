<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards as GiftcardsConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class GiftcardsDataBuilder extends AbstractDataBuilder
{
    protected ScopeConfigInterface $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $availableCards = $this->scopeConfig->getValue(
            GiftcardsConfig::XPATH_GIFTCARDS_ALLOWED_GIFTCARDS,
            ScopeInterface::SCOPE_STORE,
            $this->getOrder()->getStore()
        );

        $availableCards = $this->getPayment()->getAdditionalInformation('giftcard_method') ?? $availableCards . ',ideal';

        return [
            'servicesSelectableByClient' => $availableCards,
            'continueOnIncomplete' => 'RedirectToHTML',
        ];
    }
}

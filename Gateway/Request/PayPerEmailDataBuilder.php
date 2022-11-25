<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;
use Buckaroo\Resources\Constants\Gender;

class PayPerEmailDataBuilder extends AbstractDataBuilder
{
    private PayPerEmail $payPerEmailConfig;
    private Giftcards $giftcardsConfig;

    public function __construct(
        PayPerEmail $payPerEmailConfig,
        Giftcards   $giftcardsConfig
    )
    {
        $this->payPerEmailConfig = $payPerEmailConfig;
        $this->giftcardsConfig = $giftcardsConfig;
    }

    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);
        $storeId = $this->getOrder()->getStoreId();
        $payment = $this->getPayment();

        $data = [
            'merchantSendsEmail' => !$this->payPerEmailConfig->getSendMail(),
            'email' => $payment->getAdditionalInformation('customer_email'),
            'paymentMethodsAllowed' => $this->getPaymentMethodsAllowed($this->payPerEmailConfig, $storeId),
            'attachment' => '',
            'customer' => [
                'gender' => $payment->getAdditionalInformation('customer_gender') ?? Gender::UNKNOWN,
                'firstName' => $payment->getAdditionalInformation('customer_billingFirstName'),
                'lastName' => $payment->getAdditionalInformation('customer_billingLastName')
            ]
        ];

        if ($this->payPerEmailConfig->getExpireDays()) {
            $data['expirationDate'] = date('Y-m-d', time() + $this->payPerEmailConfig->getExpireDays() * 86400);
        }

        return $data;
    }

    private function getPaymentMethodsAllowed($config, $storeId)
    {
        if ($methods = $config->getPaymentMethod($storeId)) {
            $methods = explode(',', (string)$methods);
            $activeCards = '';
            foreach ($methods as $key => $value) {
                if ($value === 'giftcard') {
                    if ($activeCards = $this->giftcardsConfig->getAllowedGiftcards($storeId)) {
                        unset($methods[$key]);
                    }
                }
            }
            if ($activeCards) {
                $methods = array_merge($methods, explode(',', (string)$activeCards));
            }
            $methods = join(',', $methods);
        }
        return $methods;
    }
}

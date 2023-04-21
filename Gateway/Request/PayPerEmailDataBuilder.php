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

use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Resources\Constants\Gender;

class PayPerEmailDataBuilder extends AbstractDataBuilder
{
    /**
     * @var PayPerEmail
     */
    private PayPerEmail $payPerEmailConfig;

    /**
     * @var Giftcards
     */
    private Giftcards $giftcardsConfig;

    /**
     * @param PayPerEmail $payPerEmailConfig
     * @param Giftcards $giftcardsConfig
     */
    public function __construct(
        PayPerEmail $payPerEmailConfig,
        Giftcards $giftcardsConfig
    ) {
        $this->payPerEmailConfig = $payPerEmailConfig;
        $this->giftcardsConfig = $giftcardsConfig;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);
        $storeId = $this->getOrder()->getStoreId();
        $payment = $this->getPayment();

        $data = [
            'merchantSendsEmail'    => !$this->payPerEmailConfig->hasSendMail(),
            'email'                 => $payment->getAdditionalInformation('customer_email'),
            'paymentMethodsAllowed' => $this->getPaymentMethodsAllowed($this->payPerEmailConfig, $storeId),
            'attachment'            => '',
            'customer'              => [
                'gender'    => $payment->getAdditionalInformation('customer_gender') ?? Gender::UNKNOWN,
                'firstName' => $payment->getAdditionalInformation('customer_billingFirstName'),
                'lastName'  => $payment->getAdditionalInformation('customer_billingLastName')
            ]
        ];

        if ($this->payPerEmailConfig->getExpireDays()) {
            $data['expirationDate'] = date('Y-m-d', time() + $this->payPerEmailConfig->getExpireDays() * 86400);
        }

        return $data;
    }

    /**
     * Retrieves the allowed payment methods based on the PayPerEmail configuration and store ID.
     *
     * @param PayPerEmail $config
     * @param int|null $storeId
     * @return string
     */
    private function getPaymentMethodsAllowed(PayPerEmail $config, ?int $storeId): string
    {
        if ($methods = $config->getPaymentMethod($storeId)) {
            $methods = explode(',', (string)$methods);
            $activeCards = '';
            foreach ($methods as $key => $value) {
                if ($value === 'giftcard'
                    && $activeCards = $this->giftcardsConfig->getAllowedGiftcards($storeId)) {
                    unset($methods[$key]);
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

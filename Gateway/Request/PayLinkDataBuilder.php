<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Model\ConfigProvider\Method\PayLink;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;

class PayLinkDataBuilder extends AbstractDataBuilder
{
    /**
     * @var PayLink
     */
    private PayLink $payLinkConfig;
    /**
     * @var Giftcards
     */
    private Giftcards $giftcardsConfig;

    /**
     * @param PayLink $payLinkConfig
     * @param Giftcards $giftcardsConfig
     */
    public function __construct(
        PayLink $payLinkConfig,
        Giftcards $giftcardsConfig
    ) {
        $this->payLinkConfig = $payLinkConfig;
        $this->giftcardsConfig = $giftcardsConfig;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);
        $storeId = $this->getOrder()->getStoreId();
        $order = $this->getOrder();

        $data = [
            'merchantSendsEmail' => true,
            'email' => $order->getCustomerEmail(),
            'paymentMethodsAllowed' => $this->getPaymentMethodsAllowed($this->payLinkConfig, $storeId),
            'attachment' => '',
            'customer' => [
                'gender' => $order->getCustomerGender() ?? 1,
                'firstName' => $order->getCustomerFirstname(),
                'lastName' => $order->getCustomerLastname()
            ]
        ];

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

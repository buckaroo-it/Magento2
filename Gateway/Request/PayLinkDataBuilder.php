<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
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
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();
        $storeId = $order->getStoreId();

        return [
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
    }

    /**
     * Get Payment Methods Allowed
     *
     * @param PayLink $config
     * @param int|string|null $storeId
     * @return string
     */
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

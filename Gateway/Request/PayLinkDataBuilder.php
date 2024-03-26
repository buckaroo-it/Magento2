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

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayLink;

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
            'merchantSendsEmail'    => false,
            'email'                 => $order->getCustomerEmail(),
            'paymentMethodsAllowed' => $this->getPaymentMethodsAllowed($this->payLinkConfig, $storeId),
            'attachment'            => '',
            'customer'              => [
                'gender'    => $order->getCustomerGender() ?? 1,
                'firstName' => $order->getCustomerFirstname(),
                'lastName'  => $order->getCustomerLastname()
            ]
        ];
    }

    /**
     * Get Payment Methods Allowed
     *
     * @param PayLink $config
     * @param string|int|null $storeId
     * @return string
     */
    private function getPaymentMethodsAllowed(PayLink $config, $storeId): string
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
        return $methods ?? '';
    }
}

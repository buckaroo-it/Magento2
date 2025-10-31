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

use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal;
use Buckaroo\Magento2\Model\PaypalStateCodes;
use Buckaroo\Magento2\Service\PayReminderService;
use Magento\Sales\Model\Order\Address;

class PaypalExtrainfoDataBuilder extends AbstractDataBuilder
{
    /**
     * @var Paypal
     */
    protected $configProviderPaypal;

    /**
     * @var PaypalStateCodes
     */
    private $paypalStateCodes;

    /**
     * @var PayReminderService
     */
    private $payReminderService;

    /**
     * @param Paypal             $configProviderPaypal
     * @param PaypalStateCodes   $paypalStateCodes
     * @param PayReminderService $payReminderService
     */
    public function __construct(
        Paypal $configProviderPaypal,
        PaypalStateCodes $paypalStateCodes,
        PayReminderService $payReminderService
    ) {
        $this->configProviderPaypal = $configProviderPaypal;
        $this->paypalStateCodes = $paypalStateCodes;
        $this->payReminderService = $payReminderService;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $sellersProtectionActive = (bool)$this->configProviderPaypal->getSellersProtection();
        $order = $this->getOrder();
        $shippingAddress = $order->getShippingAddress();

        if ($shippingAddress === null) {
            $shippingAddress = $order->getBillingAddress();
        }

        if ($shippingAddress === null) {
            return [];
        }

        $isPaypalExpress = $this->getPayment()->getAdditionalInformation('express_order_id')!== null;

        if (!$sellersProtectionActive || !$shippingAddress || $isPaypalExpress) {
            return [];
        }

        $serviceAction = $this->payReminderService->getServiceAction($this->getOrder()->getIncrementId());
        if (!empty($serviceAction) && ($serviceAction == 'PayRemainder')) {
            return [];
        }

        $data = [
            'customer'        => [
                'name' => mb_substr($shippingAddress->getName(), 0, 32)
            ],
            'address'         => [
                'street'  => mb_substr($shippingAddress->getStreetLine(1), 0, 100),
                'city'    => mb_substr($shippingAddress->getCity(), 0, 40),
                'state'   => $this->getStateOrProvince($shippingAddress),
                'zipcode' => mb_substr($shippingAddress->getPostcode(), 0, 20),
                'country' => $shippingAddress->getCountryId(),
            ],
            'phone'           => [
                'mobile' => $shippingAddress->getTelephone() ?? ''
            ],
            'addressOverride' => true
        ];

        if ($this->payReminderService->getServiceAction($this->getOrder()->getIncrementId()) !== 'payRemainder') {
            $this->payReminderService->setServiceAction('ExtraInfo');
        }

        return $data;
    }

    /**
     * Get state or province code of the shipping address
     *
     * @param Address $shippingAddress
     *
     * @return string
     */
    private function getStateOrProvince(Address $shippingAddress): string
    {
        $shippingRegion = $shippingAddress->getRegion();
        if (!empty($shippingRegion)) {
            $twoCharacterShippingRegion = $this->paypalStateCodes->getCodeFromValue(
                $shippingAddress->getCountryId(),
                $shippingAddress->getRegion()
            );

            if ($twoCharacterShippingRegion) {
                return $twoCharacterShippingRegion;
            }
        }

        return '';
    }
}

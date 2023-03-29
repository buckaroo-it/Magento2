<?php

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
     * @param Paypal $configProviderPaypal
     * @param PaypalStateCodes $paypalStateCodes
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

        $sellersProtectionActive = (bool) $this->configProviderPaypal->getSellersProtection();

        if (!$sellersProtectionActive) {
            return [];
        }

        $shippingAddress = $this->getOrder()->getShippingAddress();
        if (!$shippingAddress) {
            return [];
        }

        $serviceAction = $this->payReminderService->getServiceAction($this->getOrder()->getIncrementId());
        if (!empty($serviceAction) && ($serviceAction == 'PayRemainder')) {
            return [];
        }

        $data = [
            'customer'  => [
                'name'      => mb_substr($shippingAddress->getName(), 0, 32)
            ],
            'address'   => [
                'street'       => mb_substr($shippingAddress->getStreetLine(1), 0, 100),
                'city'          => mb_substr($shippingAddress->getCity(), 0, 40),
                'state'         => $this->getStateOrProvince($shippingAddress),
                'zipcode'       => mb_substr($shippingAddress->getPostcode(), 0, 20),
                'country'       => $shippingAddress->getCountryId(),
            ],
            'phone'             => [
                'mobile'        => $shippingAddress->getTelephone() ?? ''
            ],
            'addressOverride' => true
        ];

        $this->payReminderService->setServiceAction('ExtraInfo');

        return $data;
    }

    /**
     * Get state or province code of the shipping address
     *
     * @param Address $shippingAddress
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

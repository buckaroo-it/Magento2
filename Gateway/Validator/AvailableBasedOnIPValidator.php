<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as ConfigProviderMethodFactory;
use Magento\Developer\Helper\Data;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Model\MethodInterface;

class AvailableBasedOnIPValidator extends AbstractValidator
{
    /**
     * @var AccountConfig
     */
    private AccountConfig $accountConfig;

    /**
     * @var \Magento\Developer\Helper\Data
     */
    protected \Magento\Developer\Helper\Data $developmentHelper;

    /**
     * @var ConfigProviderMethodFactory
     */
    private ConfigProviderMethodFactory $configProviderMethodFactory;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param AccountConfig $accountConfig
     * @param ConfigProviderMethodFactory $configProviderMethodFactory
     * @param Data $developmentHelper
     */
    public function __construct(
        ResultInterfaceFactory         $resultFactory,
        AccountConfig                  $accountConfig,
        ConfigProviderMethodFactory    $configProviderMethodFactory,
        \Magento\Developer\Helper\Data $developmentHelper
    ) {
        $this->accountConfig = $accountConfig;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->developmentHelper = $developmentHelper;
        parent::__construct($resultFactory);
    }

    /**
     * Available Based On IP dev/restrict/allow_ips
     *
     * @param array $validationSubject
     * @return ResultInterface
     * @throws \Buckaroo\Magento2\Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $isValid = true;

        if (!isset($validationSubject['paymentMethodInstance']) || !isset($validationSubject['quote'])) {
            return $this->createResult(
                false,
                [__('Payment method instance does not exist')]
            );
        }

        /** @var MethodInterface $paymentMethodInstance */
        $paymentMethodInstance = $validationSubject['paymentMethodInstance'];

        if ($this->accountConfig->getLimitByIp() == 1 || $paymentMethodInstance->getConfigData('limit_by_ip') == 1) {
            $storeId = $validationSubject['quote']->getStoreId() ?? null;
            $isAllowed = $this->developmentHelper->isDevAllowed($storeId);

            if (!$isAllowed) {
                $isValid = false;
            }
        }

        return $this->createResult($isValid);
    }
}

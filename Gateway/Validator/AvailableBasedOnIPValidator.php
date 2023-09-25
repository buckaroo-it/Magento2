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

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderMethodFactory;
use Magento\Developer\Helper\Data;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class AvailableBasedOnIPValidator extends AbstractValidator
{
    /**
     * @var Data
     */
    protected Data $developmentHelper;
    /**
     * @var AccountConfig
     */
    private AccountConfig $accountConfig;
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
        ResultInterfaceFactory $resultFactory,
        AccountConfig $accountConfig,
        ConfigProviderMethodFactory $configProviderMethodFactory,
        Data $developmentHelper
    ) {
        $this->accountConfig = $accountConfig;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->developmentHelper = $developmentHelper;
        parent::__construct($resultFactory);
    }

    /**
     * Check if this payment method is limited by IP.
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $isValid = true;

        $paymentMethodInstance = SubjectReader::readPaymentMethodInstance($validationSubject);

        if ($this->accountConfig->getLimitByIp() == 1 || $paymentMethodInstance->getConfigData('limit_by_ip') == 1) {
            $storeId = SubjectReader::readQuote($validationSubject)->getStoreId() ?? null;
            $isAllowed = $this->developmentHelper->isDevAllowed($storeId);

            if (!$isAllowed) {
                $isValid = false;
            }
        }

        return $this->createResult($isValid);
    }
}

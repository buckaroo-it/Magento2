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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\ConfigProvider\Method\ConfigProviderInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Model\InfoInterface;

class IssuerValidator extends AbstractValidator
{
    /**
     * @var Factory
     */
    private Factory $configProvider;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param Factory $configProvider
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        Factory $configProvider
    ) {
        $this->configProvider = $configProvider;
        parent::__construct($resultFactory);
    }

    /**
     * Validate issuer
     *
     * @param array $validationSubject
     * @return bool|ResultInterface
     * @throws NotFoundException
     * @throws \Exception
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $paymentInfo = SubjectReader::readPayment($validationSubject)->getPayment();

        $skipValidation = $paymentInfo->getAdditionalInformation('buckaroo_skip_validation');
        if ($skipValidation) {
            return $this->createResult(true);
        }

        $chosenIssuer = $paymentInfo->getAdditionalInformation('issuer');

        foreach ($this->getConfig($paymentInfo)->getIssuers() as $issuer) {
            if ($issuer['code'] == $chosenIssuer) {
                return $this->createResult(true);
            }
        }

        return $this->createResult(false, [__('Please select a issuer from the list')]);
    }

    /**
     * Get config provider for specific payment method
     *
     * @param InfoInterface $paymentInfo
     * @return ConfigProviderInterface|false
     */
    protected function getConfig(InfoInterface $paymentInfo)
    {
        try {
            return $this->configProvider->get($paymentInfo->getMethodInstance()->getCode());
        } catch (\Exception $exception) {
            return false;
        }
    }
}

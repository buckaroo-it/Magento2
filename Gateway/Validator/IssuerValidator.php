<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Method\ConfigProviderInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote\Payment;

class IssuerValidator extends AbstractValidator
{
    /**
     * @var Factory
     */
    private $configProvider;

    /**
     * @var HttpRequest
     */
    protected $request;

    /**
     * @var Json
     */
    protected $jsonSerializer;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param Factory                $configProvider
     * @param HttpRequest            $request
     * @param Json                   $jsonSerializer
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        Factory $configProvider,
        HttpRequest $request,
        Json $jsonSerializer
    ) {
        $this->configProvider = $configProvider;
        $this->request = $request;
        $this->jsonSerializer = $jsonSerializer;

        parent::__construct($resultFactory);
    }

    /**
     * Validate issuer
     *
     * @param array $validationSubject
     *
     * @throws LocalizedException
     *
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $paymentDO = SubjectReader::readPayment($validationSubject);
        $paymentInfo = $paymentDO->getPayment();
        $config = $this->getConfig($paymentInfo);

        if (method_exists($config, 'canShowIssuers') && !$config->canShowIssuers()) {
            return $this->createResult(true);
        }

        $chosenIssuer = $paymentInfo->getAdditionalInformation('issuer');

        if (!$chosenIssuer && $content = $this->request->getContent()) {
            $jsonDecode = $this->jsonSerializer->unserialize($content);
            if (!empty($jsonDecode['paymentMethod']['additional_data']['issuer'])) {
                $chosenIssuer = $jsonDecode['paymentMethod']['additional_data']['issuer'];
                $paymentInfo->setAdditionalInformation('issuer', $chosenIssuer);
            }
        }

        if ($chosenIssuer === 'fastcheckout') {
            return $this->createResult(true);
        }

        foreach ($config->getIssuers() as $issuer) {
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
     *
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

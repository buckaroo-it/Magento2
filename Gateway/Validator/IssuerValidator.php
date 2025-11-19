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
        $paymentInfo = $validationSubject['payment'];
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

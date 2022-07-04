<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Buckaroo\Magento2\Helper\Data;
use Magento\Framework\App\RequestInterface;
/**
 * Class IssuerValidator
 * @package Magento\Payment\Gateway\Validator
 * @api
 * @since 100.0.2
 */
class IssuerValidator extends AbstractValidator
{
    /**
     * @var ConfigInterface
     */
    private ConfigInterface $config;

    /**
     * @var Data
     */
    public ?Data $helper;

    /**
     * @var RequestInterface
     */
    protected ?RequestInterface $request;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param ConfigInterface $config
     * @param Data|null $helper
     * @param RequestInterface|null $request
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        ConfigInterface $config,
        Data $helper = null,
        RequestInterface $request = null
    ) {
        $this->config = $config;
        $this->helper = $helper;
        $this->request = $request;
        parent::__construct($resultFactory);
    }

    /**
     * @param array $validationSubject
     * @return bool|ResultInterface
     * @throws NotFoundException
     * @throws \Exception
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $paymentInfo = $validationSubject['payment'];

        $skipValidation = $paymentInfo->getAdditionalInformation('buckaroo_skip_validation');
        if ($skipValidation) {
            return $this->createResult(true);
        }

        $chosenIssuer = $paymentInfo->getAdditionalInformation('issuer');

        if ($chosenIssuer) {
            if ($content = $this->request->getContent()) {
                $jsonDecode = $this->helper->getJson()->unserialize($content);
                if (!empty($jsonDecode['paymentMethod']['additional_data']['issuer'])) {
                    $chosenIssuer = $jsonDecode['paymentMethod']['additional_data']['issuer'];
                    $paymentInfo->setAdditionalInformation('issuer', $chosenIssuer);
                }
            }
        }

        $isValid = false;
        foreach ($this->config->getIssuers() as $issuer) {
            if ($issuer['code'] == $chosenIssuer) {
                $isValid = true;
                break;
            }
        }

        $fails = [];
        if (!$isValid) {
            $fails[] = __('Please select a issuer from the list');
        }

        return $this->createResult($isValid, $fails);
    }
}

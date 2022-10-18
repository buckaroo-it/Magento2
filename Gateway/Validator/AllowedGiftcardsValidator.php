<?php
namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards as GiftcardsConfig;
use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Gateway\Validator\ResultInterface;

class AllowedGiftcardsValidator extends AbstractValidator
{
    /**
     * @var GiftcardsConfig
     */
    private GiftcardsConfig $giftcardsConfig;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param GiftcardsConfig $giftcardsConfig
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        GiftcardsConfig $giftcardsConfig
    ) {
        $this->giftcardsConfig = $giftcardsConfig;
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
        $isValid = true;

        $paymentInfo = $validationSubject['payment']->getPayment();

        $skipValidation = $paymentInfo->getAdditionalInformation('buckaroo_skip_validation');
        if ($skipValidation) {
            return $this->createResult($isValid);
        }

        /**
         * If there are no giftcards chosen, we can't be available
         */
        $fails = [];
        if (null === $this->giftcardsConfig->getAllowedGiftcards()) {
            $fails[] = __('There are no allowed giftcards.');
            $isValid = false;
        }

        return $this->createResult($isValid, $fails);
    }
}

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
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Service\Formatter\Address\StreetFormatter;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Quote\Model\Quote\Address;

class HouseNumberValidator extends AbstractValidator
{
    /**
     * @var StreetFormatter
     */
    private StreetFormatter $streetFormatter;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param StreetFormatter $streetFormatter
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        StreetFormatter $streetFormatter
    ) {
        $this->streetFormatter = $streetFormatter;
        parent::__construct($resultFactory);
    }

    /**
     * Validate country
     *
     * @param array $validationSubject
     * @return ResultInterface
     * @throws Exception
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $quote = SubjectReader::readQuote($validationSubject);

        try {
            $this->validateHouseNumber($quote->getBillingAddress());
            $this->validateHouseNumber($quote->getShippingAddress());
        } catch (Exception $exception) {
            $this->createResult(false, [$exception->getMessage()]);
        }

        return $this->createResult(true);
    }

    /**
     * @param Address $address
     * @return void
     * @throws Exception
     */
    private function validateHouseNumber(Address $address): void
    {
        $streetFormat = $this->streetFormatter->format($address->getStreet());

        if ($address->getCountryId() !== "DE") {
            return;
        }

        if (!isset($streetFormat['house_number'])
            || empty(trim($streetFormat['house_number']))
            || !is_string($streetFormat['house_number'])
        ) {
            throw new \Buckaroo\Magento2\Exception(
                new \Magento\Framework\Phrase(
                    'A valid address is required, cannot find street number'
                )
            );
        }
    }
}

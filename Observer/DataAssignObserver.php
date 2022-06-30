<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
namespace Buckaroo\Magento2\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class DataAssignObserver extends AbstractDataAssignObserver
{
    private const SKIP_VALIDATION = 'buckaroo_skip_validation';
    private const PAYMENT_FROM = 'buckaroo_payment_from';
    private const PAYMENT_ISSUER = 'issuer';
    private const TERMS_CONDITION = 'termsCondition';
    private const CUSTOMER_GENDER = 'customer_gender';
    private const CUSTOMER_BILLINGNAME = 'customer_billingName';
    private const CUSTOMER_IDENTIFICATIONNUMBER = 'customer_identificationNumber';
    private const CUSTOMER_DOB = 'customer_DoB';
    private const CUSTOMER_TELEPHONE = 'customer_telephone';
    private const CUSTOMER_BILLINGFIRSTNAME = 'customer_billingFirstName';
    private const CUSTOMER_BILLINGLASTNAME = 'customer_billingLastName';
    private const CUSTOMER_EMAIL = 'customer_email';
    private const CUSTOMER_IBAN = 'customer_iban';
    private const SELECTEDBUSINESS = 'selectedBusiness';
    private const COCNUMBER = 'COCNumber';
    private const COMPANYNAME = 'CompanyName';
    private const APPLEPAYTRANSACTION = 'applepayTransaction';
    private const BILLINGCONTACT = 'billingContact';
    private const CUSTOMER_CHAMBEROFCOMMERCE = 'customer_chamberOfCommerce';
    private const CUSTOMER_VATNUMBER = 'customer_VATNumber';
    private const CUSTOMER_ORDERAS = 'customer_orderAs';
    private const CUSTOMER_COCNUMBER = 'customer_cocnumber';
    private const CUSTOMER_COMPANYNAME = 'customer_companyName';
    private const CARD_TYPE = 'card_type';
    private const CUSTOMER_ENCRYPTEDDATA = 'customer_encrypteddata';
    private const CUSTOMER_CREDITCARDCOMPANY = 'customer_creditcardcompany';
    private const GIFTCARD_METHOD = 'giftcard_method';
    private const CUSTOMER_BIC = 'customer_bic';
    private const CLIENT_SIDE_MODE = 'client_side_mode';
    private const CUSTOMER_ACCOUNT_NAME = 'customer_account_name';

    /**
     * @var array
     */
    private array $additionalInformationList = [
        self::SKIP_VALIDATION,
        self::PAYMENT_ISSUER,
        self::PAYMENT_FROM,
        self::TERMS_CONDITION,
        self::CUSTOMER_GENDER,
        self::CUSTOMER_BILLINGNAME,
        self::CUSTOMER_IDENTIFICATIONNUMBER,
        self::CUSTOMER_DOB,
        self::CUSTOMER_TELEPHONE,
        self::CUSTOMER_BILLINGFIRSTNAME ,
        self::CUSTOMER_BILLINGLASTNAME,
        self::CUSTOMER_EMAIL,
        self::CUSTOMER_IBAN,
        self::SELECTEDBUSINESS,
        self::COCNUMBER,
        self::COMPANYNAME,
        self::APPLEPAYTRANSACTION,
        self::BILLINGCONTACT,
        self::CUSTOMER_CHAMBEROFCOMMERCE,
        self::CUSTOMER_VATNUMBER,
        self::CUSTOMER_ORDERAS,
        self::CUSTOMER_COCNUMBER,
        self::CUSTOMER_COMPANYNAME,
        self::CARD_TYPE,
        self::CUSTOMER_ENCRYPTEDDATA,
        self::CUSTOMER_CREDITCARDCOMPANY,
        self::GIFTCARD_METHOD,
        self::CUSTOMER_BIC,
        self::CLIENT_SIDE_MODE,
        self::CUSTOMER_ACCOUNT_NAME
    ];

    /**
     * @var array|string[] Some fields shouldn't be set if termsCondition is not true
     */
    private array $termsConditionDepends = [
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay::CODE,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay2::CODE,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20::CODE,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Klarna::CODE,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp::CODE,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnain::CODE,
    ];

    /**
     * Set Additional Information for all payment methods
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        if (in_array($paymentInfo->getMethodInstance()->getCode(), $this->termsConditionDepends)
            && !isset($additionalData[self::TERMS_CONDITION])) {
            return;
        }

        foreach ($this->additionalInformationList as $additionalInformationKey) {
            if (isset($additionalData[$additionalInformationKey])) {
                $paymentInfo->setAdditionalInformation(
                    $additionalInformationKey,
                    $additionalData[$additionalInformationKey]
                );
            }
        }
    }
}

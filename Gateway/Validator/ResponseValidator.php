<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Model\ConfigProvider\PublicKey;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use StdClass;

class ResponseValidator extends AbstractValidator
{
    /**
     * @var StdClass
     */
    protected StdClass $transaction;

    /**
     * @var string
     */
    protected string $responseXml;

    /**
     * @var PublicKey
     */
    protected PublicKey $publicKeyConfigProvider;

    /**
     * @param PublicKey $publicKeyConfigProvider
     * @param ResultInterfaceFactory $resultFactory
     */
    public function __construct(
        PublicKey              $publicKeyConfigProvider,
        ResultInterfaceFactory $resultFactory
    ) {
        parent::__construct($resultFactory);
        $this->publicKeyConfigProvider = $publicKeyConfigProvider;
    }

    /**
     * Performs validation of result code
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject)
    {
        if (!isset($validationSubject['response']) || !is_array($validationSubject['response'])) {
            throw new \InvalidArgumentException('Response does not exist');
        }

        $response = $validationSubject['response'];

        if (empty($response[0]) || !$response[0] instanceof StdClass) {
            return $this->createResult(
                false,
                [__('Data must be an instance of "\StdClass"')]
            );
        }

        if (empty($response['response_xml'])) {
            return $this->createResult(
                false,
                [__('Data must contain the Buckaroo response XML.')]
            );
        }

        $this->transaction = $response[0];
        $this->responseXml = $response['response_xml'];

        if ($this->validateSignature() === true && $this->validateDigest() === true) {
            return $this->createResult(
                true,
                [__('Transaction Success')]
            );
        }

        return $this->createResult(
            false,
            [__('Gateway rejected the transaction.')]
        );
    }

    /**
     * @return boolean
     */
    protected function validateSignature()
    {
        $verified = false;

        //save response XML to string
        $responseString = $this->responseXml;
        $responseDomDoc = new \DOMDocument();
        $responseDomDoc->loadXML($responseString);

        //retrieve the signature value
        $sigatureRegex  = "#<SignatureValue>(.*)</SignatureValue>#ims";
        $signatureArray = [];
        preg_match_all($sigatureRegex, $responseString, $signatureArray);

        // decode the signature
        $signature  = $signatureArray[1][0];
        //phpcs:ignore:Magento2.Functions.DiscouragedFunction
        $sigDecoded = base64_decode($signature);

        $xPath = new \DOMXPath($responseDomDoc);

        // register namespaces to use in xpath query's
        $xPath->registerNamespace(
            'wsse',
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd'
        );
        $xPath->registerNamespace('sig', 'http://www.w3.org/2000/09/xmldsig#');
        $xPath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

        // Get the SignedInfo nodeset
        $SignedInfoQuery        = '//wsse:Security/sig:Signature/sig:SignedInfo';
        $SignedInfoQueryNodeSet = $xPath->query($SignedInfoQuery);
        $SignedInfoNodeSet      = $SignedInfoQueryNodeSet->item(0);

        // Canonicalize nodeset
        $signedInfo = $SignedInfoNodeSet->C14N(true, false);

        $keyIdentifier = '//wsse:Security/sig:Signature/sig:KeyInfo/wsse:SecurityTokenReference/wsse:KeyIdentifier';
        $keyIdentifierList = $xPath->query($keyIdentifier);
        $keyIdentifierValue = '';
        if ($keyIdentifierList && $keyIdentifierList->item(0) && $keyIdentifierList->item(0)->nodeValue) {
            $keyIdentifierValue = $keyIdentifierList->item(0)->nodeValue;
        }

        // get the public key
        $pubKey = openssl_get_publickey(
            openssl_x509_read(
                $this->publicKeyConfigProvider->getPublicKey($keyIdentifierValue)
            )
        );

        // verify the signature
        $sigVerify = openssl_verify($signedInfo, $sigDecoded, $pubKey);

        if ($sigVerify === 1) {
            $verified = true;
        }

        return $verified;
    }

    /**
     * @return boolean
     */
    protected function validateDigest()
    {
        $verified = false;

        //save response XML to string
        $responseString = $this->responseXml;
        $responseDomDoc = new \DOMDocument();
        $responseDomDoc->loadXML($responseString);

        //retrieve the signature value
        $digestRegex = "#<DigestValue>(.*?)</DigestValue>#ims";
        $digestArray = [];
        preg_match_all($digestRegex, $responseString, $digestArray);

        $digestValues = [];
        foreach ($digestArray[1] as $digest) {
            $digestValues[] = $digest;
        }

        $xPath = new \DOMXPath($responseDomDoc);

        //register namespaces to use in xpath query's
        $xPath->registerNamespace(
            'wsse',
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd'
        );
        $xPath->registerNamespace('sig', 'http://www.w3.org/2000/09/xmldsig#');
        $xPath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

        $controlHashReference = $xPath->query('//*[@Id="_control"]')->item(0);
        $controlHashCanonical = $controlHashReference->C14N(true, false);
        $controlHash          = base64_encode(pack('H*', sha1($controlHashCanonical)));

        $bodyHashReference = $xPath->query('//*[@Id="_body"]')->item(0);
        $bodyHashCanonical = $bodyHashReference->C14N(true, false);
        $bodyHash          = base64_encode(pack('H*', sha1($bodyHashCanonical)));

        if (in_array($controlHash, $digestValues) === true && in_array($bodyHash, $digestValues) === true) {
            $verified = true;
        }

        return $verified;
    }
}

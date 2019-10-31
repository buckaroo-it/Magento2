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

namespace TIG\Buckaroo\Model\Validator;

class TransactionResponse implements \TIG\Buckaroo\Model\ValidatorInterface
{
    /**
     * @var \StdClass
     */
    protected $transaction;

    /**
     * @var string
     */
    protected $responseXml;

    /**
     * @var \TIG\Buckaroo\Model\ConfigProvider\PublicKey
     */
    protected $publicKeyConfigProvider;

    /**
     * TransactionResponse constructor.
     *
     * @param \TIG\Buckaroo\Model\ConfigProvider\PublicKey $publicKeyConfigProvider
     */
    public function __construct(\TIG\Buckaroo\Model\ConfigProvider\PublicKey $publicKeyConfigProvider)
    {
        $this->publicKeyConfigProvider = $publicKeyConfigProvider;
    }

    /**
     * @param array|object $data
     *
     * @return boolean
     *
     * @throws \InvalidArgumentException
     */
    public function validate($data)
    {
        if (empty($data[0]) || !$data[0] instanceof \StdClass) {
            throw new \InvalidArgumentException(
                'Data must be an instance of "\StdClass"'
            );
        }

        if (empty($data['response_xml'])) {
            throw new \InvalidArgumentException(
                'Data must contain the Buckaroo response XML.'
            );
        }

        $this->transaction = $data[0];
        $this->responseXml = $data['response_xml'];

        if ($this->validateSignature() === true && $this->validateDigest() === true) {
            return true;
        }

        return false;
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

        // get the public key
        $pubKey = openssl_get_publickey(
            openssl_x509_read(
                $this->publicKeyConfigProvider->getPublicKey()
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

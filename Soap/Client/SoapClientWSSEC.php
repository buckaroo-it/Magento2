<?php
/**
# The MIT License

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
 */

// @codingStandardsIgnoreFile
namespace TIG\Buckaroo\Soap\Client;

class SoapClientWSSEC extends \SoapClient
{
    private $pemdata = null;

    public function __soapCall ($function_name, $arguments, $options = null, $input_headers = null, &$output_headers = null)
    {
        $result = [parent::__soapCall($function_name,$arguments,$options,$input_headers,$output_headers)];
        $result['request_xml'] = $this->__getLastRequest();
        $result['response_xml'] = $this->__getLastResponse();
        return $result;
    }

    public function __call($name, $args)
    {
        // buckaroo requires all numbers to have period notation, otherwise
        // an internal error will occur on the server.
        $locale = setlocale(LC_NUMERIC, '0');
        setlocale(LC_NUMERIC, array('en_US', 'en_US.UTF-8'));
        $ret = parent::__call($name, $args);
        setlocale(LC_NUMERIC, $locale);
        return $ret;
    }

    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {

        $domDOC = new \DOMDocument();
        $domDOC->loadXML($request);

        if (!$this->pemdata) {
            throw new \InvalidArgumentException('PEM file not yet loaded. Use loadPem()');
        }

        //Sign the document
        $this->SignDomDocument($domDOC);

        return parent::__doRequest($domDOC->saveXML($domDOC->documentElement), $location, $action, $version, $one_way);
    }

    public function loadPem($pemFile)
    {
        $this->pemdata = $pemFile;
    }

    private function SignDomDocument(\DomDocument $domDocument)
    {

        //create xPath
        $xPath = new \DOMXPath($domDocument);

        //register namespaces to use in xpath query's
        $xPath->registerNamespace(
            'wsse',
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd'
        );
        $xPath->registerNamespace('sig', 'http://www.w3.org/2000/09/xmldsig#');
        $xPath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

        //Set id on soap body to easily extract the body later.
        $bodyNodeList = $xPath->query('/soap:Envelope/soap:Body');
        $bodyNode = $bodyNodeList->item(0);
        /** @noinspection PhpUndefinedMethodInspection */
        $bodyNode->setAttribute('Id', '_body');

        //Get the digest values
        $controlHash = $this->CalculateDigestValue($this->GetCanonical($this->GetReference('_control', $xPath)));
        $bodyHash = $this->CalculateDigestValue($this->GetCanonical($this->GetReference('_body', $xPath)));

        //Set the digest value for the control reference
        $Control = '#_control';
        $controlHashQuery = $query = '//*[@URI="'.$Control.'"]/sig:DigestValue';
        $controlHashQueryNodeset = $xPath->query($controlHashQuery);
        $controlHashNode = $controlHashQueryNodeset->item(0);
        $controlHashNode->nodeValue = $controlHash;

        //Set the digest value for the body reference
        $Body = '#_body';
        $bodyHashQuery = $query = '//*[@URI="'.$Body.'"]/sig:DigestValue';
        $bodyHashQueryNodeset = $xPath->query($bodyHashQuery);
        $bodyHashNode = $bodyHashQueryNodeset->item(0);
        $bodyHashNode->nodeValue = $bodyHash;

        //Get the SignedInfo nodeset
        $SignedInfoQuery = '//wsse:Security/sig:Signature/sig:SignedInfo';
        $SignedInfoQueryNodeSet = $xPath->query($SignedInfoQuery);
        $SignedInfoNodeSet = $SignedInfoQueryNodeSet->item(0);

        //Canonicalize nodeset
        $signedINFO = $this->GetCanonical($SignedInfoNodeSet);

        //Sign signedinfo with privatekey
        $signature2 = '';
        openssl_sign($signedINFO, $signature2, $this->pemdata);

        //Add signature value to xml document
        $sigValQuery = '//wsse:Security/sig:Signature/sig:SignatureValue';
        $sigValQueryNodeset = $xPath->query($sigValQuery);

        $sigValNodeSet = $sigValQueryNodeset->item(0);
        $sigValNodeSet->nodeValue = base64_encode($signature2);

        //Get signature node
        $sigQuery = '//wsse:Security/sig:Signature';
        $sigQueryNodeset = $xPath->query($sigQuery);
        $sigNodeSet = $sigQueryNodeset->item(0);

        //Create keyinfo element and Add public key to KeyIdentifier element
        $KeyTypeNode = $domDocument->createElementNS("http://www.w3.org/2000/09/xmldsig#","KeyInfo");
        $SecurityTokenReference = $domDocument->createElementNS(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd','SecurityTokenReference'
        );
        $KeyIdentifier = $domDocument->createElement("KeyIdentifier");

        $thumbprint = $this->sha1_thumbprint($this->pemdata);
        $KeyIdentifier->nodeValue = $thumbprint;
        $KeyIdentifier->setAttribute(
            'ValueType',
            'http://docs.oasis-open.org/wss/oasis-wss-soap-message-security-1.1#ThumbPrintSHA1'
        );
        $SecurityTokenReference->appendChild($KeyIdentifier);
        $KeyTypeNode->appendChild($SecurityTokenReference);
        $sigNodeSet->appendChild($KeyTypeNode);
    }

    //Get nodeset based on xpath and ID
    private function GetReference($ID, \DOMXPath $xPath)
    {
        $query = '//*[@Id="'.$ID.'"]';
        $nodeset = $xPath->query($query);
        $Object = $nodeset->item(0);

        return $Object;
    }

    //Canonicalize nodeset
    private function GetCanonical(\DOMNode $Object)
    {
        $output = $Object->C14N(true, false);
        return $output;
    }

    //Calculate digest value (sha1 hash)
    private function CalculateDigestValue($input) {
        $digValueControl = base64_encode(pack("H*", sha1($input)));

        return $digValueControl;
    }

    private function sha1_thumbprint($fullcert) {
        // First, strip out only the right section
        openssl_x509_export($fullcert, $pem);

        // Then calculate sha1 of base64 decoded cert
        $pem = preg_replace('/\-+BEGIN CERTIFICATE\-+/', '', $pem);
        $pem = preg_replace('/\-+END CERTIFICATE\-+/', '', $pem);
        $pem = trim($pem);
        $pem = str_replace(array("\n\r","\n","\r"), '', $pem);
        $bin = base64_decode($pem);
        return sha1($bin);
    }
}

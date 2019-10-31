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
namespace TIG\Buckaroo\Model\ConfigProvider;

class PublicKey implements \Magento\Checkout\Model\ConfigProviderInterface
{
    /**
     * @var string
     */
    protected $publicKey = <<<KEY
-----BEGIN CERTIFICATE-----
MIICeDCCAeGgAwIBAgIBADANBgkqhkiG9w0BAQUFADCBwjEUMBIGA1UEBhMLTmV0
aGVybGFuZHMxEDAOBgNVBAgTB1V0cmVjaHQxEDAOBgNVBAcTB1V0cmVjaHQxFjAU
BgNVBAoTDUJ1Y2thcm9vIEIuVi4xGjAYBgNVBAsTEVRlY2huaWNhbCBTdXBwb3J0
MS4wLAYDVQQDEyVCdWNrYXJvbyBPbmxpbmUgUGF5bWVudCBTZXJ2aWNlcyBCLlYu
MSIwIAYJKoZIhvcNAQkBFhNzdXBwb3J0QGJ1Y2thcm9vLm5sMB4XDTEyMDIwNzEx
MTQ1NVoXDTIyMDIwNzExMTQ1NVowQTEPMA0GA1UEBxMGQkVJTEVOMRYwFAYDVQQK
Ew1CdWNrYXJvbyBCLlYuMRYwFAYDVQQDEw1CdWNrYXJvbyBCLlYuMIGfMA0GCSqG
SIb3DQEBAQUAA4GNADCBiQKBgQD4u6psr+HtBpZIB9cGkg/Aov+yJNm0GPVV+f3w
yoXPNDhbHxCnKXslKxO6WYxEzUQJuuphtUdxb5tR1wbuv8NSnBNUv2qB1SLRIEJH
CLCtUyTC79HvpWHIDaibuRCqCjNlOgphgc0Am/PruwGqvG3qtVcWjG1io7iXzlJJ
XF+UbQIDAQABMA0GCSqGSIb3DQEBBQUAA4GBANj91vccLfvwIMU5L++ONcx6Ymck
wU0UnlIDKapCvNIcpfCH1wE9QiSvgfe22G9TPtYCGl3EkD+1QetQ/luFuSchD+/Q
RJgSa1IpXGvqmV3g8H2xSj0N+a7z1fK2N2CqREHQZ7VbYZdWSNXYyn5yggNefuCC
utpwIl+bFlxvC64V
-----END CERTIFICATE-----
KEY;

    /**
     * @param null $publicKey
     */
    public function __construct($publicKey = null)
    {
        if ($publicKey) {
            $this->publicKey = $publicKey;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig($store = null)
    {
        $config = [
            'public_key' => $this->getPublicKey($store),
        ];
        return $config;
    }

    /**
     * Return public key
     *
     * @return null|string
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }
}

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

namespace Buckaroo\Magento2\Model\ConfigProvider;

use Magento\Checkout\Model\ConfigProviderInterface;

class PublicKey implements ConfigProviderInterface
{
    /**
     * @var string
     */
    protected string $publicKey = <<<KEY
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

    protected string $publicKey85694BFB9716140DFF33604775A9668DAA67ECD4 = <<<KEY
-----BEGIN CERTIFICATE-----
MIICeTCCAeKgAwIBAgIBADANBgkqhkiG9w0BAQUFADCBwjEUMBIGA1UEBhMLTmV0
aGVybGFuZHMxEDAOBgNVBAgTB1V0cmVjaHQxEDAOBgNVBAcTB1V0cmVjaHQxFjAU
BgNVBAoTDUJ1Y2thcm9vIEIuVi4xGjAYBgNVBAsTEVRlY2huaWNhbCBTdXBwb3J0
MS4wLAYDVQQDEyVCdWNrYXJvbyBPbmxpbmUgUGF5bWVudCBTZXJ2aWNlcyBCLlYu
MSIwIAYJKoZIhvcNAQkBFhNzdXBwb3J0QGJ1Y2thcm9vLm5sMB4XDTIxMDgzMDA3
MDQxMVoXDTMxMDgzMDA3MDQxMVowQjEQMA4GA1UEBxMHVVRSRUNIVDEWMBQGA1UE
ChMNQnVja2Fyb28gQi5WLjEWMBQGA1UEAxMNQnVja2Fyb28gQi5WLjCBnzANBgkq
hkiG9w0BAQEFAAOBjQAwgYkCgYEAyL7qNsQiaMA0rzWJZrttntoonFqJmpO6nGtc
CIRZhDt8SEZaE4/SL0nvXHGDLSub8kqpn1AftFfTFeYAekENsyc7yyY37e52W271
PLVhURSa/amASYyJ71arzmleAGK9TNykGk9dawffQFA8mJoa3eTRHtItqWMr/y5q
nLckFEkCAwEAATANBgkqhkiG9w0BAQUFAAOBgQBBwefjXSPdMKEO7D0j7W1YN1PF
beWhkJoSST8HsnX9E5uUNCL0roNg/XDA9EpgsbPeXS2e4160pq6BhDVllu/FkRHl
/w4nWCvWZdLJVU/jtmfo/Mc/01GsusX/jjp3Qfy0uLgHTXIOOLhVhAHaUVS+fYfB
rGiZ8AqhytAWg+r5Yw==
-----END CERTIFICATE-----
KEY;

    /**
     * @param string|null $publicKey
     */
    public function __construct(string $publicKey = null)
    {
        if ($publicKey) {
            $this->publicKey = $publicKey;
        }
    }

    /**
     * @inheritdoc
     */
    public function getConfig($store = null): array
    {
        return [
            'public_key' => $this->getPublicKey(),
        ];
    }

    /**
     * Return public key
     *
     * @param string $keyIdentifier
     * @return null|string
     */
    public function getPublicKey(string $keyIdentifier = ''): ?string
    {
        if (isset($this->{'publicKey' . $keyIdentifier})) {
            return $this->{'publicKey' . $keyIdentifier};
        } else {
            return $this->publicKey;
        }
    }
}

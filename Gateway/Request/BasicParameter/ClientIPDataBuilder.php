<?php

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Resources\Constants\IPProtocolVersion;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ClientIPDataBuilder implements BuilderInterface
{
    /**
     * @var Account
     */
    private $configProviderAccount;

    /**
     * Constructor
     *
     * @param Account $configProviderAccount
     */
    public function __construct(
        Account $configProviderAccount
    ) {
        $this->configProviderAccount = $configProviderAccount;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();

        $ip = $this->getIp($order);

        return [
            'clientIP' => [
                'address' => $ip,
                'type'    => strpos($ip, ':') === false ? IPProtocolVersion::IPV4 : IPProtocolVersion::IPV6
            ]
        ];
    }

    public function getIp($order)
    {
        $ip = $order->getRemoteIp();
        $store = $order->getStore();

        $ipHeaders = $this->configProviderAccount->getIpHeader($store);

        if ($ipHeaders) {
            $ipHeaders = explode(',', strtoupper($ipHeaders));
            foreach ($ipHeaders as &$ipHeader) {
                $ipHeader = 'HTTP_' . str_replace('-', '_', (string)$ipHeader);
            }
            $ip = $order->getPayment()->getMethodInstance()->getRemoteAddress(false, $ipHeaders);
        }

        //trustly anyway should be w/o private ip
        if ((
            isset($order->getPayment()->getMethodInstance()->buckarooPaymentMethodCode) &&
                $order->getPayment()->getMethodInstance()->buckarooPaymentMethodCode == 'trustly'
        ) &&
            $this->isIpPrivate($ip) &&
            $order->getXForwardedFor()
        ) {
            $ip = $order->getXForwardedFor();
        }

        if (!$ip) {
            $ip = $order->getPayment()->getMethodInstance()->getRemoteAddress();
        }

        return $ip;
    }

    private function isIpPrivate($ip)
    {
        if (!$ip) {
            return false;
        }

        $priAddrs = [
            '10.0.0.0|10.255.255.255', // single class A network
            '172.16.0.0|172.31.255.255', // 16 contiguous class B network
            '192.168.0.0|192.168.255.255', // 256 contiguous class C network
            '169.254.0.0|169.254.255.255', // Link-local address also referred to as Automatic Private IP Addressing
            '127.0.0.0|127.255.255.255' // localhost
        ];

        $longIp = ip2long($ip);
        if ($longIp != -1) {
            foreach ($priAddrs as $priAddr) {
                list($start, $end) = explode('|', $priAddr);

                if ($longIp >= ip2long($start) && $longIp <= ip2long($end)) {
                    return true;
                }
            }
        }

        return false;
    }
}

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

namespace Buckaroo\Magento2\Gateway\Http\TransactionBuilder;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\FormKey;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;

class DataRequest extends AbstractTransactionBuilder
{
    /**
     * @return array
     */
    public function getBody()
    {
        $ip = '127.0.0.' . rand(1, 100);

        $body = [
            'ClientIP' => (object)[
                '_' => $ip,
                'Type' => strpos($ip, ':') === false ? 'IPv4' : 'IPv6',
            ],
            'ReturnURL' => $this->getReturnUrl(),
            'Services' => (object)[
                'Service' => $this->getServices()
            ],
            'AdditionalParameters' => (object)[
                'AdditionalParameter' => $this->getAdditionalParameters()
            ],
        ];

        return $body;
    }


    /**
     * @return array
     */
    private function getAdditionalParameters()
    {
        $parameterLine = [];
        if (isset($this->getServices()['Action'])) {
            $parameterLine[] = $this->getParameterLine('service_action_from_magento', strtolower($this->getServices()['Action']));
        }

        $parameterLine[] = $this->getParameterLine('initiated_by_magento', 1);

        if($additionalParameters = $this->getAllAdditionalParameters()){
            foreach ($additionalParameters as $key => $value) {
                $parameterLine[] = $this->getParameterLine($key, $value);
            }
        }

        return $parameterLine;
    }

    /**
     * @param $name
     * @param $value
     *
     * @return array
     */
    private function getParameterLine($name, $value)
    {
        $line = [
            '_'    => $value,
            'Name' => $name,
        ];

        return $line;
    }
}

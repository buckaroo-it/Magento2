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

namespace Buckaroo\Magento2\Service\EmailProvider;

use Buckaroo\Magento2\Model\ConfigProvider\ExternalEmailProvider as EmailProviderConfig;
use Buckaroo\Magento2\Service\EmailProvider\Transport\TransportInterface;
use Buckaroo\Magento2\Service\EmailProvider\Transport\SmtpTransport;
use Buckaroo\Magento2\Service\EmailProvider\Transport\ApiTransport;

class TransportFactory
{
    /**
     * @var EmailProviderConfig
     */
    protected $config;

    /**
     * @var SmtpTransport
     */
    protected $smtpTransport;

    /**
     * @var ApiTransport
     */
    protected $apiTransport;

    /**
     * @param EmailProviderConfig $config
     * @param SmtpTransport       $smtpTransport
     * @param ApiTransport        $apiTransport
     */
    public function __construct(
        EmailProviderConfig $config,
        SmtpTransport $smtpTransport,
        ApiTransport $apiTransport
    ) {
        $this->config = $config;
        $this->smtpTransport = $smtpTransport;
        $this->apiTransport = $apiTransport;
    }

    /**
     * Get appropriate transport based on configuration
     *
     * @param int|null $storeId
     *
     * @return TransportInterface
     */
    public function create($storeId = null): TransportInterface
    {
        $method = $this->config->getMethod($storeId);

        if ($method === 'api') {
            return $this->apiTransport;
        }

        return $this->smtpTransport;
    }
}

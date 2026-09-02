<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
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

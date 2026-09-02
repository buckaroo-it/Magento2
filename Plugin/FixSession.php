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
declare(strict_types=1);

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\HTTP\Header;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\Stdlib\Cookie\PhpCookieManager;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;

class FixSession
{
    /**
     * @var Header
     */
    protected $header;

    /**
     * @var BuckarooLoggerInterface
     */
    protected $logger;

    /**
     * @var SessionManager
     */
    protected $sessionManager;

    /**
     * @param Header                  $header
     * @param BuckarooLoggerInterface $logger
     * @param SessionManager          $sessionManager
     */
    public function __construct(
        Header $header,
        BuckarooLoggerInterface $logger,
        SessionManager $sessionManager
    ) {
        $this->header = $header;
        $this->logger = $logger;
        $this->sessionManager = $sessionManager;
    }

    /**
     * Fix the issue when customers get logged out or lose cart content on Magento storefront
     *
     * @param PhpCookieManager          $subject
     * @param string                    $name
     * @param string                    $value
     * @param PublicCookieMetadata|null $metadata
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSetPublicCookie(
        PhpCookieManager $subject,
        string $name,
        string $value,
        ?PublicCookieMetadata $metadata = null
    ) {
        if (($metadata && method_exists($metadata, 'getSameSite') && ($name == $this->sessionManager->getName()))
            && $metadata->getSameSite() != 'None') {
            $metadata->setSecure(true);
            $metadata->setSameSite('None');
        }
        return [$name, $value, $metadata];
    }
}

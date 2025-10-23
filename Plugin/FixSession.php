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

namespace Buckaroo\Magento2\Plugin;

use Magento\Framework\HTTP\Header;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\Stdlib\Cookie\PhpCookieManager;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;
use Magento\Framework\Session\SessionManager;

class FixSession
{
    /**
     * @var Header
     */
    protected $header;

    /**
     * @var Log
     */
    protected $logger;

    protected $sessionManager;

    public function __construct(
        Header $header,
        Log $logger,
        SessionManager $sessionManager
    ) {
        $this->header = $header;
        $this->logger = $logger;
        $this->sessionManager = $sessionManager;
    }

    public function beforeSetPublicCookie(
        PhpCookieManager $subject,
        $name,
        $value,
        ?PublicCookieMetadata $metadata = null
    ) {
        if ($metadata && method_exists($metadata, 'getSameSite') && ($name == $this->sessionManager->getName())) {
            if ($metadata->getSameSite() != 'None') {
                $metadata->setSecure(true);
                $metadata->setSameSite('None');
            }
        }
        return [$name, $value, $metadata];
    }
}

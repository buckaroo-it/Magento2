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
namespace Buckaroo\Magento2\Model\Plugin;

use Magento\Framework\HTTP\Header;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\Stdlib\Cookie\PhpCookieManager;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;

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

    public function __construct(
        Header $header,
        Log $logger
    ) {
        $this->header = $header;
        $this->logger             = $logger;
    }

    public function beforeSetPublicCookie(
        PhpCookieManager $subject,
        $name,
        $value,
        PublicCookieMetadata $metadata = null
    ) {
        if ($metadata && method_exists($metadata, 'getSameSite') && ($name == 'PHPSESSID')) {
            //$this->logger->addDebug(__METHOD__ . '|1|' . var_export([$name, $value, $metadata->getSameSite()], true));
            if ($metadata->getSameSite() != 'None') {
                $metadata->setSameSite('None');
                $metadata->setSecure(true);
            }
        }
        return [$name, $value, $metadata];
    }
}

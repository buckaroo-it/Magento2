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

namespace Buckaroo\Magento2\Model\Config\Backend;

use Magento\Framework\App\Config\Value;

class CertificateLabel extends Value
{
    /**
     * Prevent saving the value by returning from the function immediately
     *
     * We don't need to safe certificate_label since it's handled in the certificate backend model.
     * By returning $this immediately we exit the function without saving or breaking anything.
     *
     * @return $this
     */
    public function save(): CertificateLabel
    {
        return $this;
    }
}

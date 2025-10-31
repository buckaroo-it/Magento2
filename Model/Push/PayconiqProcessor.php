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

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Model\BuckarooStatusCode;

class PayconiqProcessor extends DefaultProcessor
{
    /**
     * Skip the push if the conditions are met.
     *
     * @throws \Exception
     * @return bool
     */
    protected function skipPush(): bool
    {
        if ($this->pushRequest->getStatusCode() == BuckarooStatusCode::PENDING_PROCESSING) {
            return true;
        }

        return parent::skipPush();
    }
}

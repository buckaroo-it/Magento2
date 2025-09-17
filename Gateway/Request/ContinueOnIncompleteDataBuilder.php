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

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Builds the data for the ContinueOnIncomplete parameter.
 * Now always sets it for standard iDEAL, as issuer selection is handled by Buckaroo.
 */
class ContinueOnIncompleteDataBuilder implements BuilderInterface
{
    /**
     * @inheritdoc
     * Always adds 'continueOnIncomplete' => '1' (or Buckaroo equivalent)
     * as issuer selection is no longer handled within Magento checkout.
     */
    public function build(array $buildSubject): array
    {
        return ['continueOnIncomplete' => '1'];
    }
}

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

namespace Buckaroo\Magento2\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use \Magento\Framework\Exception\ValidatorException;

class DebugEmails extends Value
{
    public function beforeSave()
    {
        if (trim($this->getValue()) != '') {
            $emails = explode(",", $this->getValue());
            $this->validateListOfEmails($emails);
        }
        parent::beforeSave();
    }

    /**
     * Check if all emails are valid
     *
     * @param array $emails
     *
     * @return void
     * @throws ValidatorException
     */
    private function validateListOfEmails(array $emails)
    {

        foreach ($emails as $email) {
            if (!filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                throw new ValidatorException(
                    __("Invalid value for debug email: Invalid email `{$email}`")
                );
            }
        }
    }
}

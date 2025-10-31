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

namespace Buckaroo\Magento2\Logging;

use Buckaroo\Magento2\Model\ConfigProvider\DebugConfiguration;

class Mail
{
    /**
     * @var DebugConfiguration
     */
    private $debugConfiguration;

    /**
     * @var array
     */
    private $message = [];

    /**
     * @var string
     */
    protected $mailSubject = 'Buckaroo_Magento2 log mail';

    /**
     * @var string
     */
    protected $mailFrom = 'nobody@buckaroo.nl';

    /**
     * Mail constructor.
     *
     * @param DebugConfiguration $debugConfiguration
     */
    public function __construct(DebugConfiguration $debugConfiguration)
    {
        $this->debugConfiguration = $debugConfiguration;
    }

    /**
     * Reset the message
     *
     * @return $this
     */
    public function resetMessage()
    {
        $this->message = [];

        return $this;
    }

    /**
     * Add $message to the message array, and cast to string if an array or object
     *
     * @param  mixed $message
     * @return $this
     */
    public function addToMessage($message)
    {
        if (is_array($message) || is_object($message)) {
            //@codingStandardsIgnoreLine
            $message = print_r($message, true);
        }

        $this->message[] = $message;

        return $this;
    }

    /**
     * Get message
     *
     * @return array
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Return the message array as imploded string
     *
     * @return string|null
     */
    public function getMessageAsString()
    {
        if (count($this->getMessage()) == 0) {
            return null;
        }

        return implode(PHP_EOL, $this->getMessage());
    }

    /**
     * Get mail subject
     *
     * @return string
     */
    public function getMailSubject()
    {
        return $this->mailSubject;
    }

    /**
     * Set mail subject
     *
     * @param  string $mailSubject
     * @return $this
     */
    public function setMailSubject($mailSubject)
    {
        $this->mailSubject = $mailSubject;

        return $this;
    }

    /**
     * Get mail from
     *
     * @return string
     */
    public function getMailFrom()
    {
        return $this->mailFrom;
    }

    /**
     * Set mail from
     *
     * @param  string $mailFrom
     * @return $this
     */
    public function setMailFrom($mailFrom)
    {
        $this->mailFrom = $mailFrom;

        return $this;
    }
}

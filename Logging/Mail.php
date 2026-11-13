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
     * @param mixed $message
     *
     * @return $this
     */
    public function addToMessage($message)
    {
        if (is_array($message) || is_object($message)) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
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
     * @param string $mailSubject
     *
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
     * @param string $mailFrom
     *
     * @return $this
     */
    public function setMailFrom($mailFrom)
    {
        $this->mailFrom = $mailFrom;

        return $this;
    }
}

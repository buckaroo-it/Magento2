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
namespace Buckaroo\Magento2\Logging;

use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use Buckaroo\Magento2\Model\ConfigProvider\DebugConfiguration;

class Log extends Logger
{
    /** @var DebugConfiguration */
    private $debugConfiguration;

    /** @var Mail */
    private $mail;

    /** @var array */
    protected $message = [];

    private static $processUid = 0;

    protected $_checkoutSession;

    protected $_session;

    protected $customerSession;

    /**
     * Log constructor.
     *
     * @param string             $name
     * @param DebugConfiguration $debugConfiguration
     * @param Mail               $mail
     * @param HandlerInterface[] $handlers
     * @param callable[]         $processors
     */
    public function __construct(
        $name,
        DebugConfiguration $debugConfiguration,
        Mail $mail,
        array $handlers = [],
        array $processors = [],
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Session\SessionManager $sessionManager,
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->debugConfiguration = $debugConfiguration;
        $this->mail = $mail;
        $this->_checkoutSession  = $checkoutSession;
        $this->_session = $sessionManager;
        $this->customerSession = $customerSession;

        parent::__construct($name, $handlers, $processors);
    }

    /**
     * Make sure the debug information is always send to the debug email
     */
    public function __destruct()
    {
        $this->mail->mailMessage();
    }

    /**
     * {@inheritdoc}
     */
    public function addRecord($level, $message, array $context = [])
    {
        if (!$this->debugConfiguration->canLog($level)) {
            return false;
        }

        if (empty(self::$processUid)) {
            self::$processUid = uniqid();
        }

        $message = self::$processUid . '|' . microtime(true). '|' . $this->_session->getSessionId() . '|' . $this->customerSession->getCustomer()->getId() . '|' . $this->_checkoutSession->getQuote()->getId() . '|' . $this->_checkoutSession->getQuote()->getReservedOrderId() . '|' . $message;

        // Prepare the message to be send to the debug email
        $this->mail->addToMessage($message);

        return parent::addRecord($level, $message, $context);
    }
}

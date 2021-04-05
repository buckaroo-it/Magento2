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

use Buckaroo\Magento2\Model\ConfigProvider\DebugConfiguration;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

class Log extends Logger
{

    public const BUCKAROO_LOG_TRACE_DEPTH = 90;

    /** @var DebugConfiguration */
    private $debugConfiguration;

    /** @var array */
    protected $message = [];

    private static $processUid = 0;

    protected $checkoutSession;

    protected $session;

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
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Session\SessionManager $sessionManager,
        \Magento\Customer\Model\Session $customerSession,
        array $handlers = [],
        array $processors = []
    ) {
        $this->debugConfiguration = $debugConfiguration;
        $this->checkoutSession   = $checkoutSession;
        $this->session           = $sessionManager;
        $this->customerSession    = $customerSession;

        parent::__construct($name, $handlers, $processors);
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

        $trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
        $logTrace = [];
        for ($cnt=1; $cnt<self::BUCKAROO_LOG_TRACE_DEPTH; $cnt++) {
            if (isset($trace[$cnt])) {
                try {
                $logTrace[] = str_replace(BP, '', $trace[$cnt]['file']) . ": " .$trace[$cnt]['line']. " ".
                    $trace[$cnt]['class'] . '->' .
                    $trace[$cnt]['function'] . '()';
                } catch(\Exception $e) {
                    $logTrace[] = json_encode($trace[$cnt]);
                }
            }
        }

        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        $message = json_encode([
            'uid'  => self::$processUid,
            'time' => microtime(true),
            'sid'  => $this->session->getSessionId(),
            'cid'  => $this->customerSession->getCustomer()->getId(),
            'qid'  => $this->checkoutSession->getQuote()->getId(),
            'id'   => $this->checkoutSession->getQuote()->getReservedOrderId(),
            'msg' => $message,
            'trace' => $logTrace
        ], $flags);

        return parent::addRecord($level, $message, $context);
    }
}

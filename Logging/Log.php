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
use Magento\Checkout\Model\Session;
use Magento\Framework\Session\SessionManager;
use Monolog\DateTimeImmutable;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

class Log extends Logger
{
    public const BUCKAROO_LOG_TRACE_DEPTH_DEFAULT = 10;

    /**
     * @var DebugConfiguration
     */
    private DebugConfiguration $debugConfiguration;

    /**
     * @var array
     */
    protected array $message = [];

    /**
     * @var int
     */
    private static $processUid = 0;

    /**
     * @var Session
     */
    protected Session $checkoutSession;

    /**
     * @var SessionManager
     */
    protected SessionManager $session;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected \Magento\Customer\Model\Session $customerSession;

    /**
     * Log constructor.
     *
     * @param DebugConfiguration $debugConfiguration
     * @param Session $checkoutSession
     * @param SessionManager $sessionManager
     * @param \Magento\Customer\Model\Session $customerSession
     * @param HandlerInterface[] $handlers
     * @param callable[] $processors
     * @param string $name
     */
    public function __construct(
        DebugConfiguration $debugConfiguration,
        Session $checkoutSession,
        SessionManager $sessionManager,
        \Magento\Customer\Model\Session $customerSession,
        array $handlers = [],
        array $processors = [],
        string $name = 'buckaroo'
    ) {
        $this->debugConfiguration = $debugConfiguration;
        $this->checkoutSession = $checkoutSession;
        $this->session = $sessionManager;
        $this->customerSession = $customerSession;

        parent::__construct($name, $handlers, $processors);
    }

    /**
     * @inheritdoc
     */
    public function addRecord(
        int $level,
        string $message,
        array $context = [],
        DateTimeImmutable $datetime = null
    ): bool {
        if (!$this->debugConfiguration->canLog($level)) {
            return false;
        }

        if (empty(self::$processUid)) {
            self::$processUid = uniqid();
        }

        $depth = $this->debugConfiguration->getDebugBacktraceDepth();
        if (empty($depth) || trim($depth) == '') {
            $depth = self::BUCKAROO_LOG_TRACE_DEPTH_DEFAULT;
        }

        $trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, $depth);
        $logTrace = [];

        for ($cnt = 1; $cnt < $depth; $cnt++) {
            if (isset($trace[$cnt])) {
                try {
                    /** @phpstan-ignore-next-line */
                    $logTrace[] = str_replace(BP, '', $trace[$cnt]['file']) . ": " . $trace[$cnt]['line'] . " " .
                        $trace[$cnt]['class'] . '->' .
                        $trace[$cnt]['function'] . '()';
                } catch (\Exception $e) {
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

    /**
     * Logs a debug message.
     *
     * @param string $message
     * @return bool
     */
    public function addDebug(string $message): bool
    {
        return $this->addRecord(Logger::DEBUG, $message);
    }

    /**
     * Logs an error message.
     *
     * @param string $message
     * @return bool
     */
    public function addError(string $message): bool
    {
        return $this->addRecord(Logger::ERROR, $message);
    }

    /**
     * @inheritdoc
     */
    public function debug($message, array $context = []): void
    {
        $this->addRecord(Logger::DEBUG, (string) $message, $context);
    }
}

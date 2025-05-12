<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world‑wide‑web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world‑wide‑web, please email
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

namespace Buckaroo\Magento2\Logging;

use Buckaroo\Magento2\Model\ConfigProvider\DebugConfiguration;
use Magento\Checkout\Model\Session;
use Magento\Framework\Session\SessionManager;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

class Log extends Logger implements BuckarooLoggerInterface
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
     * @var string
     */
    protected string $action = '';

    /**
     * @var int|string
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
     * @param DebugConfiguration                 $debugConfiguration
     * @param Session                            $checkoutSession
     * @param SessionManager                     $sessionManager
     * @param \Magento\Customer\Model\Session $customerSession
     * @param HandlerInterface[]                 $handlers
     * @param callable[]                         $processors
     * @param string                             $name
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
        $this->checkoutSession   = $checkoutSession;
        $this->session           = $sessionManager;
        $this->customerSession   = $customerSession;

        parent::__construct($name, $handlers, $processors);
    }

    /**
     * @inheritdoc
     *
     * The base signature in Monolog 2 is:
     *   addRecord(int $level, ...);
     *
     * In Monolog 3 it is:
     *   addRecord(Level|int $level, ...);
     *
     * We use the *wider* type `mixed`, so both are satisfied.
     */
    public function addRecord(
        mixed $level,                       // ← was Level|int
        string|\Stringable $message,
        array $context = [],
        \DateTimeInterface|null $datetime = null   // ← keeps it portable
    ): bool {
        if (! $this->debugConfiguration->canLog($level)) {
            return false;
        }

        if (empty(self::$processUid)) {
            self::$processUid = uniqid();
        }

        $depth = $this->debugConfiguration->getDebugBacktraceDepth();
        if (empty($depth) || trim((string) $depth) === '') {
            $depth = self::BUCKAROO_LOG_TRACE_DEPTH_DEFAULT;
        }

        $trace    = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, $depth);
        $logTrace = [];

        for ($cnt = 1; $cnt < $depth; $cnt++) {
            if (isset($trace[$cnt])) {
                try {
                    /** @phpstan-ignore-next-line */
                    $logTrace[] = str_replace(BP, '', $trace[$cnt]['file']) . ': ' . $trace[$cnt]['line'] . ' ' .
                        $trace[$cnt]['class'] . '->' . $trace[$cnt]['function'] . '()';
                } catch (\Exception $e) {
                    $logTrace[] = json_encode($trace[$cnt]);
                }
            }
        }

        $flags   = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $message = $this->action . (string) $message;

        $message = json_encode([
            'uid'   => self::$processUid,
            'time'  => microtime(true),
            'sid'   => $this->session->getSessionId(),
            'cid'   => $this->customerSession->getCustomer()->getId(),
            'qid'   => $this->checkoutSession->getQuote()->getId(),
            'id'    => $this->checkoutSession->getQuote()->getReservedOrderId(),
            'msg'   => $message,
            'trace' => $logTrace,
        ], $flags);

        // -----------------------------------------------------------------
        // If the store runs on Monolog 3 and the caller gave us a plain int,
        // convert it to the new enum so parent::addRecord() is happy.
        // -----------------------------------------------------------------
        if (\class_exists(\Monolog\Level::class) && \is_int($level)) {
            /** @var \Monolog\Level $level */
            $level = \Monolog\Level::from($level);
        }

        return parent::addRecord($level, $message, $context, $datetime);
    }

    /**
     * Logs a debug message.
     */
    public function addDebug(string $message): bool
    {
        return $this->addRecord(Logger::DEBUG, $message);
    }

    /**
     * Logs an error message.
     */
    public function addError(string $message): bool
    {
        return $this->addRecord(Logger::ERROR, $message);
    }

    /**
     * Logs a warning message.
     */
    public function addWarning(string $message): bool
    {
        return $this->addRecord(Logger::WARNING, $message);
    }

    /**
     * @inheritdoc
     */
    public function debug($message, array $context = []): void
    {
        $this->addRecord(Logger::DEBUG, (string) $message, $context);
    }

    /**
     * @inheritdoc
     */
    public function setAction(string $action): BuckarooLoggerInterface
    {
        $this->action = $action;
        return $this;
    }
}

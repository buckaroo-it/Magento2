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
declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Transaction;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Transaction\Response\TransactionResponse;

/**
 * Finds the transaction a request created when its answer from Buckaroo was lost.
 *
 * Buckaroo has no idempotency key and accepts the same invoice number twice, so the request must not
 * be sent again. Instead the transactions on the invoice (and, for refunds and captures, those related
 * to the original transaction) are looked up, and the one created by this request is returned in the
 * same shape as a normal answer. Only an unambiguous match is returned.
 */
class OutcomeResolver
{
    /** Buckaroo reports transaction times in Dutch local time */
    private const BUCKAROO_TIMEZONE = 'Europe/Amsterdam';

    /** Allowed clock difference between this server and Buckaroo, in seconds */
    private const CLOCK_TOLERANCE = 120;

    /** Buckaroo may store the transaction only after it answers: look again a few times */
    private const ATTEMPTS = 3;

    private const RETRY_DELAY_MICROSECONDS = 2000000;

    /**
     * The lookups run after the request already waited for its timeout: give up after this many seconds
     * so the shopper's request stays well under common PHP and web server limits.
     */
    private const TIME_BUDGET = 15;

    /** Never inspect more candidates than this */
    private const MAX_CANDIDATES = 10;

    /**
     * @var BuckarooAdapter
     */
    private BuckarooAdapter $adapter;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @var callable
     */
    private $sleep;

    /**
     * @var callable
     */
    private $clock;

    /**
     * Constructor
     *
     * @param BuckarooAdapter         $adapter
     * @param BuckarooLoggerInterface $logger
     * @param callable|null           $sleep   for tests
     * @param callable|null           $clock   for tests, returns unix time
     */
    public function __construct(
        BuckarooAdapter $adapter,
        BuckarooLoggerInterface $logger,
        ?callable $sleep = null,
        ?callable $clock = null
    ) {
        $this->adapter = $adapter;
        $this->logger = $logger;
        $this->sleep = $sleep ?? 'usleep';
        $this->clock = $clock ?? 'time';
    }

    /**
     * Resolve
     *
     * @param string    $action      SDK action of the lost request (pay, refund, capture, ...)
     * @param string    $method      payment method code used for the request
     * @param array     $data        request data (invoice, amountDebit/amountCredit, originalTransactionKey, orderStoreId)
     * @param int       $startedAt   unix time the request was sent
     * @param bool|null $originalTransactionWasTest
     *
     * @return TransactionResponse|null the transaction this request created, or null when it can't be told
     */
    public function resolve(
        string $action,
        string $method,
        array $data,
        int $startedAt,
        ?bool $originalTransactionWasTest = null
    ): ?TransactionResponse {
        $lookup = $this->buildLookup($action, $method, $data, $startedAt, $originalTransactionWasTest);

        if ($lookup['invoice'] === '' && $lookup['originalKey'] === '') {
            return null;
        }

        $deadline = ($this->clock)() + self::TIME_BUDGET;

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            if ($attempt > 1 && ($this->clock)() >= $deadline) {
                $this->log('out of time', $lookup['invoice'], $action);
                break;
            }

            $matches = $this->tryFindMatches($lookup + ['deadline' => $deadline]);
            if (!empty($matches)) {
                return $this->pickSingleMatch($matches, $lookup);
            }

            if ($attempt < self::ATTEMPTS) {
                ($this->sleep)(self::RETRY_DELAY_MICROSECONDS);
            }
        }

        $this->log('no transaction found', $lookup['invoice'], $action);
        return null;
    }

    /**
     * What to look for
     *
     * @param string    $action
     * @param string    $method
     * @param array     $data
     * @param int       $startedAt
     * @param bool|null $wasTest
     *
     * @return array
     */
    private function buildLookup(string $action, string $method, array $data, int $startedAt, ?bool $wasTest): array
    {
        $isCredit = stripos($action, 'refund') !== false;
        $amount = $isCredit ? ($data['amountCredit'] ?? null) : ($data['amountDebit'] ?? null);

        return [
            'action'      => $action,
            'method'      => $method,
            'invoice'     => (string)($data['invoice'] ?? ''),
            'originalKey' => (string)($data['originalTransactionKey'] ?? ''),
            'storeId'     => isset($data['orderStoreId']) ? (int)$data['orderStoreId'] : null,
            'isCredit'    => $isCredit,
            'amount'      => $amount !== null ? (float)$amount : null,
            'startedAt'   => $startedAt,
            'wasTest'     => $wasTest,
        ];
    }

    /**
     * One lookup; errors count as "nothing found yet"
     *
     * @param array $lookup
     *
     * @return TransactionResponse[]
     */
    private function tryFindMatches(array $lookup): array
    {
        try {
            return $this->findMatches(
                $lookup['method'],
                $lookup['storeId'],
                $lookup['invoice'],
                $lookup['originalKey'],
                $lookup['isCredit'],
                $lookup['amount'],
                $lookup['startedAt'],
                $lookup['wasTest'],
                $lookup['deadline'] ?? PHP_INT_MAX
            );
        } catch (\Throwable $e) {
            $this->log('lookup failed', $lookup['invoice'], $lookup['action'], ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * The match when it is the only one; never guess between several
     *
     * @param TransactionResponse[] $matches
     * @param array                 $lookup
     *
     * @return TransactionResponse|null
     */
    private function pickSingleMatch(array $matches, array $lookup): ?TransactionResponse
    {
        if (count($matches) > 1) {
            $this->log('more than one candidate, not choosing', $lookup['invoice'], $lookup['action'], [
                'keys' => array_keys($matches),
            ]);
            return null;
        }

        $response = reset($matches);
        $this->log('found the transaction', $lookup['invoice'], $lookup['action'], [
            'key'    => $response->getTransactionKey(),
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }

    /**
     * Find matches
     *
     * @param string     $method
     * @param int|null   $storeId
     * @param string     $invoice
     * @param string     $originalKey
     * @param bool       $isCredit
     * @param float|null $amount
     * @param int        $startedAt
     * @param bool|null  $wasTest
     * @param int        $deadline
     *
     * @throws \Throwable
     *
     * @return TransactionResponse[] keyed by transaction key
     */
    private function findMatches(
        string $method,
        ?int $storeId,
        string $invoice,
        string $originalKey,
        bool $isCredit,
        ?float $amount,
        int $startedAt,
        ?bool $wasTest,
        int $deadline = PHP_INT_MAX
    ): array {
        $keys = [];

        if ($invoice !== '') {
            foreach ($this->adapter->getTransactionsByInvoice($method, $storeId, $invoice, $wasTest) as $transaction) {
                $keys[$transaction['key']] = true;
            }
        }

        if ($originalKey !== '') {
            $original = $this->adapter->getTransactionStatus($method, $storeId, $originalKey, $wasTest);
            foreach ((array)$original->get('RelatedTransactions') as $related) {
                if (!empty($related['RelatedTransactionKey'])) {
                    $keys[(string)$related['RelatedTransactionKey']] = true;
                }
            }
            unset($keys[$originalKey]);
        }

        $matches = [];
        foreach (array_slice(array_keys($keys), 0, self::MAX_CANDIDATES) as $key) {
            if (($this->clock)() >= $deadline) {
                // Out of time: an incomplete look must not pick a single match
                throw new \RuntimeException('time budget spent while checking candidates');
            }
            $candidate = $this->adapter->getTransactionStatus($method, $storeId, (string)$key, $wasTest);

            if ($this->matches($candidate, $isCredit, $amount, $startedAt, $originalKey)) {
                $matches[(string)$key] = $candidate;
            }
        }

        return $matches;
    }

    /**
     * Matches
     *
     * @param TransactionResponse $candidate
     * @param bool                $isCredit
     * @param float|null          $amount
     * @param int                 $startedAt
     * @param string              $originalKey
     *
     * @return bool
     */
    private function matches(
        TransactionResponse $candidate,
        bool $isCredit,
        ?float $amount,
        int $startedAt,
        string $originalKey
    ): bool {
        $created = $this->createdAt($candidate);
        if ($created === null || $created < $startedAt - self::CLOCK_TOLERANCE) {
            return false;
        }

        $candidateAmount = $isCredit ? $candidate->get('AmountCredit') : $candidate->get('AmountDebit');
        if ($candidateAmount === null || ($amount !== null && abs((float)$candidateAmount - $amount) > 0.005)) {
            return false;
        }

        if ($originalKey !== '' && !$this->relatesTo($candidate, $originalKey)) {
            return false;
        }

        return true;
    }

    /**
     * Relates to
     *
     * @param TransactionResponse $candidate
     * @param string              $originalKey
     *
     * @return bool
     */
    private function relatesTo(TransactionResponse $candidate, string $originalKey): bool
    {
        foreach ((array)$candidate->get('RelatedTransactions') as $related) {
            if (strcasecmp((string)($related['RelatedTransactionKey'] ?? ''), $originalKey) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Created at
     *
     * @param TransactionResponse $candidate
     *
     * @return int|null
     */
    private function createdAt(TransactionResponse $candidate): ?int
    {
        $status = (array)$candidate->get('Status');
        $dateTime = (string)($status['DateTime'] ?? '');

        if ($dateTime === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($dateTime, new \DateTimeZone(self::BUCKAROO_TIMEZONE)))->getTimestamp();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Log
     *
     * @param string $message
     * @param string $invoice
     * @param string $action
     * @param array  $context
     *
     * @return void
     */
    private function log(string $message, string $invoice, string $action, array $context = []): void
    {
        $this->logger->addDebug(sprintf(
            '[OUTCOME] | [%s] - %s | invoice: %s | action: %s | %s',
            __METHOD__,
            $message,
            $invoice,
            $action,
            json_encode($context)
        ));
    }
}

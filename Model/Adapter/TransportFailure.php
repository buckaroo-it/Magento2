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

namespace Buckaroo\Magento2\Model\Adapter;

/**
 * Why a request to Buckaroo got no usable answer (see TransportFailureClassifier).
 *
 * Guzzle reports every curl failure the same way (ConnectException, mostly curl error 28), whether
 * the request never left the server or Buckaroo received it and the answer never came back. Only
 * the curl transfer statistics in the handler context tell the two apart: a request that was sent
 * has a pre-transfer time and uploaded bytes.
 */
class TransportFailure
{
    /** Not a transport failure: Buckaroo answered (or the error happened before any request) */
    public const NONE = 'none';

    /** The request never reached Buckaroo: safe to treat as failed */
    public const NOT_SENT = 'not_sent';

    /** The request was sent and the answer is missing: Buckaroo may have processed it */
    public const UNKNOWN_OUTCOME = 'unknown_outcome';

    /**
     * @var string
     */
    private string $kind;

    /**
     * @var array
     */
    private array $details;

    /**
     * Constructor
     *
     * @param string $kind    One of NONE, NOT_SENT, UNKNOWN_OUTCOME
     * @param array  $details Connection statistics for the log
     */
    public function __construct(string $kind, array $details = [])
    {
        $this->kind = $kind;
        $this->details = $details;
    }

    /**
     * Get kind
     *
     * @return string
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * Is transport failure
     *
     * @return bool
     */
    public function isTransportFailure(): bool
    {
        return $this->kind !== self::NONE;
    }

    /**
     * Is outcome unknown
     *
     * @return bool
     */
    public function isOutcomeUnknown(): bool
    {
        return $this->kind === self::UNKNOWN_OUTCOME;
    }

    /**
     * Connection statistics for the log
     *
     * @return array
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}

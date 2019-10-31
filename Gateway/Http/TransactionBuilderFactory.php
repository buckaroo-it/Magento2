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

namespace TIG\Buckaroo\Gateway\Http;

class TransactionBuilderFactory
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var array
     */
    protected $transactionBuilders;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param array                                     $transactionBuilders
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        array $transactionBuilders = []
    ) {
        $this->objectManager = $objectManager;
        $this->transactionBuilders = $transactionBuilders;
    }

    /**
     * Retrieve proper transaction builder for the specified transaction type.
     *
     * @param string $builderType
     *
     * @return TransactionBuilderInterface
     * @throws \LogicException|\TIG\Buckaroo\Exception
     */
    public function get($builderType)
    {
        if (empty($this->transactionBuilders)) {
            throw new \LogicException('Transaction builder adapter is not set.');
        }
        foreach ($this->transactionBuilders as $transactionBuilderMetaData) {
            $transactionBuilderType = $transactionBuilderMetaData['type'];
            if ($transactionBuilderType == $builderType) {
                $transactionBuilderClass = $transactionBuilderMetaData['model'];
                break;
            }
        }

        if (!isset($transactionBuilderClass) || empty($transactionBuilderClass)) {
            throw new \TIG\Buckaroo\Exception(
                new \Magento\Framework\Phrase(
                    'Unknown transaction builder type requested: %1.',
                    [$builderType]
                )
            );
        }

        $transactionBuilder = $this->objectManager->get($transactionBuilderClass);
        if (!$transactionBuilder instanceof TransactionBuilderInterface) {
            throw new \LogicException(
                'The transaction builder must implement "TIG\Buckaroo\Gateway\Http\TransactionBuilderInterface".'
            );
        }
        return $transactionBuilder;
    }
}

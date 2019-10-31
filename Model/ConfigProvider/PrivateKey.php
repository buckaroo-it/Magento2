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
namespace TIG\Buckaroo\Model\ConfigProvider;

use Magento\Checkout\Model\ConfigProviderInterface;
use TIG\Buckaroo\Api\CertificateRepositoryInterface;

class PrivateKey implements ConfigProviderInterface
{
    /**
     * Xpath to the 'certificate_upload' setting.
     */
    const XPATH_CERTIFICATE_ID = 'tig_buckaroo/account/certificate_file';

    /**
     * @var CertificateRepositoryInterface
     */
    protected $certificateRepository;

    /** @var Account */
    protected $account;

    /**
     * @param CertificateRepositoryInterface $certificateRepository
     * @param Account $account
     *
     * @throws \LogicException
     */
    public function __construct(
        CertificateRepositoryInterface $certificateRepository,
        Account $account
    ) {
        $this->certificateRepository = $certificateRepository;
        $this->account = $account;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig($store = null)
    {
        $config = [
            'private_key' => $this->getPrivateKey($store),
        ];
        return $config;
    }

    /**
     * Return private key from certificate
     *
     * @param $store
     *
     * @return string
     */
    public function getPrivateKey($store = null)
    {
        $certificateId = $this->account->getCertificateFile($store);

        if (!$certificateId) {
            throw new \LogicException('No Buckaroo certificate configured.');
        }

        $certificate = $this->certificateRepository->getById($certificateId);

        return $certificate->getCertificate();
    }
}

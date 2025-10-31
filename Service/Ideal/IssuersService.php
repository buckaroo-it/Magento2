<?php

namespace Buckaroo\Magento2\Service\Ideal;

use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Asset\Repository;

class IssuersService
{
    /**
     * @var string
     */
    protected const CACHE_KEY = 'buckaroo_ideal_issuers';

    /**
     * @var int
     */
    protected const CACHE_LIFETIME_SECONDS = 86400; //24hours

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var Json
     */
    protected $serializer;

    /**
     * @var Repository
     */
    protected $assetRepo;

    /**
     * @var BuckarooAdapter
     */
    protected $buckarooAdapter;

    /**
     * @var array
     */
    protected const ISSUERS_IMAGES = [
        'ABNANL2A' => 'abnamro',
        'ASNBNL21' => 'asnbank',
        'INGBNL2A' => 'ing',
        'RABONL2U' => 'rabobank',
        'SNSBNL2A' => 'sns',
        'RBRBNL21' => 'regiobank',
        'TRIONL2U' => 'triodos',
        'FVLBNL22' => 'vanlanschot',
        'KNABNL2H' => 'knab',
        'BUNQNL2A' => 'bunq',
        'REVOLT21' => 'revolut',
        'BITSNL2A' => 'yoursafe',
        'NTSBDEB1' => 'n26',
        'NNBANL2G' => 'nn'
    ];

    /**
     * @param CacheInterface  $cache
     * @param Json            $serializer
     * @param Repository      $assetRepo
     * @param BuckarooAdapter $buckarooAdapter
     */
    public function __construct(
        CacheInterface $cache,
        Json $serializer,
        Repository $assetRepo,
        BuckarooAdapter $buckarooAdapter
    ) {
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->assetRepo = $assetRepo;
        $this->buckarooAdapter = $buckarooAdapter;
    }

    /**
     * Get a list of issuers
     *
     * @return array
     */
    public function get(): array
    {
        $issuers = $this->getCachedIssuers();
        if ($issuers === null) {
            return $this->updateCacheIssuers();
        }
        return $issuers;
    }

    /**
     * Request the issuer list from the payment engine and update the cache
     *
     * @return array
     */
    private function updateCacheIssuers(): array
    {
        $retrievedIssuers = $this->addLogos(
            $this->buckarooAdapter->getIdealIssuers()
        );

        if (count($retrievedIssuers)) {
            $this->cacheIssuers($retrievedIssuers);
            return $retrievedIssuers;
        }
        return [];
    }

    /**
     * Cache issuers in magento cache
     *
     * @param array $issuers
     */
    private function cacheIssuers(array $issuers): void
    {
        $this->cache->save(
            $this->serializer->serialize($issuers),
            self::CACHE_KEY,
            [],
            self::CACHE_LIFETIME_SECONDS
        );
    }

    /**
     * Get the list of issuers from cache
     *
     * @return array|null
     */
    private function getCachedIssuers(): ?array
    {
        $cacheData = $this->cache->load(self::CACHE_KEY);
        if ($cacheData === null || $cacheData === false) {
            return null;
        }
        $issuers = $this->serializer->unserialize($cacheData);
        if (!is_array($issuers)) {
            return null;
        }
        return $issuers;
    }

    /**
     * Add logo url to the list of issuer
     *
     * @param array $issuers
     *
     * @return array
     */
    private function addLogos(array $issuers): array
    {
        return array_map(
            function ($issuer) {
                $logo = null;
                if (isset($issuer['id'])) {
                    $logo = $this->getImageUrlByIssuerId($issuer['id']);
                }
                $issuer['logo'] = $logo;
                $issuer['code'] = $issuer['id'];
                return $issuer;
            },
            $issuers
        );
    }

    public function getImageUrlByIssuerId(?string $issuerId): ?string
    {
        if (isset(self::ISSUERS_IMAGES[$issuerId])) {
            $name = self::ISSUERS_IMAGES[$issuerId];
            return $this->getImageUrl("ideal/{$name}");
        }
        return null;
    }

    /**
     * Generate the url to the desired asset.
     *
     * @param string $imgName
     *
     * @return string
     */
    public function getImageUrl(string $imgName): string
    {
        return $this->assetRepo->getUrl("Buckaroo_Magento2::images/{$imgName}.svg");
    }
}

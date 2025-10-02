<?php

declare(strict_types=1);

namespace Ameax\BgbInterest;

/**
 * Package configuration
 */
class Config
{
    /**
     * @var string Cache directory for base rate data
     */
    private $cacheDirectory;

    /**
     * @var float Additional percentage points for consumers (§288 BGB: base rate + 5)
     */
    private $consumerSurcharge = 5.0;

    /**
     * @var float Additional percentage points for businesses (§288 BGB: base rate + 9)
     */
    private $businessSurcharge = 9.0;

    /**
     * Constructor
     *
     * @param  string|null  $cacheDirectory  Directory for cache files (default: system temp)
     * @param  float|null  $consumerSurcharge  Surcharge for consumers (default: 5.0)
     * @param  float|null  $businessSurcharge  Surcharge for businesses (default: 9.0)
     */
    public function __construct(
        ?string $cacheDirectory = null,
        ?float $consumerSurcharge = null,
        ?float $businessSurcharge = null
    ) {
        $this->cacheDirectory = $cacheDirectory ?? sys_get_temp_dir();

        if ($consumerSurcharge !== null) {
            $this->consumerSurcharge = $consumerSurcharge;
        }

        if ($businessSurcharge !== null) {
            $this->businessSurcharge = $businessSurcharge;
        }
    }

    /**
     * Get cache directory
     */
    public function getCacheDirectory(): string
    {
        return $this->cacheDirectory;
    }

    /**
     * Set cache directory
     */
    public function setCacheDirectory(string $directory): self
    {
        $this->cacheDirectory = $directory;

        return $this;
    }

    /**
     * Get consumer surcharge (percentage points)
     */
    public function getConsumerSurcharge(): float
    {
        return $this->consumerSurcharge;
    }

    /**
     * Set consumer surcharge (percentage points)
     */
    public function setConsumerSurcharge(float $surcharge): self
    {
        $this->consumerSurcharge = $surcharge;

        return $this;
    }

    /**
     * Get business surcharge (percentage points)
     */
    public function getBusinessSurcharge(): float
    {
        return $this->businessSurcharge;
    }

    /**
     * Set business surcharge (percentage points)
     */
    public function setBusinessSurcharge(float $surcharge): self
    {
        $this->businessSurcharge = $surcharge;

        return $this;
    }
}

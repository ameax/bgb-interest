<?php

declare(strict_types=1);

namespace Ameax\BgbInterest;

use RuntimeException;

/**
 * Fetches and caches base interest rates from Deutsche Bundesbank
 */
class BaseRateProvider
{
    /**
     * @var string Bundesbank API URL for base interest rates
     */
    private $apiUrl = 'https://api.statistiken.bundesbank.de/rest/download/BBIN1/M.DE.BBK.BBKBAS2.EUR.ME?format=sdmx&lang=de';

    /**
     * @var string Directory to store cached JSON data
     */
    private $cacheDirectory;

    /**
     * @var string Filename for cached JSON data
     */
    private $cacheFilename = 'base_rates.json';

    /**
     * Constructor
     *
     * @param  string|null  $cacheDirectory  Directory to store cache files (default: system temp directory)
     */
    public function __construct(?string $cacheDirectory = null)
    {
        $this->cacheDirectory = $cacheDirectory ?? sys_get_temp_dir();
    }

    /**
     * Fetch XML data from Bundesbank API
     *
     * @return string XML content
     *
     * @throws RuntimeException If fetching fails
     */
    public function fetchXml(): string
    {
        $xml = @file_get_contents($this->apiUrl);

        if ($xml === false) {
            throw new RuntimeException('Failed to fetch data from Bundesbank API');
        }

        return $xml;
    }

    /**
     * Parse SDMX XML and extract interest rate data
     *
     * @param  string  $xmlContent  XML content to parse
     * @return array<string, float> Array of interest rates with dates as keys (oldest first, only changes)
     *
     * @throws RuntimeException If parsing fails
     */
    public function parseXml(string $xmlContent): array
    {
        $xml = @simplexml_load_string($xmlContent);

        if ($xml === false) {
            throw new RuntimeException('Failed to parse XML data');
        }

        // Register namespaces
        $xml->registerXPathNamespace('generic', 'http://www.sdmx.org/resources/sdmxml/schemas/v2_1/data/generic');

        // Find all observations
        $observations = $xml->xpath('//generic:Obs');

        if ($observations === false || empty($observations)) {
            throw new RuntimeException('No observations found in XML data');
        }

        $rates = [];

        foreach ($observations as $obs) {
            $obs->registerXPathNamespace('generic', 'http://www.sdmx.org/resources/sdmxml/schemas/v2_1/data/generic');

            // Extract date (ObsDimension)
            $dateDimension = $obs->xpath('generic:ObsDimension');
            if (empty($dateDimension)) {
                continue;
            }

            $date = (string) $dateDimension[0]->attributes()['value'];

            // Extract value (ObsValue)
            $valueElement = $obs->xpath('generic:ObsValue');
            if (empty($valueElement)) {
                continue;
            }

            $value = (float) $valueElement[0]->attributes()['value'];

            $rates[$date] = $value;
        }

        // Sort by date (oldest first)
        ksort($rates);

        // Filter: only keep entries where rate changed
        return $this->filterChangesOnly($rates);
    }

    /**
     * Filter rates to only include entries where the rate changed
     *
     * @param  array<string, float>  $rates  All rates sorted by date
     * @return array<string, float> Filtered rates with only changes
     */
    private function filterChangesOnly(array $rates): array
    {
        $filtered = [];
        $previousRate = null;

        foreach ($rates as $date => $rate) {
            if ($previousRate === null || $rate !== $previousRate) {
                $filtered[$date] = $rate;
                $previousRate = $rate;
            }
        }

        return $filtered;
    }

    /**
     * Save parsed data to JSON file
     *
     * @param  array<string, float>  $data  Data to save
     * @return string Path to saved file
     *
     * @throws RuntimeException If saving fails
     */
    public function saveToJson(array $data): string
    {
        if (! is_dir($this->cacheDirectory)) {
            if (! @mkdir($this->cacheDirectory, 0755, true)) {
                throw new RuntimeException('Failed to create cache directory: '.$this->cacheDirectory);
            }
        }

        $filePath = $this->cacheDirectory.DIRECTORY_SEPARATOR.$this->cacheFilename;

        // Build JSON structure with metadata
        $jsonData = [
            'metadata' => [
                'source_url' => $this->apiUrl,
                'last_updated' => date('Y-m-d H:i:s'),
            ],
            'data' => $data,
        ];

        $json = json_encode($jsonData, JSON_PRETTY_PRINT);

        if ($json === false) {
            throw new RuntimeException('Failed to encode data to JSON');
        }

        if (@file_put_contents($filePath, $json) === false) {
            throw new RuntimeException('Failed to write JSON file: '.$filePath);
        }

        return $filePath;
    }

    /**
     * Fetch, parse and cache base interest rates
     *
     * @return array<string, float> Array of interest rates with dates as keys
     *
     * @throws RuntimeException If any step fails
     */
    public function updateCache(): array
    {
        $xml = $this->fetchXml();
        $rates = $this->parseXml($xml);
        $this->saveToJson($rates);

        return $rates;
    }

    /**
     * Get cache file path
     *
     * @return string Full path to cache file
     */
    public function getCacheFilePath(): string
    {
        return $this->cacheDirectory.DIRECTORY_SEPARATOR.$this->cacheFilename;
    }
}

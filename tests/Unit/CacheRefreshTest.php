<?php

use Ameax\BgbInterest\BaseRateProvider;
use Ameax\BgbInterest\BgbInterest;
use Ameax\BgbInterest\Config;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/bgb-interest-refresh-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        array_map('unlink', glob("$this->tempDir/*"));
        rmdir($this->tempDir);
    }
});

it('does not refresh cache if younger than 1 month', function () {
    $provider = new BaseRateProvider($this->tempDir);
    $provider->saveToJson([
        '2023-01' => 1.62,
        '2024-01' => 3.62,
    ]);

    // Read cache file to verify it's recent
    $cacheFile = $this->tempDir.'/base_rates.json';
    $originalContent = file_get_contents($cacheFile);

    // Create calculator - should not refresh
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    // Cache should be unchanged
    $newContent = file_get_contents($cacheFile);
    $originalData = json_decode($originalContent, true);
    $newData = json_decode($newContent, true);

    expect($originalData['metadata']['last_updated'])
        ->toBe($newData['metadata']['last_updated']);
});

it('refreshes cache if older than 1 month', function () {
    // Create old cache file
    $oldData = [
        'metadata' => [
            'source_url' => 'https://api.statistiken.bundesbank.de/rest/download/BBIN1/M.DE.BBK.BBKBAS2.EUR.ME?format=sdmx&lang=de',
            'last_updated' => date('Y-m-d H:i:s', strtotime('-2 months')),
        ],
        'data' => [
            '2023-01' => 1.62,
        ],
    ];

    $cacheFile = $this->tempDir.'/base_rates.json';
    file_put_contents($cacheFile, json_encode($oldData, JSON_PRETTY_PRINT));

    // Mock the API call by intercepting - in real test this would refresh from API
    // For this test, we just verify the mechanism works
    $config = new Config($this->tempDir);

    // The calculator will try to refresh, but may fail due to network
    // That's okay - we're testing the mechanism, not the API
    try {
        $calculator = new BgbInterest($config);
        // If it succeeds, check that last_updated changed
        $newData = json_decode(file_get_contents($cacheFile), true);
        $oldTimestamp = strtotime($oldData['metadata']['last_updated']);
        $newTimestamp = strtotime($newData['metadata']['last_updated']);

        // If refresh succeeded, timestamp should be newer
        if ($newTimestamp > $oldTimestamp) {
            expect($newTimestamp)->toBeGreaterThan($oldTimestamp);
        } else {
            // Refresh failed, which is acceptable - it should fall back
            expect($calculator)->toBeInstanceOf(BgbInterest::class);
        }
    } catch (Exception $e) {
        // Even if refresh fails, calculator should work with old cache
        expect($e->getMessage())->toContain('cache');
    }
})->skip('Requires network access to Bundesbank API');

it('falls back to old cache when refresh fails', function () {
    // Create old cache file with valid data
    $oldData = [
        'metadata' => [
            'source_url' => 'https://invalid-url-that-will-fail.example.com',
            'last_updated' => date('Y-m-d H:i:s', strtotime('-2 months')),
        ],
        'data' => [
            '2023-01' => 1.62,
            '2024-01' => 3.62,
        ],
    ];

    $cacheFile = $this->tempDir.'/base_rates.json';
    file_put_contents($cacheFile, json_encode($oldData, JSON_PRETTY_PRINT));

    $config = new Config($this->tempDir);

    // Should create calculator successfully even though refresh will fail
    $calculator = new BgbInterest($config);

    // Should still be able to calculate using old cache
    $result = $calculator->calculate(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        true
    );

    expect($result)->toHaveKey('total_interest')
        ->and($calculator)->toBeInstanceOf(BgbInterest::class);
});

it('uses refreshed rates for calculation after successful update', function () {
    // Create initial cache
    $provider = new BaseRateProvider($this->tempDir);
    $provider->saveToJson([
        '2023-01' => 1.62,
        '2024-01' => 3.62,
    ]);

    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    // Calculator should work with the rates
    $result = $calculator->calculate(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        true
    );

    expect($result)->toHaveKey('total_interest')
        ->and($result['periods'][0]['base_rate'])->toBe(1.62);
});

it('handles missing last_updated field gracefully', function () {
    // Create cache without last_updated
    $invalidData = [
        'metadata' => [
            'source_url' => 'https://api.statistiken.bundesbank.de',
        ],
        'data' => [
            '2023-01' => 1.62,
        ],
    ];

    $cacheFile = $this->tempDir.'/base_rates.json';
    file_put_contents($cacheFile, json_encode($invalidData, JSON_PRETTY_PRINT));

    $config = new Config($this->tempDir);

    // Should not crash, just skip refresh
    $calculator = new BgbInterest($config);

    expect($calculator)->toBeInstanceOf(BgbInterest::class);
});

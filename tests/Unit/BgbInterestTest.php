<?php

use Ameax\BgbInterest\BaseRateProvider;
use Ameax\BgbInterest\BgbInterest;
use Ameax\BgbInterest\Config;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/bgb-interest-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);

    // Create test cache with base rates
    $provider = new BaseRateProvider($this->tempDir);
    $provider->saveToJson([
        '2023-01' => 1.62,
        '2023-07' => 3.12,
        '2024-01' => 3.62,
        '2024-07' => 3.37,
        '2025-01' => 2.27,
    ]);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        array_map('unlink', glob("$this->tempDir/*"));
        rmdir($this->tempDir);
    }
});

it('calculates interest for consumer correctly', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $result = $calculator->calculate(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        true // Consumer
    );

    expect($result)->toHaveKey('total_interest')
        ->toHaveKey('total_days')
        ->toHaveKey('amount')
        ->toHaveKey('is_consumer')
        ->toHaveKey('periods')
        ->and($result['amount'])->toBe(10000.00)
        ->and($result['is_consumer'])->toBeTrue()
        ->and($result['total_days'])->toBe(181);

    // Base rate 1.62% + 5% = 6.62%
    // (10000 * 6.62 * 181) / 36500 = 328.28
    expect($result['total_interest'])->toBe(328.28);
});

it('calculates interest for business correctly', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $result = $calculator->calculate(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        false // Business
    );

    expect($result['is_consumer'])->toBeFalse();

    // Base rate 1.62% + 9% = 10.62%
    // (10000 * 10.62 * 181) / 36500 = 526.64
    expect($result['total_interest'])->toBe(526.64);
});

it('calculates interest across multiple rate periods', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $result = $calculator->calculate(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2024-01-01'),
        true
    );

    expect($result['periods'])->toHaveCount(2)
        ->and($result['periods'][0]['from'])->toBe('2023-01-01')
        ->and($result['periods'][0]['to'])->toBe('2023-07-01')
        ->and($result['periods'][0]['base_rate'])->toBe(1.62)
        ->and(round($result['periods'][0]['interest_rate'], 2))->toBe(6.62)
        ->and($result['periods'][1]['from'])->toBe('2023-07-02')
        ->and($result['periods'][1]['to'])->toBe('2024-01-01')
        ->and($result['periods'][1]['base_rate'])->toBe(3.12)
        ->and(round($result['periods'][1]['interest_rate'], 2))->toBe(8.12);
});

it('returns zero interest when payment date is before due date', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $result = $calculator->calculate(
        10000.00,
        new DateTime('2023-07-01'),
        new DateTime('2023-01-01'),
        true
    );

    expect($result['total_interest'])->toBe(0.0)
        ->and($result['total_days'])->toBe(0)
        ->and($result['periods'])->toBeEmpty();
});

it('returns zero interest when due date equals payment date', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $result = $calculator->calculate(
        10000.00,
        new DateTime('2023-07-01'),
        new DateTime('2023-07-01'),
        true
    );

    expect($result['total_interest'])->toBe(0.0)
        ->and($result['total_days'])->toBe(0);
});

it('splits calculation by calendar year when requested', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $result = $calculator->calculate(
        10000.00,
        new DateTime('2023-06-15'),
        new DateTime('2024-03-15'),
        true,
        true // Split by year
    );

    // Should have at least 2 periods: one ending 2023-12-31, one starting 2024-01-01
    $hasYearSplit = false;
    foreach ($result['periods'] as $period) {
        if ($period['to'] === '2023-12-31') {
            $hasYearSplit = true;
            break;
        }
    }

    expect($hasYearSplit)->toBeTrue();
});

it('uses custom surcharge rates from config', function () {
    $config = new Config($this->tempDir, 10.0, 15.0);
    $calculator = new BgbInterest($config);

    $resultConsumer = $calculator->calculate(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        true
    );

    $resultBusiness = $calculator->calculate(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        false
    );

    // Base rate 1.62% + 10% = 11.62% for consumer
    expect(round($resultConsumer['periods'][0]['interest_rate'], 2))->toBe(11.62);

    // Base rate 1.62% + 15% = 16.62% for business
    expect(round($resultBusiness['periods'][0]['interest_rate'], 2))->toBe(16.62);
});

it('throws exception for negative amount', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $calculator->calculate(
        -1000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        true
    );
})->throws(RuntimeException::class, 'Amount must be greater than zero');

it('throws exception for zero amount', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $calculator->calculate(
        0.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        true
    );
})->throws(RuntimeException::class, 'Amount must be greater than zero');

it('throws exception when cache file is missing', function () {
    $emptyDir = sys_get_temp_dir().'/bgb-interest-empty-'.uniqid();
    mkdir($emptyDir, 0755, true);

    $config = new Config($emptyDir);

    try {
        new BgbInterest($config);
    } finally {
        rmdir($emptyDir);
    }
})->throws(RuntimeException::class, 'Base rate cache file not found');

it('calculates total interest as sum of all periods', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $result = $calculator->calculate(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2024-01-01'),
        true
    );

    $manualSum = 0.0;
    foreach ($result['periods'] as $period) {
        $manualSum += $period['interest'];
    }

    expect($result['total_interest'])->toBe(round($manualSum, 2));
});

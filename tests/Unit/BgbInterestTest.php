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

it('calculates interest with partial payments correctly', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $partialPayments = [
        [
            'date' => new DateTime('2023-04-01'),
            'amount' => 3000.00,
        ],
    ];

    $result = $calculator->calculateWithPartialPayments(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        false, // Business
        $partialPayments
    );

    expect($result)->toHaveKey('partial_payments')
        ->and($result['partial_payments'])->toHaveCount(1)
        ->and($result['partial_payments'][0]['date'])->toBe('2023-04-01')
        ->and($result['partial_payments'][0]['amount'])->toBe(3000.00);

    // Should have at least 2 periods (before and after payment)
    expect($result['periods'])->toBeArray()
        ->and(count($result['periods']))->toBeGreaterThanOrEqual(2);

    // First period should have full principal
    expect($result['periods'][0]['principal'])->toBe(10000.00);

    // Find period with partial payment
    $hasPaymentPeriod = false;
    foreach ($result['periods'] as $period) {
        if (isset($period['partial_payment'])) {
            expect($period['partial_payment']['amount'])->toBe(3000.00);
            $hasPaymentPeriod = true;
        }
    }
    expect($hasPaymentPeriod)->toBeTrue();
});

it('reduces principal after partial payment', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $partialPayments = [
        [
            'date' => new DateTime('2023-04-01'),
            'amount' => 3000.00,
        ],
    ];

    $result = $calculator->calculateWithPartialPayments(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        false,
        $partialPayments
    );

    // Find the period after the payment
    $foundReducedPrincipal = false;
    foreach ($result['periods'] as $period) {
        if ($period['from'] > '2023-04-01') {
            expect($period['principal'])->toBe(7000.00);
            $foundReducedPrincipal = true;
            break;
        }
    }

    expect($foundReducedPrincipal)->toBeTrue();
});

it('handles multiple partial payments', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $partialPayments = [
        [
            'date' => new DateTime('2023-03-01'),
            'amount' => 2000.00,
        ],
        [
            'date' => new DateTime('2023-05-01'),
            'amount' => 3000.00,
        ],
    ];

    $result = $calculator->calculateWithPartialPayments(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        false,
        $partialPayments
    );

    expect($result['partial_payments'])->toHaveCount(2);

    // Check that principal reduces correctly
    $principals = [];
    foreach ($result['periods'] as $period) {
        $principals[] = $period['principal'];
    }

    expect($principals)->toContain(10000.00) // Initial
        ->and($principals)->toContain(8000.00) // After first payment
        ->and($principals)->toContain(5000.00); // After second payment
});

it('calculates less interest with partial payments than without', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $partialPayments = [
        [
            'date' => new DateTime('2023-04-01'),
            'amount' => 5000.00,
        ],
    ];

    $withPayments = $calculator->calculateWithPartialPayments(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        false,
        $partialPayments
    );

    $withoutPayments = $calculator->calculate(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        false
    );

    expect($withPayments['total_interest'])->toBeLessThan($withoutPayments['total_interest']);
});

it('ignores partial payments outside the interest period', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $partialPayments = [
        [
            'date' => new DateTime('2022-12-01'), // Before due date
            'amount' => 3000.00,
        ],
        [
            'date' => new DateTime('2023-08-01'), // After payment date
            'amount' => 2000.00,
        ],
    ];

    $result = $calculator->calculateWithPartialPayments(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        false,
        $partialPayments
    );

    expect($result['partial_payments'])->toBeEmpty();
});

it('validates partial payment format', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $partialPayments = [
        [
            'invalid' => 'format',
        ],
    ];

    $calculator->calculateWithPartialPayments(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        false,
        $partialPayments
    );
})->throws(RuntimeException::class, 'Invalid partial payment format');

it('validates partial payment date type', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $partialPayments = [
        [
            'date' => '2023-04-01', // String instead of DateTime
            'amount' => 3000.00,
        ],
    ];

    $calculator->calculateWithPartialPayments(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        false,
        $partialPayments
    );
})->throws(RuntimeException::class, 'Partial payment date must be a DateTime object');

it('validates partial payment amount', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    $partialPayments = [
        [
            'date' => new DateTime('2023-04-01'),
            'amount' => -1000.00,
        ],
    ];

    $calculator->calculateWithPartialPayments(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        false,
        $partialPayments
    );
})->throws(RuntimeException::class, 'Partial payment amount must be greater than zero');

it('sorts partial payments chronologically', function () {
    $config = new Config($this->tempDir);
    $calculator = new BgbInterest($config);

    // Provide payments in reverse order
    $partialPayments = [
        [
            'date' => new DateTime('2023-05-01'),
            'amount' => 3000.00,
        ],
        [
            'date' => new DateTime('2023-03-01'),
            'amount' => 2000.00,
        ],
    ];

    $result = $calculator->calculateWithPartialPayments(
        10000.00,
        new DateTime('2023-01-01'),
        new DateTime('2023-07-01'),
        false,
        $partialPayments
    );

    // Check that payments are sorted in result
    expect($result['partial_payments'][0]['date'])->toBe('2023-03-01')
        ->and($result['partial_payments'][1]['date'])->toBe('2023-05-01');
});

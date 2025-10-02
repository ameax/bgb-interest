<?php

require_once __DIR__.'/../vendor/autoload.php';

use Ameax\BgbInterest\BgbInterest;
use Ameax\BgbInterest\Config;

// Setup configuration with custom cache directory
$config = new Config(__DIR__.'/../data');

// Create calculator
$calculator = new BgbInterest($config);

echo "=== BGB §288 Default Interest Calculator ===\n\n";

// Example 1: Simple calculation across multiple rate periods
echo "Example 1: Consumer debt over multiple base rate periods\n";
echo str_repeat('-', 70)."\n";
$result = $calculator->calculate(
    10000.00,                           // 10,000 EUR
    new DateTime('2023-01-01'),         // Due date
    new DateTime('2025-01-01'),         // Payment date
    true,                               // Consumer
    false                               // No year split
);

echo "Amount: {$result['amount']} EUR\n";
echo 'Is Consumer: '.($result['is_consumer'] ? 'Yes' : 'No')."\n";
echo "Total Days: {$result['total_days']}\n";
echo "Total Interest: {$result['total_interest']} EUR\n\n";

echo "Periods:\n";
foreach ($result['periods'] as $period) {
    echo sprintf(
        "  %s to %s (%3d days): Base %.2f%% + 5%% = %.2f%% → %7.2f EUR\n",
        $period['from'],
        $period['to'],
        $period['days'],
        $period['base_rate'],
        $period['interest_rate'],
        $period['interest']
    );
}

// Example 2: Business transaction
echo "\n\nExample 2: Business debt (same period)\n";
echo str_repeat('-', 70)."\n";
$result = $calculator->calculate(
    10000.00,
    new DateTime('2023-01-01'),
    new DateTime('2025-01-01'),
    false,                              // Business
    false
);

echo "Amount: {$result['amount']} EUR\n";
echo 'Is Consumer: '.($result['is_consumer'] ? 'Yes' : 'No')."\n";
echo "Total Days: {$result['total_days']}\n";
echo "Total Interest: {$result['total_interest']} EUR\n\n";

echo "Periods:\n";
foreach ($result['periods'] as $period) {
    echo sprintf(
        "  %s to %s (%3d days): Base %.2f%% + 9%% = %.2f%% → %7.2f EUR\n",
        $period['from'],
        $period['to'],
        $period['days'],
        $period['base_rate'],
        $period['interest_rate'],
        $period['interest']
    );
}

// Example 3: With year splitting
echo "\n\nExample 3: Consumer debt with year splitting\n";
echo str_repeat('-', 70)."\n";
$result = $calculator->calculate(
    5000.00,
    new DateTime('2023-06-15'),
    new DateTime('2024-03-15'),
    true,
    true                                // Split by year
);

echo "Amount: {$result['amount']} EUR\n";
echo 'Is Consumer: '.($result['is_consumer'] ? 'Yes' : 'No')."\n";
echo "Total Days: {$result['total_days']}\n";
echo "Total Interest: {$result['total_interest']} EUR\n\n";

echo "Periods (split by calendar year):\n";
foreach ($result['periods'] as $period) {
    echo sprintf(
        "  %s to %s (%3d days): Base %.2f%% + 5%% = %.2f%% → %7.2f EUR\n",
        $period['from'],
        $period['to'],
        $period['days'],
        $period['base_rate'],
        $period['interest_rate'],
        $period['interest']
    );
}

// Example 4: Short period with negative base rate
echo "\n\nExample 4: During negative base rate period (2016-2022)\n";
echo str_repeat('-', 70)."\n";
$result = $calculator->calculate(
    8000.00,
    new DateTime('2020-01-01'),
    new DateTime('2020-12-31'),
    true,
    false
);

echo "Amount: {$result['amount']} EUR\n";
echo "Total Days: {$result['total_days']}\n";
echo "Total Interest: {$result['total_interest']} EUR\n";
echo "Note: Interest rate = -0.88% + 5% = 4.12% (still positive)\n\n";

foreach ($result['periods'] as $period) {
    echo sprintf(
        "  %s to %s (%3d days): Base %.2f%% + 5%% = %.2f%% → %7.2f EUR\n",
        $period['from'],
        $period['to'],
        $period['days'],
        $period['base_rate'],
        $period['interest_rate'],
        $period['interest']
    );
}

echo "\n".str_repeat('=', 70)."\n";
echo "Note: §289 BGB prohibits compound interest (Zinseszinsen)\n";
echo "All calculations are based on the original principal amount only.\n";

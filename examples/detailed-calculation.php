<?php

require_once __DIR__.'/../vendor/autoload.php';

use Ameax\BgbInterest\BgbInterest;
use Ameax\BgbInterest\Config;

// Setup configuration
$config = new Config(__DIR__.'/../data');
$calculator = new BgbInterest($config);

echo "=== Detailed Default Interest Calculation ===\n\n";
echo "Claim: 10,000.00 EUR\n";
echo "Due since: January 15, 2023\n";
echo 'Calculation until: '.date('F d, Y')." (today)\n";
echo "Type: Business (Base rate + 9 percentage points)\n";
echo str_repeat('=', 80)."\n\n";

$result = $calculator->calculate(
    10000.00,
    new DateTime('2023-01-15'),
    new DateTime('today'),
    false,  // Business (not consumer)
    false   // No year splitting
);

echo "SUMMARY:\n";
echo str_repeat('-', 80)."\n";
echo sprintf("Principal amount:        %10.2f EUR\n", $result['amount']);
echo sprintf("Total days in default:   %10d days\n", $result['total_days']);
echo sprintf("Total default interest:  %10.2f EUR\n", $result['total_interest']);
echo sprintf("Total claim:             %10.2f EUR\n\n", $result['amount'] + $result['total_interest']);

echo "DETAILED CALCULATION BY PERIOD:\n";
echo str_repeat('-', 80)."\n";

foreach ($result['periods'] as $i => $period) {
    echo sprintf("\nPeriod %d:\n", $i + 1);
    echo sprintf("  From:             %s\n", $period['from']);
    echo sprintf("  To:               %s\n", $period['to']);
    echo sprintf("  Days:             %d\n", $period['days']);
    echo sprintf("  Base rate:        %.2f%%\n", $period['base_rate']);
    echo sprintf("  Surcharge:        +9.00%%\n");
    echo sprintf("  Total rate:       %.2f%%\n", $period['interest_rate']);
    echo sprintf("  Calculation:      (10,000.00 × %.2f%% × %d days) / (100 × 365)\n",
        $period['interest_rate'],
        $period['days']
    );
    echo sprintf("  Period interest:  %10.2f EUR\n", $period['interest']);
}

echo "\n".str_repeat('=', 80)."\n";
echo "Note: According to §289 BGB, compound interest is not permitted.\n";
echo "The calculation is based solely on the principal amount of 10,000.00 EUR.\n";

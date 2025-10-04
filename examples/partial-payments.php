<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Ameax\BgbInterest\BgbInterest;
use Ameax\BgbInterest\Config;

// Initialize calculator
$config = new Config(__DIR__.'/../cache');
$calculator = new BgbInterest($config);

echo "=== BGB Interest Calculation with Partial Payments ===\n\n";

// Example: 10,000 EUR claim with partial payments
$initialAmount = 10000.00;
$dueDate = new DateTime('2023-01-15');
$paymentDate = new DateTime('2024-01-15');
$isConsumer = false; // Business transaction

// Define partial payments
$partialPayments = [
    [
        'date' => new DateTime('2023-06-01'),
        'amount' => 3000.00,
    ],
    [
        'date' => new DateTime('2023-10-15'),
        'amount' => 2000.00,
    ],
];

echo 'Initial claim: '.number_format($initialAmount, 2, ',', '.')." EUR\n";
echo 'Due date: '.$dueDate->format('d.m.Y')."\n";
echo 'Payment date: '.$paymentDate->format('d.m.Y')."\n";
echo 'Type: '.($isConsumer ? 'Consumer' : 'Business')."\n\n";

echo "Partial payments:\n";
foreach ($partialPayments as $i => $payment) {
    echo '  '.($i + 1).'. '.$payment['date']->format('d.m.Y').' - '.number_format($payment['amount'], 2, ',', '.')." EUR\n";
}
echo "\n";

// Calculate with partial payments
$result = $calculator->calculateWithPartialPayments(
    $initialAmount,
    $dueDate,
    $paymentDate,
    $isConsumer,
    $partialPayments
);

echo "=== Calculation Result ===\n\n";
echo 'Total interest: '.number_format($result['total_interest'], 2, ',', '.')." EUR\n";
echo 'Total days in default: '.$result['total_days']."\n\n";

echo "=== Detailed Breakdown ===\n\n";

$periodNumber = 1;
foreach ($result['periods'] as $period) {
    echo "Period {$periodNumber}:\n";
    echo "  Date range: {$period['from']} to {$period['to']} ({$period['days']} days)\n";
    echo '  Principal amount: '.number_format($period['principal'], 2, ',', '.')." EUR\n";
    echo '  Base rate: '.number_format($period['base_rate'], 2, ',', '.').'% + '.
         number_format($period['interest_rate'] - $period['base_rate'], 2, ',', '.').'% = '.
         number_format($period['interest_rate'], 2, ',', '.')."% p.a.\n";
    echo '  Interest: '.number_format($period['interest'], 2, ',', '.')." EUR\n";

    if (isset($period['partial_payment'])) {
        echo "  >>> Partial payment on {$period['partial_payment']['date']}: ".
             number_format($period['partial_payment']['amount'], 2, ',', '.')." EUR\n";
        echo '  >>> Remaining principal: '.number_format(
            $period['principal'] - $period['partial_payment']['amount'],
            2,
            ',',
            '.'
        )." EUR\n";
    }

    echo "\n";
    $periodNumber++;
}

// Comparison: Calculate without partial payments
echo "=== Comparison: Without Partial Payments ===\n\n";

$resultWithoutPayments = $calculator->calculate(
    $initialAmount,
    $dueDate,
    $paymentDate,
    $isConsumer
);

echo 'Total interest (without partial payments): '.
     number_format($resultWithoutPayments['total_interest'], 2, ',', '.')." EUR\n";
echo 'Total interest (with partial payments): '.
     number_format($result['total_interest'], 2, ',', '.')." EUR\n";
echo 'Savings from partial payments: '.
     number_format($resultWithoutPayments['total_interest'] - $result['total_interest'], 2, ',', '.')." EUR\n";

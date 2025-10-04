<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Ameax\BgbInterest\BgbInterest;
use Ameax\BgbInterest\Config;

$config = new Config(__DIR__.'/../cache');
$calculator = new BgbInterest($config);

echo "=== Rückgabewert der calculateWithPartialPayments() Funktion ===\n\n";

$partialPayments = [
    [
        'date' => new DateTime('2023-04-02'),
        'amount' => 500.00,
    ],
];

$result = $calculator->calculateWithPartialPayments(
    1000.00,
    new DateTime('2023-02-01'),
    new DateTime('2023-06-01'),
    false,
    $partialPayments
);

echo "Rückgabewert (JSON formatiert):\n";
echo "================================\n\n";
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n";

echo "Struktur erklärt:\n";
echo "=================\n\n";

echo "• total_interest: {$result['total_interest']} EUR\n";
echo "  → Gesamte Verzugszinsen\n\n";

echo "• total_days: {$result['total_days']}\n";
echo "  → Gesamte Verzugstage\n\n";

echo "• amount: {$result['amount']} EUR\n";
echo "  → Ursprünglicher Hauptbetrag\n\n";

echo "• is_consumer: ".($result['is_consumer'] ? 'true' : 'false')."\n";
echo "  → Verbraucher (true) oder Unternehmer (false)\n\n";

echo "• partial_payments: ".count($result['partial_payments'])." Einträge\n";
foreach ($result['partial_payments'] as $i => $payment) {
    echo "  [{$i}] {$payment['date']}: {$payment['amount']} EUR\n";
}
echo "\n";

echo "• periods: ".count($result['periods'])." Perioden\n\n";

foreach ($result['periods'] as $i => $period) {
    echo "  Periode [{$i}]:\n";
    echo "    from: {$period['from']}\n";
    echo "    to: {$period['to']}\n";
    echo "    days: {$period['days']}\n";
    echo "    base_rate: {$period['base_rate']}%\n";
    echo "    interest_rate: {$period['interest_rate']}%\n";
    echo "    interest: {$period['interest']} EUR\n";
    echo "    principal: {$period['principal']} EUR  ← Hauptbetrag für diese Periode\n";

    if (isset($period['partial_payment'])) {
        echo "    partial_payment:\n";
        echo "      date: {$period['partial_payment']['date']}\n";
        echo "      amount: {$period['partial_payment']['amount']} EUR\n";
        echo "      ↑ Diese Periode endet mit einer Teilzahlung\n";
    }

    echo "\n";
}

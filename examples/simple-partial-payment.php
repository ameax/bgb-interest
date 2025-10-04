<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Ameax\BgbInterest\BgbInterest;
use Ameax\BgbInterest\Config;

// Initialize calculator
$config = new Config(__DIR__.'/../cache');
$calculator = new BgbInterest($config);

echo "=== Einfaches Beispiel zur Nachrechnung ===\n\n";

// Einfaches Beispiel: 1.000 EUR, bleiben im gleichen Zinszeitraum
$initialAmount = 1000.00;
$dueDate = new DateTime('2023-02-01');      // Fällig ab 1. Februar
$paymentDate = new DateTime('2023-06-01');  // Bezahlt am 1. Juni (120 Tage, vor Zinsänderung am 01.07.)
$isConsumer = false; // Unternehmer (Basiszins 1,62% + 9% = 10,62%)

echo "Ausgangssituation:\n";
echo "═══════════════════\n";
echo "Forderung: 1.000,00 EUR\n";
echo "Fällig seit: 01.02.2023\n";
echo "Bezahlt am: 01.06.2023 (120 Tage Verzug)\n";
echo "Zinssatz: 1,62% + 9% = 10,62% p.a. (konstant)\n\n";

// Ohne Teilzahlung
$withoutPayment = $calculator->calculate(
    $initialAmount,
    $dueDate,
    $paymentDate,
    $isConsumer
);

echo "OHNE Teilzahlung:\n";
echo "─────────────────\n";
echo "Formel: (1.000 × 10,62 × 120) / 36.500 = {$withoutPayment['total_interest']} EUR\n";
echo "Rechnung: 1.274.400 / 36.500 = 34,91 EUR\n\n";

// Mit Teilzahlung nach 60 Tagen
$partialPayments = [
    [
        'date' => new DateTime('2023-04-02'),  // Nach 60 Tagen (01.02. + 60 Tage)
        'amount' => 500.00,                     // Hälfte wird bezahlt
    ],
];

$withPayment = $calculator->calculateWithPartialPayments(
    $initialAmount,
    $dueDate,
    $paymentDate,
    $isConsumer,
    $partialPayments
);

echo "MIT Teilzahlung (500 EUR nach 60 Tagen):\n";
echo "════════════════════════════════════════\n\n";

echo "Periode 1 (01.02. bis 02.04. = 60 Tage):\n";
echo "  Hauptbetrag: 1.000,00 EUR\n";
echo "  Formel: (1.000 × 10,62 × 60) / 36.500 = 17,45 EUR\n";
echo "  Berechnet: {$withPayment['periods'][0]['interest']} EUR\n\n";

echo ">>> Teilzahlung: 500,00 EUR am 02.04.2023\n";
echo ">>> Neuer Hauptbetrag: 500,00 EUR\n\n";

echo "Periode 2 (03.04. bis 01.06. = 59 Tage):\n";
echo "  Hauptbetrag: 500,00 EUR\n";
echo "  Formel: (500 × 10,62 × 59) / 36.500 = 8,59 EUR\n";
echo "  Berechnet: {$withPayment['periods'][1]['interest']} EUR\n\n";

echo "Gesamtzinsen MIT Teilzahlung:\n";
echo "  17,45 + 8,59 = {$withPayment['total_interest']} EUR\n\n";

echo "Ersparnis durch Teilzahlung:\n";
echo "═══════════════════════════════\n";
echo "Ohne Teilzahlung: {$withoutPayment['total_interest']} EUR\n";
echo "Mit Teilzahlung:  {$withPayment['total_interest']} EUR\n";
echo "Ersparnis:        ".number_format($withoutPayment['total_interest'] - $withPayment['total_interest'], 2, ',', '.')." EUR\n\n";

echo "Nachrechnung der Ersparnis:\n";
echo "  Ohne Zahlung: 1.000 EUR × 10,62% × 120 Tage = 34,91 EUR\n";
echo "  Mit Zahlung:  1.000 EUR × 10,62% ×  60 Tage = 17,45 EUR\n";
echo "              +  500 EUR × 10,62% ×  59 Tage =  8,59 EUR\n";
echo "                                              = 26,04 EUR\n";
echo "  Ersparnis: 34,91 - 26,04 = 8,87 EUR ✓\n";

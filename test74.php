<?php

require_once __DIR__ . '/vendor/autoload.php';

use Ameax\BgbInterest\BgbInterest;

$calculator = new BgbInterest();

// Test: 1000 EUR, fällig am 1.1.2024, bezahlt am 1.2.2024 (31 Tage Verzug)
$amount = 1000.00;
$dueDate = new DateTime('2024-01-01');
$paymentDate = new DateTime('2024-02-01');

echo "Test 1: Consumer (Verbraucher)\n";
echo "Amount: " . $amount . " EUR\n";
echo "Due: " . $dueDate->format('Y-m-d') . "\n";
echo "Paid: " . $paymentDate->format('Y-m-d') . "\n";
$interest = $calculator->calculate($amount, $dueDate, $paymentDate, false);
echo "Interest: " . round($interest, 2) . " EUR\n\n";

echo "Test 2: Business (Unternehmer)\n";
echo "Amount: " . $amount . " EUR\n";
echo "Due: " . $dueDate->format('Y-m-d') . "\n";
echo "Paid: " . $paymentDate->format('Y-m-d') . "\n";
$interest = $calculator->calculate($amount, $dueDate, $paymentDate, true);
echo "Interest: " . round($interest, 2) . " EUR\n";

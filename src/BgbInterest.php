<?php

declare(strict_types=1);

namespace Ameax\BgbInterest;

use DateInterval;
use DateTime;
use RuntimeException;

/**
 * BGB interest calculator for default interest calculation (§288 BGB)
 *
 * Note: Compound interest (Zinseszinsen) is prohibited by §289 BGB
 */
class BgbInterest
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var array<string, float> Cached base rates from JSON file
     */
    private $baseRates = [];

    /**
     * Constructor
     *
     * @param  Config|null  $config  Configuration object
     */
    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? new Config;
        $this->loadBaseRates();
        $this->refreshCacheIfNeeded();
    }

    /**
     * Calculate default interest according to BGB §288
     *
     * Calculates interest for each period where the base rate changes.
     * No compound interest is calculated (prohibited by §289 BGB).
     *
     * @param  float  $amount  The principal amount
     * @param  DateTime  $dueDate  The due date (start of default)
     * @param  DateTime  $paymentDate  The payment date (end of default)
     * @param  bool  $isConsumer  Whether this is a consumer transaction (default: true)
     * @param  bool  $splitByYear  Whether to split calculation by calendar year (default: false)
     * @return array{total_interest: float, total_days: int, amount: float, is_consumer: bool, periods: array<int, array{from: string, to: string, days: int, base_rate: float, interest_rate: float, interest: float}>} Detailed calculation result
     */
    public function calculate(
        float $amount,
        DateTime $dueDate,
        DateTime $paymentDate,
        bool $isConsumer = true,
        bool $splitByYear = false
    ): array {
        return $this->calculateWithPartialPayments(
            $amount,
            $dueDate,
            $paymentDate,
            $isConsumer,
            [],
            $splitByYear
        );
    }

    /**
     * Calculate default interest with partial payments according to BGB §288
     *
     * Calculates interest for each period, considering partial payments that reduce the principal.
     * No compound interest is calculated (prohibited by §289 BGB).
     *
     * @param  float  $amount  The principal amount
     * @param  DateTime  $dueDate  The due date (start of default)
     * @param  DateTime  $paymentDate  The payment date (end of default)
     * @param  bool  $isConsumer  Whether this is a consumer transaction (default: true)
     * @param  array<int, array{date: DateTime, amount: float}>  $partialPayments  Array of partial payments with date and amount
     * @param  bool  $splitByYear  Whether to split calculation by calendar year (default: false)
     * @return array{total_interest: float, total_days: int, amount: float, is_consumer: bool, periods: array<int, array{from: string, to: string, days: int, base_rate: float, interest_rate: float, interest: float, principal: float, partial_payment?: array{date: string, amount: float}}>, partial_payments: array<int, array{date: string, amount: float}>} Detailed calculation result
     */
    public function calculateWithPartialPayments(
        float $amount,
        DateTime $dueDate,
        DateTime $paymentDate,
        bool $isConsumer = true,
        array $partialPayments = [],
        bool $splitByYear = false
    ): array {
        if ($amount <= 0) {
            throw new RuntimeException('Amount must be greater than zero');
        }

        // Validate and sort partial payments
        $validatedPayments = $this->validateAndSortPartialPayments($partialPayments, $dueDate, $paymentDate);

        if ($dueDate >= $paymentDate) {
            return [
                'total_interest' => 0.0,
                'total_days' => 0,
                'amount' => $amount,
                'is_consumer' => $isConsumer,
                'periods' => [],
                'partial_payments' => $this->formatPartialPayments($validatedPayments),
            ];
        }

        $surcharge = $isConsumer
            ? $this->config->getConsumerSurcharge()
            : $this->config->getBusinessSurcharge();

        $periods = $this->calculatePeriodsWithPartialPayments(
            $dueDate,
            $paymentDate,
            $amount,
            $surcharge,
            $validatedPayments,
            $splitByYear
        );

        $totalInterest = 0.0;
        foreach ($periods as $period) {
            $totalInterest += $period['interest'];
        }

        $totalDays = $this->calculateDays($dueDate, $paymentDate);

        return [
            'total_interest' => round($totalInterest, 2),
            'total_days' => $totalDays,
            'amount' => $amount,
            'is_consumer' => $isConsumer,
            'periods' => $periods,
            'partial_payments' => $this->formatPartialPayments($validatedPayments),
        ];
    }

    /**
     * Calculate interest periods with partial payments
     *
     * @param  DateTime  $startDate  Start date
     * @param  DateTime  $endDate  End date
     * @param  float  $initialAmount  Initial principal amount
     * @param  float  $surcharge  Surcharge percentage points
     * @param  array<int, array{date: DateTime, amount: float}>  $partialPayments  Sorted partial payments
     * @param  bool  $splitByYear  Whether to split by calendar year
     * @return array<int, array{from: string, to: string, days: int, base_rate: float, interest_rate: float, interest: float, principal: float, partial_payment?: array{date: string, amount: float}}> Array of period calculations
     */
    private function calculatePeriodsWithPartialPayments(
        DateTime $startDate,
        DateTime $endDate,
        float $initialAmount,
        float $surcharge,
        array $partialPayments,
        bool $splitByYear
    ): array {
        $periods = [];
        $currentStart = clone $startDate;
        $currentPrincipal = $initialAmount;
        $paymentIndex = 0;

        while ($currentStart < $endDate) {
            $baseRate = $this->getBaseRateForDate($currentStart);
            $nextChangeDate = $this->getNextRateChangeDate($currentStart, $endDate);

            // Check if there's a partial payment in this period
            $nextPaymentDate = null;
            if ($paymentIndex < count($partialPayments)) {
                $nextPaymentDate = $partialPayments[$paymentIndex]['date'];
            }

            // If split by year is enabled, check if we need to split at year boundary
            if ($splitByYear) {
                $yearEnd = new DateTime($currentStart->format('Y').'-12-31 23:59:59');
                if ($yearEnd < $nextChangeDate && $yearEnd < $endDate) {
                    $nextChangeDate = $yearEnd;
                }
            }

            // Determine the end of this period
            $periodEnd = $nextChangeDate < $endDate ? $nextChangeDate : $endDate;

            // If there's a partial payment before the period end, split at payment date
            if ($nextPaymentDate !== null && $nextPaymentDate > $currentStart && $nextPaymentDate <= $periodEnd) {
                $periodEnd = $nextPaymentDate;
            }

            $days = $this->calculateDays($currentStart, $periodEnd);

            if ($days > 0 && $currentPrincipal > 0) {
                $interestRate = $baseRate + $surcharge;
                $interest = ($currentPrincipal * $interestRate * $days) / (100 * 365);

                $period = [
                    'from' => $currentStart->format('Y-m-d'),
                    'to' => $periodEnd->format('Y-m-d'),
                    'days' => $days,
                    'base_rate' => $baseRate,
                    'interest_rate' => $interestRate,
                    'interest' => round($interest, 2),
                    'principal' => $currentPrincipal,
                ];

                // Check if this period ends with a partial payment
                if ($nextPaymentDate !== null && $nextPaymentDate == $periodEnd) {
                    $payment = $partialPayments[$paymentIndex];
                    $period['partial_payment'] = [
                        'date' => $payment['date']->format('Y-m-d'),
                        'amount' => $payment['amount'],
                    ];
                    $currentPrincipal = max(0, $currentPrincipal - $payment['amount']);
                    $paymentIndex++;
                }

                $periods[] = $period;
            }

            $currentStart = clone $periodEnd;
            $currentStart->add(new DateInterval('P1D'));
        }

        return $periods;
    }

    /**
     * Get base rate for a specific date
     *
     * @param  DateTime  $date  The date
     * @return float The base rate
     */
    private function getBaseRateForDate(DateTime $date): float
    {
        $dateStr = $date->format('Y-m');
        $applicableRate = null;

        foreach ($this->baseRates as $rateDate => $rate) {
            if ($rateDate <= $dateStr) {
                $applicableRate = $rate;
            } else {
                break;
            }
        }

        if ($applicableRate === null) {
            throw new RuntimeException('No base rate found for date: '.$date->format('Y-m-d'));
        }

        return $applicableRate;
    }

    /**
     * Get the date when the next rate change occurs
     *
     * @param  DateTime  $currentDate  Current date
     * @param  DateTime  $maxDate  Maximum date to check
     * @return DateTime Next change date or max date
     */
    private function getNextRateChangeDate(DateTime $currentDate, DateTime $maxDate): DateTime
    {
        $currentStr = $currentDate->format('Y-m');
        $nextDate = null;

        foreach ($this->baseRates as $rateDate => $rate) {
            if ($rateDate > $currentStr) {
                // Parse the rate date (YYYY-MM) and create DateTime for first day of that month
                $parts = explode('-', $rateDate);
                $nextDate = new DateTime($parts[0].'-'.$parts[1].'-01');
                break;
            }
        }

        if ($nextDate === null || $nextDate > $maxDate) {
            return $maxDate;
        }

        return $nextDate;
    }

    /**
     * Calculate the number of days between two dates
     *
     * @param  DateTime  $start  Start date
     * @param  DateTime  $end  End date
     * @return int Number of days
     */
    private function calculateDays(DateTime $start, DateTime $end): int
    {
        $interval = $start->diff($end);

        return (int) $interval->format('%a');
    }

    /**
     * Load base rates from cached JSON file
     *
     * @throws RuntimeException If cache file not found or invalid
     */
    private function loadBaseRates(): void
    {
        $cacheFile = $this->config->getCacheDirectory().DIRECTORY_SEPARATOR.'base_rates.json';

        if (! file_exists($cacheFile)) {
            throw new RuntimeException(
                'Base rate cache file not found. Please run BaseRateProvider->updateCache() first. '.
                'Expected file: '.$cacheFile
            );
        }

        $content = @file_get_contents($cacheFile);
        if ($content === false) {
            throw new RuntimeException('Failed to read base rate cache file: '.$cacheFile);
        }

        $data = json_decode($content, true);
        if (! is_array($data) || ! isset($data['data']) || ! is_array($data['data'])) {
            throw new RuntimeException('Invalid base rate cache file format: '.$cacheFile);
        }

        // Validate that all values are floats
        foreach ($data['data'] as $date => $rate) {
            if (! is_string($date) || ! is_float($rate) && ! is_int($rate)) {
                throw new RuntimeException('Invalid base rate data format: '.$cacheFile);
            }
        }

        /** @var array<string, float> $validatedRates */
        $validatedRates = $data['data'];
        $this->baseRates = $validatedRates;
    }

    /**
     * Check if cache is older than 1 month and refresh if needed
     * Falls back to old cache if refresh fails
     */
    private function refreshCacheIfNeeded(): void
    {
        $cacheFile = $this->config->getCacheDirectory().DIRECTORY_SEPARATOR.'base_rates.json';

        if (! file_exists($cacheFile)) {
            return;
        }

        $content = @file_get_contents($cacheFile);
        if ($content === false) {
            return;
        }

        $data = json_decode($content, true);
        if (! is_array($data) || ! isset($data['metadata']) || ! is_array($data['metadata']) || ! isset($data['metadata']['last_updated']) || ! is_string($data['metadata']['last_updated'])) {
            return;
        }

        try {
            $lastUpdated = new DateTime($data['metadata']['last_updated']);
            $oneMonthAgo = new DateTime('-1 month');

            if ($lastUpdated < $oneMonthAgo) {
                // Cache is older than 1 month, try to refresh
                $provider = new BaseRateProvider($this->config->getCacheDirectory());
                $newRates = $provider->updateCache();

                // Reload the newly cached rates
                $this->baseRates = $newRates;
            }
        } catch (\Exception $e) {
            // If refresh fails, continue with existing cache
            // The existing rates are already loaded in loadBaseRates()
        }
    }

    /**
     * Validate and sort partial payments chronologically
     *
     * @param  array<mixed>  $partialPayments  Partial payments
     * @param  DateTime  $dueDate  Due date (start of default)
     * @param  DateTime  $paymentDate  Payment date (end of default)
     * @return array<int, array{date: DateTime, amount: float}> Validated and sorted payments
     */
    private function validateAndSortPartialPayments(array $partialPayments, DateTime $dueDate, DateTime $paymentDate): array
    {
        $validated = [];

        foreach ($partialPayments as $payment) {
            if (! is_array($payment) || ! array_key_exists('date', $payment) || ! array_key_exists('amount', $payment)) {
                throw new RuntimeException('Invalid partial payment format. Expected array with "date" and "amount" keys.');
            }

            if (! ($payment['date'] instanceof DateTime)) {
                throw new RuntimeException('Partial payment date must be a DateTime object.');
            }

            if (! is_numeric($payment['amount']) || $payment['amount'] <= 0) {
                throw new RuntimeException('Partial payment amount must be greater than zero.');
            }

            // Only include payments within the interest period
            if ($payment['date'] > $dueDate && $payment['date'] <= $paymentDate) {
                $validated[] = [
                    'date' => $payment['date'],
                    'amount' => (float) $payment['amount'],
                ];
            }
        }

        // Sort by date
        usort($validated, function ($a, $b) {
            return $a['date'] <=> $b['date'];
        });

        return $validated;
    }

    /**
     * Format partial payments for output
     *
     * @param  array<int, array{date: DateTime, amount: float}>  $partialPayments  Partial payments
     * @return array<int, array{date: string, amount: float}> Formatted payments
     */
    private function formatPartialPayments(array $partialPayments): array
    {
        $formatted = [];

        foreach ($partialPayments as $payment) {
            $formatted[] = [
                'date' => $payment['date']->format('Y-m-d'),
                'amount' => $payment['amount'],
            ];
        }

        return $formatted;
    }
}

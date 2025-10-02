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
        if ($amount <= 0) {
            throw new RuntimeException('Amount must be greater than zero');
        }

        if ($dueDate >= $paymentDate) {
            return [
                'total_interest' => 0.0,
                'total_days' => 0,
                'amount' => $amount,
                'is_consumer' => $isConsumer,
                'periods' => [],
            ];
        }

        $surcharge = $isConsumer
            ? $this->config->getConsumerSurcharge()
            : $this->config->getBusinessSurcharge();

        $periods = $this->calculatePeriods($dueDate, $paymentDate, $amount, $surcharge, $splitByYear);

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
        ];
    }

    /**
     * Calculate interest periods based on base rate changes
     *
     * @param  DateTime  $startDate  Start date
     * @param  DateTime  $endDate  End date
     * @param  float  $amount  Principal amount
     * @param  float  $surcharge  Surcharge percentage points
     * @param  bool  $splitByYear  Whether to split by calendar year
     * @return array<int, array{from: string, to: string, days: int, base_rate: float, interest_rate: float, interest: float}> Array of period calculations
     */
    private function calculatePeriods(
        DateTime $startDate,
        DateTime $endDate,
        float $amount,
        float $surcharge,
        bool $splitByYear
    ): array {
        $periods = [];
        $currentStart = clone $startDate;

        while ($currentStart < $endDate) {
            $baseRate = $this->getBaseRateForDate($currentStart);
            $nextChangeDate = $this->getNextRateChangeDate($currentStart, $endDate);

            // If split by year is enabled, check if we need to split at year boundary
            if ($splitByYear) {
                $yearEnd = new DateTime($currentStart->format('Y').'-12-31 23:59:59');
                if ($yearEnd < $nextChangeDate && $yearEnd < $endDate) {
                    $nextChangeDate = $yearEnd;
                }
            }

            $periodEnd = $nextChangeDate < $endDate ? $nextChangeDate : $endDate;
            $days = $this->calculateDays($currentStart, $periodEnd);

            if ($days > 0) {
                $interestRate = $baseRate + $surcharge;
                $interest = ($amount * $interestRate * $days) / (100 * 365);

                $periods[] = [
                    'from' => $currentStart->format('Y-m-d'),
                    'to' => $periodEnd->format('Y-m-d'),
                    'days' => $days,
                    'base_rate' => $baseRate,
                    'interest_rate' => $interestRate,
                    'interest' => round($interest, 2),
                ];
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
}

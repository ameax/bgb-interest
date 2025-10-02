<?php

declare(strict_types=1);

namespace Ameax\BgbInterest;

use DateTime;

/**
 * Simple BGB interest calculator for default interest calculation
 */
class BgbInterest
{
    /**
     * Calculate default interest according to BGB §288
     *
     * @param float $amount The principal amount
     * @param DateTime $dueDate The due date
     * @param DateTime $paymentDate The payment date
     * @param bool $isBusiness Whether this is a business transaction (default: false)
     * @return float The calculated interest amount
     */
    public function calculate(float $amount, DateTime $dueDate, DateTime $paymentDate, bool $isBusiness = false): float
    {
        $days = $this->calculateDays($dueDate, $paymentDate);

        if ($days <= 0) {
            return 0.0;
        }

        $baseRate = $this->getBaseRate($dueDate);
        $interestRate = $isBusiness ? $baseRate + 9.0 : $baseRate + 5.0;

        // Formula: (Amount * Interest Rate * Days) / (100 * 365)
        return ($amount * $interestRate * $days) / (100 * 365);
    }

    /**
     * Calculate the number of days between two dates
     *
     * @param DateTime $start Start date
     * @param DateTime $end End date
     * @return int Number of days
     */
    private function calculateDays(DateTime $start, DateTime $end): int
    {
        $interval = $start->diff($end);
        return (int) $interval->format('%r%a');
    }

    /**
     * Get the base interest rate for a given date
     *
     * @param DateTime $date The date to get the base rate for
     * @return float The base rate
     */
    private function getBaseRate(DateTime $date): float
    {
        // For now, return a fixed base rate
        // TODO: Implement actual historical base rates from Bundesbank
        return -0.88;
    }
}

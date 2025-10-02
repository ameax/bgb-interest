<?php

require_once __DIR__.'/../vendor/autoload.php';

use Ameax\BgbInterest\BaseRateProvider;

// Example 1: Using default temp directory
echo "Example 1: Default cache directory (temp)\n";
echo str_repeat('-', 50)."\n";

$provider = new BaseRateProvider;
echo 'Cache file path: '.$provider->getCacheFilePath()."\n\n";

try {
    echo "Fetching and parsing data from Bundesbank...\n";
    $rates = $provider->updateCache();

    echo 'Successfully fetched '.count($rates)." interest rate entries\n\n";

    echo "First 5 entries:\n";
    $count = 0;
    foreach ($rates as $date => $rate) {
        echo "  $date: $rate%\n";
        if (++$count >= 5) {
            break;
        }
    }

    echo "\nLast 5 entries:\n";
    $lastRates = array_slice($rates, -5, 5, true);
    foreach ($lastRates as $date => $rate) {
        echo "  $date: $rate%\n";
    }

    echo "\nJSON file created at: ".$provider->getCacheFilePath()."\n";

} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}

// Example 2: Using custom directory
echo "\n\n";
echo "Example 2: Custom cache directory\n";
echo str_repeat('-', 50)."\n";

$customDir = __DIR__.'/../data';
$provider2 = new BaseRateProvider($customDir);
echo 'Cache file path: '.$provider2->getCacheFilePath()."\n\n";

try {
    echo "Fetching and parsing data from Bundesbank...\n";
    $rates2 = $provider2->updateCache();

    echo 'Successfully saved '.count($rates2).' entries to: '.$provider2->getCacheFilePath()."\n";

} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}

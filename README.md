# BGB Interest Calculator

A PHP library for calculating default interest (Verzugszinsen) according to German Civil Code (BGB) §288.

[![License](https://img.shields.io/packagist/l/ameax/bgb-interest)](https://packagist.org/packages/ameax/bgb-interest)

---

**Framework-agnostic** | **PHP 7.4 - 8.3 compatible** | **Auto-updates base rates from Bundesbank**

## Features

✅ **Accurate BGB §288 Calculations**
- Consumer rate: Base rate + 5 percentage points
- Business rate: Base rate + 9 percentage points
- Period-based calculation with automatic rate changes
- No compound interest (§289 BGB compliant)

✅ **Automatic Base Rate Updates**
- Fetches historical data from Deutsche Bundesbank API
- Caches rates locally for performance
- Only stores rate changes (optimized data)

✅ **Flexible Configuration**
- Configurable cache directory
- Optional calendar year breakdown
- Customizable surcharge rates

## Requirements

- PHP 7.4 or higher
- ext-simplexml
- ext-json

## Installation

```bash
composer require ameax/bgb-interest
```

## Quick Start

### 1. Fetch Base Rates

First, fetch and cache the base interest rates from Bundesbank:

```php
use Ameax\BgbInterest\BaseRateProvider;

$provider = new BaseRateProvider('./cache');
$provider->updateCache();
```

### 2. Calculate Default Interest

```php
use Ameax\BgbInterest\BgbInterest;
use Ameax\BgbInterest\Config;

// Configure
$config = new Config('./cache');
$calculator = new BgbInterest($config);

// Calculate interest
$result = $calculator->calculate(
    10000.00,                      // Amount in EUR
    new DateTime('2023-01-15'),    // Due date
    new DateTime('2025-10-02'),    // Payment date
    false                          // false = Business, true = Consumer
);

echo "Total Interest: {$result['total_interest']} EUR\n";
echo "Total Days: {$result['total_days']}\n";
```

### Result Structure

```php
[
    'total_interest' => 3154.20,
    'total_days' => 991,
    'amount' => 10000.00,
    'is_consumer' => false,
    'periods' => [
        [
            'from' => '2023-01-15',
            'to' => '2023-07-01',
            'days' => 167,
            'base_rate' => 1.62,
            'interest_rate' => 10.62,
            'interest' => 485.90
        ],
        // ... more periods
    ]
]
```

## Advanced Usage

### Calendar Year Breakdown

Split calculations by calendar year for accounting purposes:

```php
$result = $calculator->calculate(
    5000.00,
    new DateTime('2023-06-15'),
    new DateTime('2024-03-15'),
    true,   // Consumer
    true    // Split by year
);
```

### Custom Configuration

```php
$config = new Config(
    '/custom/cache/path',  // Cache directory
    5.0,                   // Consumer surcharge (default: 5.0)
    9.0                    // Business surcharge (default: 9.0)
);
```

### Update Base Rates Periodically

The Bundesbank updates base rates semi-annually (January & July). Update your cache:

```php
$provider = new BaseRateProvider('./cache');
$rates = $provider->updateCache();

// Check last update
$cacheFile = $provider->getCacheFilePath();
$data = json_decode(file_get_contents($cacheFile), true);
echo "Last updated: {$data['metadata']['last_updated']}\n";
```

## Legal Information

### BGB §288 - Default Interest Rates

According to German law:
- **Consumers**: Base interest rate + 5 percentage points per year
- **Businesses**: Base interest rate + 9 percentage points per year

### BGB §289 - Compound Interest

**Compound interest (Zinseszinsen) is prohibited** by German law. This package only calculates simple interest on the principal amount.

## Examples

See the `examples/` directory for complete working examples:

- **fetch-base-rates.php** - Fetching and caching base rates
- **calculate-interest.php** - Various calculation scenarios
- **detailed-calculation.php** - Detailed breakdown with all periods

Run examples:
```bash
php examples/detailed-calculation.php
```

## Development

### Run Tests

```bash
composer test              # Full test suite
composer test:types        # PHPStan static analysis
composer test:lint         # Code style check
composer test:unit         # Unit tests
```

### Code Style

```bash
composer lint              # Fix code style
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

# Deutsche Dokumentation

Eine PHP-Bibliothek zur Berechnung von Verzugszinsen gemäß BGB §288.

## Funktionen

✅ **Präzise BGB §288 Berechnungen**
- Verbraucherzins: Basiszins + 5 Prozentpunkte
- Unternehmerzins: Basiszins + 9 Prozentpunkte
- Periodenbasierte Berechnung mit automatischen Zinsänderungen
- Keine Zinseszinsen (§289 BGB konform)

✅ **Automatische Basiszins-Aktualisierung**
- Lädt historische Daten von der Bundesbank API
- Lokales Caching für Performance
- Speichert nur Zinsänderungen (optimierte Daten)

✅ **Flexible Konfiguration**
- Konfigurierbares Cache-Verzeichnis
- Optional: Aufteilung nach Kalenderjahren
- Anpassbare Aufschlagsätze

## Voraussetzungen

- PHP 7.4 oder höher
- ext-simplexml
- ext-json

## Installation

```bash
composer require ameax/bgb-interest
```

## Schnellstart

### 1. Basiszinssätze abrufen

Zuerst die Basiszinssätze von der Bundesbank laden und cachen:

```php
use Ameax\BgbInterest\BaseRateProvider;

$provider = new BaseRateProvider('./cache');
$provider->updateCache();
```

### 2. Verzugszinsen berechnen

```php
use Ameax\BgbInterest\BgbInterest;
use Ameax\BgbInterest\Config;

// Konfiguration
$config = new Config('./cache');
$calculator = new BgbInterest($config);

// Berechnung
$result = $calculator->calculate(
    10000.00,                      // Betrag in EUR
    new DateTime('2023-01-15'),    // Fälligkeitsdatum
    new DateTime('2025-10-02'),    // Zahlungsdatum
    false                          // false = Unternehmer, true = Verbraucher
);

echo "Verzugszinsen: {$result['total_interest']} EUR\n";
echo "Verzugstage: {$result['total_days']}\n";
```

### Ergebnis-Struktur

```php
[
    'total_interest' => 3154.20,  // Gesamtzinsen
    'total_days' => 991,           // Verzugstage
    'amount' => 10000.00,          // Hauptforderung
    'is_consumer' => false,        // Verbraucher?
    'periods' => [                 // Perioden mit unterschiedlichen Zinssätzen
        [
            'from' => '2023-01-15',      // Von
            'to' => '2023-07-01',        // Bis
            'days' => 167,               // Tage
            'base_rate' => 1.62,         // Basiszins
            'interest_rate' => 10.62,    // Gesamtzins (Basiszins + 9%)
            'interest' => 485.90         // Zinsen dieser Periode
        ],
        // ... weitere Perioden
    ]
]
```

## Erweiterte Nutzung

### Aufteilung nach Kalenderjahren

Berechnung nach Kalenderjahren für buchhalterische Zwecke:

```php
$result = $calculator->calculate(
    5000.00,
    new DateTime('2023-06-15'),
    new DateTime('2024-03-15'),
    true,   // Verbraucher
    true    // Nach Jahren aufteilen
);
```

### Eigene Konfiguration

```php
$config = new Config(
    '/eigener/cache/pfad',  // Cache-Verzeichnis
    5.0,                    // Verbraucher-Aufschlag (Standard: 5.0)
    9.0                     // Unternehmer-Aufschlag (Standard: 9.0)
);
```

### Basiszinssätze aktualisieren

Die Bundesbank aktualisiert die Basiszinssätze halbjährlich (Januar & Juli). Cache aktualisieren:

```php
$provider = new BaseRateProvider('./cache');
$rates = $provider->updateCache();

// Letzte Aktualisierung prüfen
$cacheFile = $provider->getCacheFilePath();
$data = json_decode(file_get_contents($cacheFile), true);
echo "Zuletzt aktualisiert: {$data['metadata']['last_updated']}\n";
```

## Rechtliche Informationen

### BGB §288 - Verzugszinsen

Nach deutschem Recht:
- **Verbraucher**: Basiszinssatz + 5 Prozentpunkte pro Jahr
- **Unternehmer**: Basiszinssatz + 9 Prozentpunkte pro Jahr

### BGB §289 - Zinseszinsen

**Zinseszinsen sind verboten** nach deutschem Recht. Dieses Paket berechnet nur einfache Zinsen auf den Hauptbetrag.

## Beispiele

Siehe `examples/` Verzeichnis für vollständige Beispiele:

- **fetch-base-rates.php** - Basiszinssätze abrufen und cachen
- **calculate-interest.php** - Verschiedene Berechnungsszenarien
- **detailed-calculation.php** - Detaillierte Aufschlüsselung mit allen Perioden

Beispiele ausführen:
```bash
php examples/detailed-calculation.php
```

## Berechnung erklärt

Die Verzugszinsen werden nach folgender Formel berechnet:

```
Zinsen = (Hauptbetrag × Zinssatz × Tage) / (100 × 365)
```

### Beispielrechnung

Forderung: 10.000 EUR
Fällig seit: 15.01.2023
Bezahlt am: 02.10.2025 (991 Tage)
Art: Unternehmer

**Periode 1** (15.01.2023 - 01.07.2023, 167 Tage):
- Basiszins: 1,62% + 9% = 10,62%
- Zinsen: (10.000 × 10,62 × 167) / 36.500 = 485,90 EUR

**Periode 2** (02.07.2023 - 01.01.2024, 183 Tage):
- Basiszins: 3,12% + 9% = 12,12%
- Zinsen: (10.000 × 12,12 × 183) / 36.500 = 607,66 EUR

*... weitere Perioden ...*

**Gesamt: 3.154,20 EUR** Verzugszinsen

## Entwicklung

### Tests ausführen

```bash
composer test              # Vollständige Test-Suite
composer test:types        # PHPStan statische Analyse
composer test:lint         # Code-Style prüfen
composer test:unit         # Unit-Tests
```

### Code-Style

```bash
composer lint              # Code-Style korrigieren
```

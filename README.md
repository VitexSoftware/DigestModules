# DigestModules

A standalone PHP library for collecting and processing data from accounting systems for digest reports.

## Overview

This library provides data collection modules that return structured data (associative arrays) instead of HTML output. It's designed to be accounting-system agnostic and can be extended to support various accounting systems like AbraFlexi, Pohoda, Money S3, etc.

## Features

- **System Agnostic**: Modular design supports multiple accounting systems
- **JSON Output**: Returns structured data as associative arrays
- **Extensible**: Easy to add new modules and data providers
- **PSR-4 Compliant**: Follows PHP standards
- **Type Safe**: Full PHP 8.1+ type declarations

## Installation

```bash
composer require vitexsoftware/digest-modules
```

## Basic Usage

```php
use VitexSoftware\DigestModules\Core\ModuleRunner;
use VitexSoftware\DigestModules\Providers\AbraFlexiDataProvider;

// Create a data provider for your accounting system
$dataProvider = new AbraFlexiDataProvider($config);

// Create module runner
$runner = new ModuleRunner($dataProvider);

// Add modules
$runner->addModule('outcoming_invoices', \VitexSoftware\DigestModules\Modules\OutcomingInvoices::class);
$runner->addModule('debtors', \VitexSoftware\DigestModules\Modules\Debtors::class);

// Process data for a time period
$period = new \DatePeriod(
    new \DateTime('2024-01-01'),
    new \DateInterval('P1M'),
    new \DateTime('2024-02-01')
);

$results = $runner->run($period);

// Get JSON output
echo json_encode($results, JSON_PRETTY_PRINT);
```

## Module Structure

Each module returns structured data in this format:

```php
[
    'module_name' => 'outcoming_invoices',
    'heading' => 'Outcoming Invoices',
    'period' => [
        'start' => '2024-01-01',
        'end' => '2024-02-01'
    ],
    'success' => true,
    'data' => [
        'summary' => [
            'total_count' => 150,
            'total_amount' => 250000.50,
            'currency' => 'CZK'
        ],
        'by_type' => [...],
        'details' => [...]
    ],
    'metadata' => [
        'processing_time' => 0.123,
        'timestamp' => '2024-01-15T10:30:00Z'
    ]
]
```

## Available Modules

- **OutcomingInvoices**: Analyzes issued invoices
- **IncomingInvoices**: Analyzes received invoices
- **Debtors**: Tracks unpaid invoices and overdue amounts
- **NewCustomers**: Identifies new customers in period
- **BestSellers**: Top-selling products/services
- **WaitingPayments**: Outstanding payments

## Data Providers

- **AbraFlexiDataProvider**: For AbraFlexi accounting system
- **PohodaDataProvider**: For Pohoda accounting system (planned)
- **MoneyS3DataProvider**: For Money S3 accounting system (planned)

## Extending

Create custom modules by implementing `ModuleInterface`:

```php
class CustomModule implements ModuleInterface
{
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        // Your data collection logic
        return [
            'module_name' => 'custom_module',
            'heading' => 'Custom Analysis',
            'success' => true,
            'data' => $analyzedData
        ];
    }
}
```

## License

GPL-2.0-or-later
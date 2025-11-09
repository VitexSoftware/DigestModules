# DigestModules 📊

> **Modular Analytics Library for Accounting Systems**

A standalone PHP library that provides **data collection and analytics modules** for accounting systems, returning structured JSON data for further processing or visualization.

## 🎯 Overview

DigestModules is the **data collection engine** that:

- 🔌 **Connects to accounting systems** (AbraFlexi, Pohoda, Money S3, etc.)
- 📈 **Analyzes business data** (invoices, customers, payments, etc.)
- 📋 **Returns structured JSON** (no HTML - pure data layer)
- 🧩 **Modular architecture** (easy to extend with new analytics)
- 🔄 **System-agnostic design** (works across different accounting platforms)

## ✨ Key Features

- **🎯 Pure Data Layer**: Returns JSON arrays - no HTML generation
- **🔌 Multiple Providers**: AbraFlexi, Pohoda, and custom system support
- **📊 Built-in Analytics**: Invoice analysis, debt monitoring, financial insights
- **🧩 Modular Design**: Easy to add new modules and data sources  
- **⚡ Performance**: Optimized queries with caching support
- **🛡️ Type Safe**: Full PHP 8.1+ type declarations with strict types
- **📝 PSR-4 Compliant**: Follows PHP-FIG standards
- **🔍 Comprehensive Testing**: PHPUnit test coverage

## 🚀 Quick Start

### Installation

```bash
# Via Composer
composer require vitexsoftware/digest-modules

# Via Debian Package  
sudo apt install php-vitexsoftware-digest-modules
```

### Basic Usage

```php
<?php
use VitexSoftware\DigestModules\Core\ModuleRunner;
use VitexSoftware\DigestModules\Providers\AbraFlexiDataProvider;

// Connect to your accounting system
$dataProvider = new AbraFlexiDataProvider(
    'https://your-abraflexi.com',
    'username', 
    'password'
);

// Run analytics modules
$runner = new ModuleRunner($dataProvider);

// Get invoice analysis as JSON
$invoiceData = $runner->runModule('outcoming_invoices');
echo json_encode($invoiceData, JSON_PRETTY_PRINT);

// Get debtor analysis  
$debtorData = $runner->runModule('debtors');
echo json_encode($debtorData, JSON_PRETTY_PRINT);
```

### Expected JSON Output

```json
{
    "module": "outcoming_invoices",
    "heading": "Outcoming Invoices Analysis",
    "summary": {
        "total_amount": 125000.50,
        "currency": "CZK", 
        "count": 45,
        "processing_time": 0.234
    },
    "details": [
        {
            "customer": "ACME Corp",
            "amount": 25000.00,
            "date": "2024-12-15",
            "status": "paid"
        }
    ],
    "metadata": {
        "generated_at": "2024-12-23T10:30:45+01:00",
        "provider": "AbraFlexiDataProvider", 
        "system_version": "2023.1"
    }
}
```

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
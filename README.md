# DigestModules

> **System-agnostic data collection modules for accounting digest reports**

A standalone PHP library providing analytics modules that work with any accounting system through a neutral `DataProviderInterface`. Modules return structured arrays — no HTML, no rendering, no system-specific code.

## Overview

DigestModules is the **analytics engine** that:

- Defines a neutral vocabulary (`FILTER_*`, `FIELD_*`, `ENTITY_*` constants) for conditions and return fields
- Provides ready-made modules: invoices, payments, debtors, customers, products, reminders
- Stays completely decoupled from AbraFlexi, Pohoda, or any specific accounting system
- Returns structured data ready for rendering or further processing

Data providers (e.g. `AbraFlexiDataProvider` in `vitexsoftware/abraflexi-digest`) implement the interface and translate the neutral schema to system-specific queries.

## Installation

```bash
composer require vitexsoftware/digest-modules
```

## Quick Start

```php
<?php
use VitexSoftware\DigestModules\Core\ModuleRunner;
use VitexSoftware\DigestModules\Modules\Debtors;
use VitexSoftware\DigestModules\Modules\OutcomingInvoices;

// Use any DataProviderInterface implementation (e.g. AbraFlexiDataProvider)
$provider = new \AbraFlexi\Digest\Providers\AbraFlexiDataProvider($config);

$runner = new ModuleRunner($provider);
$runner->addModule('outcoming_invoices', new OutcomingInvoices());
$runner->addModule('debtors', new Debtors());

$period = new \DatePeriod(
    new \DateTime('first day of last month'),
    new \DateInterval('P1M'),
    new \DateTime('first day of this month'),
);

$result = $runner->run($period);
echo json_encode($result, JSON_PRETTY_PRINT);
```

## Available Modules

| Module class | Key | Description |
|---|---|---|
| `OutcomingInvoices` | `outcoming_invoices` | Issued invoices in the period |
| `IncomingInvoices` | `incoming_invoices` | Received invoices in the period |
| `IncomingPayments` | `incoming_payments` | Bank receipts in the period |
| `OutcomingPayments` | `outcoming_payments` | Bank outflows in the period |
| `Debtors` | `debtors` | All overdue unpaid receivables |
| `UnmatchedInvoices` | `unmatched_invoices` | Issued invoices not matched to a payment |
| `UnmatchedPayments` | `unmatched_payments` | Bank movements not matched to an invoice |
| `WaitingIncome` | `waiting_income` | Proforma invoices awaiting settlement |
| `WaitingPayments` | `waiting_payments` | Invoices awaiting payment |
| `NewCustomers` | `new_customers` | Contacts created in the period |
| `Reminds` | `reminds` | Invoices with pending payment reminders |
| `BestSellers` | `best_sellers` | Top products/services sold in the period |
| `WithoutEmail` | `without_email` | Contacts missing an email address |
| `WithoutTel` | `without_tel` | Contacts missing a phone number |
| `AllTime\PurchasePriceLowerThanSales` | `purchase_price_lower_than_sales` | Products sold below purchase price |

## Module Output Format

Every module returns an array from `AbstractModule::createResult()`:

```php
[
    'module_name' => 'outcoming_invoices',
    'heading'     => 'Outcoming Invoices',
    'description' => '...',
    'period' => [
        'start' => '2024-01-01',
        'end'   => '2024-02-01',
    ],
    'success'  => true,
    'data'     => [/* module-specific */],
    'metadata' => [
        'timestamp' => '2024-01-15T10:30:00+01:00',
        'provider'  => 'abraflexi',
    ],
]
```

## Data Providers

This package defines only the interface. Concrete providers live in their own packages:

| Provider | Package |
|---|---|
| `AbraFlexiDataProvider` | `vitexsoftware/abraflexi-digest` |
| `PohodaDataProvider` | `vitexsoftware/pohoda-digest` (planned) |

## Extending

### Create a custom module

```php
<?php declare(strict_types=1);

namespace YourApp\Analytics;

use VitexSoftware\DigestModules\Core\AbstractModule;
use VitexSoftware\DigestModules\Core\DataProviderInterface;

class PaidThisWeek extends AbstractModule
{
    protected string $moduleName = 'paid_this_week';
    protected string $heading    = 'Paid This Week';

    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        $invoices = $provider->getData(
            DataProviderInterface::ENTITY_OUTCOMING_INVOICES,
            [
                DataProviderInterface::FILTER_DATE_PERIOD     => [
                    'column' => DataProviderInterface::DATE_COLUMN_ISSUE_DATE,
                    'period' => $period,
                ],
                DataProviderInterface::FILTER_PAYMENT_STATUS  => DataProviderInterface::PAYMENT_STATUS_PAID,
                DataProviderInterface::FILTER_CANCELLED       => false,
                DataProviderInterface::FILTER_LIMIT           => 0,
            ],
        );

        $total = array_sum(array_column($invoices, DataProviderInterface::FIELD_TOTAL_AMOUNT));

        return $this->createResult($period, true, [
            'summary' => [
                'count'        => count($invoices),
                'total_amount' => $this->formatCurrency($total),
            ],
        ]);
    }
}
```

### Create a custom data provider

Implement `DataProviderInterface::getData()` — translate neutral `FILTER_*` conditions to system-specific queries, and return records keyed with `FIELD_*` constants:

```php
<?php declare(strict_types=1);

use VitexSoftware\DigestModules\Core\DataProviderInterface;

class MySystemDataProvider implements DataProviderInterface
{
    public function getData(string $entity, array $conditions = [], array $columns = []): array
    {
        // 1. Map ENTITY_* → your system's endpoint
        // 2. Map FILTER_* conditions → your query format
        // 3. Return records with FIELD_* keys

        return array_map(fn($raw) => [
            DataProviderInterface::FIELD_CODE         => $raw['number'],
            DataProviderInterface::FIELD_COMPANY      => $raw['client_name'],
            DataProviderInterface::FIELD_TOTAL_AMOUNT => (float) $raw['total'],
            DataProviderInterface::FIELD_CURRENCY     => $raw['currency'] ?? 'CZK',
            DataProviderInterface::FIELD_CANCELLED    => (bool) $raw['cancelled'],
            // ... other FIELD_* constants
        ], $this->fetchFromMySystem($entity, $conditions));
    }

    public function getSystemName(): string { return 'my_system'; }
    // ... implement remaining interface methods
}
```

## License

MIT

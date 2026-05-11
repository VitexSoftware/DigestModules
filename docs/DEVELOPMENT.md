# Development Guide — DigestModules

## Architecture

```
vitexsoftware/digest-modules          ← this package
  Core/DataProviderInterface.php      — neutral constants + contract
  Core/AbstractModule.php             — base class with helpers
  Core/ModuleInterface.php            — interface all modules implement
  Core/ModuleRunner.php               — orchestrates module execution
  Modules/*.php                       — ready-made analytics modules

vitexsoftware/abraflexi-digest        ← separate package
  Providers/AbraFlexiDataProvider.php — translates neutral schema → AbraFlexi WQL

vitexsoftware/pohoda-digest           ← separate package (planned)
  DataProvider/PohodaDataProvider.php — translates neutral schema → Pohoda API
```

The key rule: **this package must contain no AbraFlexi or Pohoda code**. Modules may only reference `DataProviderInterface::FILTER_*`, `FIELD_*`, `ENTITY_*`, and value constants.

---

## Writing a new module

### 1. Extend AbstractModule

```php
<?php declare(strict_types=1);

namespace VitexSoftware\DigestModules\Modules;

use VitexSoftware\DigestModules\Core\AbstractModule;
use VitexSoftware\DigestModules\Core\DataProviderInterface;

class PaidInvoices extends AbstractModule
{
    protected string $moduleName     = 'paid_invoices';
    protected string $heading        = 'Paid Invoices';
    protected string $description    = 'Invoices fully paid within the period';
    protected array  $requiredFeatures = ['date_filtering', 'payment_status'];

    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        try {
            $invoices = $provider->getData(
                DataProviderInterface::ENTITY_OUTCOMING_INVOICES,
                [
                    DataProviderInterface::FILTER_DATE_PERIOD    => [
                        'column' => DataProviderInterface::DATE_COLUMN_ISSUE_DATE,
                        'period' => $period,
                    ],
                    DataProviderInterface::FILTER_PAYMENT_STATUS => DataProviderInterface::PAYMENT_STATUS_PAID,
                    DataProviderInterface::FILTER_CANCELLED       => false,
                    DataProviderInterface::FILTER_LIMIT           => 0,
                ],
            );

            if (empty($invoices)) {
                return $this->createResult($period, true, [
                    'summary' => ['count' => 0, 'message' => 'No paid invoices found'],
                ]);
            }

            return $this->createResult($period, true, $this->analyze($invoices));

        } catch (\Throwable $e) {
            return $this->createResult($period, false, [], [
                'error' => ['message' => $e->getMessage(), 'type' => get_class($e)],
            ]);
        }
    }

    /** @param array<array<string, mixed>> $invoices */
    private function analyze(array $invoices): array
    {
        $totalsByCurrency = [];

        foreach ($invoices as $invoice) {
            $currency = (string) ($invoice[DataProviderInterface::FIELD_CURRENCY] ?? 'CZK');
            $amount   = $currency !== 'CZK'
                ? (float) ($invoice[DataProviderInterface::FIELD_TOTAL_AMOUNT_FOREIGN] ?? 0)
                : (float) ($invoice[DataProviderInterface::FIELD_TOTAL_AMOUNT] ?? 0);

            $totalsByCurrency[$currency] = ($totalsByCurrency[$currency] ?? 0.0) + $amount;
        }

        $formattedTotals = [];
        foreach ($totalsByCurrency as $currency => $total) {
            $formattedTotals[$currency] = $this->formatCurrency($total, $currency);
        }

        return [
            'summary' => ['count' => count($invoices), 'currencies' => array_keys($totalsByCurrency)],
            'totals_by_currency' => $formattedTotals,
        ];
    }
}
```

### 2. Rules for module code

- Only use `DataProviderInterface::FILTER_*` in conditions — never pass raw WQL, SQL, or system-specific strings.
- Only read `DataProviderInterface::FIELD_*` keys from returned records — never assume `kod`, `firma`, `datVyst`, etc.
- Wrap the entire `process()` body in `try/catch (\Throwable $e)` and return `createResult(..., false, ...)` on error.
- Use `$this->formatCurrency()` for monetary values so the output schema is consistent.
- Use `foreach` (not `array_map` with two arrays) when building associative results — `array_map` loses string keys.

### 3. Register and run

```php
$runner = new ModuleRunner($provider);
$runner->addModule('paid_invoices', new PaidInvoices());

$result = $runner->run($period);
```

---

## Writing a new data provider

Implement `DataProviderInterface`. The two most important obligations:

1. **Translate `FILTER_*` conditions** to your system's query format.
2. **Return records keyed with `FIELD_*` constants** — modules must not know your system's internal field names.

```php
<?php declare(strict_types=1);

namespace YourApp\DataProviders;

use VitexSoftware\DigestModules\Core\DataProviderInterface;

class MySystemDataProvider implements DataProviderInterface
{
    public function getData(string $entity, array $conditions = [], array $columns = []): array
    {
        $query = $this->buildQuery($entity, $conditions);
        $raw   = $this->executeQuery($query);

        return array_map([$this, 'normalizeRecord'], $raw);
    }

    private function buildQuery(string $entity, array $conditions): array
    {
        $q = ['entity' => $this->entityMap[$entity]];

        foreach ($conditions as $key => $value) {
            switch ($key) {
                case DataProviderInterface::FILTER_DATE_PERIOD:
                    $col   = $this->dateColumnMap[$value['column']] ?? 'created_at';
                    $start = $value['period']->getStartDate()->format('Y-m-d');
                    $end   = $value['period']->getEndDate()->format('Y-m-d');
                    $q['where'][] = "$col >= '$start' AND $col < '$end'";
                    break;

                case DataProviderInterface::FILTER_PAYMENT_STATUS:
                    if ($value === DataProviderInterface::PAYMENT_STATUS_UNPAID_OR_PARTIAL) {
                        $q['where'][] = "payment_status IN ('unpaid','partial')";
                    }
                    break;

                case DataProviderInterface::FILTER_CANCELLED:
                    $q['where'][] = 'cancelled = ' . ($value ? '1' : '0');
                    break;

                case DataProviderInterface::FILTER_LIMIT:
                    if ($value > 0) {
                        $q['limit'] = (int) $value;
                    }
                    break;
            }
        }

        return $q;
    }

    private function normalizeRecord(array $raw): array
    {
        return [
            DataProviderInterface::FIELD_CODE         => $raw['invoice_no'],
            DataProviderInterface::FIELD_COMPANY      => $raw['client_name'],
            DataProviderInterface::FIELD_DATE         => $raw['issue_date'],
            DataProviderInterface::FIELD_DUE_DATE     => $raw['due_date'],
            DataProviderInterface::FIELD_TOTAL_AMOUNT => (float) $raw['total'],
            DataProviderInterface::FIELD_CURRENCY     => $raw['currency'] ?? 'CZK',
            DataProviderInterface::FIELD_CANCELLED    => (bool) ($raw['cancelled'] ?? false),
            DataProviderInterface::FIELD_PAYMENT_STATUS => $this->normalizePaymentStatus($raw['status']),
        ];
    }

    private function normalizePaymentStatus(string $raw): string
    {
        return match ($raw) {
            'PAID'    => DataProviderInterface::PAYMENT_STATUS_PAID,
            'PARTIAL' => DataProviderInterface::PAYMENT_STATUS_PARTIAL,
            default   => DataProviderInterface::PAYMENT_STATUS_UNPAID,
        };
    }

    public function getSystemName(): string          { return 'my_system'; }
    public function getSupportedEntities(): array    { return array_keys($this->entityMap); }
    public function supportsFeature(string $f): bool { return in_array($f, $this->features, true); }
    public function getCompanyInfo(): array          { return ['name' => $this->fetchCompanyName()]; }
    public function formatDate(\DateTime $d): string { return $d->format('Y-m-d'); }

    public function formatDatePeriod(string $column, \DatePeriod $period): string
    {
        $col   = $this->dateColumnMap[$column] ?? $column;
        $start = $period->getStartDate()->format('Y-m-d');
        $end   = $period->getEndDate()->format('Y-m-d');
        return "$col >= '$start' AND $col < '$end'";
    }
}
```

---

## Testing

### Testing a module with a mock provider

```php
<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use VitexSoftware\DigestModules\Core\DataProviderInterface;
use VitexSoftware\DigestModules\Modules\PaidInvoices;

class PaidInvoicesTest extends TestCase
{
    private function makeProvider(array $returnData): DataProviderInterface
    {
        $mock = $this->createMock(DataProviderInterface::class);
        $mock->method('getData')->willReturn($returnData);
        $mock->method('getSystemName')->willReturn('test');
        $mock->method('supportsFeature')->willReturn(true);
        return $mock;
    }

    private function makePeriod(): \DatePeriod
    {
        return new \DatePeriod(
            new \DateTime('2024-01-01'),
            new \DateInterval('P1M'),
            new \DateTime('2024-02-01'),
        );
    }

    public function testCountsInvoices(): void
    {
        $invoices = [
            [
                DataProviderInterface::FIELD_CURRENCY     => 'CZK',
                DataProviderInterface::FIELD_TOTAL_AMOUNT => 1000.0,
                DataProviderInterface::FIELD_CANCELLED    => false,
            ],
            [
                DataProviderInterface::FIELD_CURRENCY     => 'CZK',
                DataProviderInterface::FIELD_TOTAL_AMOUNT => 2000.0,
                DataProviderInterface::FIELD_CANCELLED    => false,
            ],
        ];

        $result = (new PaidInvoices())->process($this->makeProvider($invoices), $this->makePeriod());

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['data']['summary']['count']);
        $this->assertSame(3000.0, $result['data']['totals_by_currency']['CZK']['amount']);
    }

    public function testEmptyProviderReturnsSuccess(): void
    {
        $result = (new PaidInvoices())->process($this->makeProvider([]), $this->makePeriod());

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['data']['summary']['count']);
    }

    public function testProviderExceptionReturnsFailed(): void
    {
        $mock = $this->createMock(DataProviderInterface::class);
        $mock->method('getData')->willThrowException(new \RuntimeException('timeout'));
        $mock->method('supportsFeature')->willReturn(true);

        $result = (new PaidInvoices())->process($mock, $this->makePeriod());

        $this->assertFalse($result['success']);
        $this->assertSame('timeout', $result['metadata']['error']['message']);
    }
}
```

### Testing a data provider

Test normalization of raw system records into neutral schema. Because most providers call live APIs, mock the HTTP/connection layer and assert that `FIELD_*` keys are present and correctly typed in the output.

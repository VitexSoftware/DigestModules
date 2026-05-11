# API Reference — DigestModules

## DataProviderInterface

All accounting system connectors must implement this interface (`VitexSoftware\DigestModules\Core\DataProviderInterface`).

### Entity constants

Used as the first argument to `getData()`.

| Constant | Value | Description |
|---|---|---|
| `ENTITY_OUTCOMING_INVOICES` | `outcoming_invoices` | Issued / outgoing invoices |
| `ENTITY_INCOMING_INVOICES` | `incoming_invoices` | Received / incoming invoices |
| `ENTITY_BANK_STATEMENTS` | `bank_statements` | Bank transactions (payments in and out) |
| `ENTITY_CONTACTS` | `contacts` | Address book — customers, suppliers |
| `ENTITY_PRODUCTS` | `products` | Product catalog / price list |
| `ENTITY_REMINDERS` | `reminders` | Payment reminders sent |
| `ENTITY_ORDERS` | `orders` | Received orders |

### Filter constants (`FILTER_*`)

Used as keys in the `$conditions` array passed to `getData()`.

| Constant | Value | Type | Description |
|---|---|---|---|
| `FILTER_DATE_PERIOD` | `date_period` | `['column' => DATE_COLUMN_*, 'period' => \DatePeriod]` | Date range filter |
| `FILTER_PAYMENT_DIRECTION` | `direction` | `DIRECTION_*` | Incoming or outgoing |
| `FILTER_PAYMENT_STATUS` | `payment_status` | `PAYMENT_STATUS_*` | Paid / unpaid / partial |
| `FILTER_CANCELLED` | `cancelled` | `bool` | Include cancelled documents |
| `FILTER_ACCOUNTED` | `accounted` | `bool` | Posted/accounted filter |
| `FILTER_MATCHED` | `matched` | `bool` | Payment matched to invoice |
| `FILTER_DOCUMENT_TYPE` | `document_type` | `DOCUMENT_TYPE_*` | Include only this type |
| `FILTER_EXCLUDE_DOCUMENT_TYPE` | `exclude_document_type` | `DOCUMENT_TYPE_*` | Exclude this type |
| `FILTER_MAIL_PENDING` | `mail_pending` | `bool true` | Email not yet sent |
| `FILTER_MISSING_EMAIL` | `missing_email` | `bool true` | Contact has no email |
| `FILTER_MISSING_PHONE` | `missing_phone` | `bool true` | Contact has no phone |
| `FILTER_WITH_ITEMS` | `with_items` | `bool true` | Include line items |
| `FILTER_OVERDUE` | `overdue` | `bool true` | Past due date only |
| `FILTER_HAS_BUY_PRICE` | `has_buy_price` | `bool true` | Product has buy price |
| `FILTER_HAS_SELL_PRICE` | `has_sell_price` | `bool true` | Product has sell price |
| `FILTER_RELATIONSHIP` | `relationship` | `customer` \| `supplier` | Contact role |
| `FILTER_LIMIT` | `limit` | `int` | Max records (0 = all) |

### Date column constants (`DATE_COLUMN_*`)

Used as `column` inside `FILTER_DATE_PERIOD`.

| Constant | Value |
|---|---|
| `DATE_COLUMN_ISSUE_DATE` | `issue_date` |
| `DATE_COLUMN_DUE_DATE` | `due_date` |
| `DATE_COLUMN_LAST_UPDATED` | `last_updated` |
| `DATE_COLUMN_FIRST_REMINDER` | `first_reminder_date` |

### Field constants (`FIELD_*`)

Keys present in records returned by `getData()`.

| Constant | Value | Type |
|---|---|---|
| `FIELD_CODE` | `code` | `string` |
| `FIELD_DATE` | `date` | `string` (Y-m-d) |
| `FIELD_DUE_DATE` | `due_date` | `string` (Y-m-d) |
| `FIELD_COMPANY` | `company` | `string` |
| `FIELD_TOTAL_AMOUNT` | `total_amount` | `float` |
| `FIELD_TOTAL_AMOUNT_FOREIGN` | `total_amount_foreign` | `float` |
| `FIELD_REMAINING_AMOUNT` | `remaining_amount` | `float` |
| `FIELD_REMAINING_AMOUNT_FOREIGN` | `remaining_amount_foreign` | `float` |
| `FIELD_DEPOSIT_AMOUNT` | `deposit_amount` | `float` |
| `FIELD_DEPOSIT_AMOUNT_FOREIGN` | `deposit_amount_foreign` | `float` |
| `FIELD_CURRENCY` | `currency` | `string` (e.g. `CZK`, `EUR`) |
| `FIELD_CANCELLED` | `cancelled` | `bool` |
| `FIELD_DOCUMENT_TYPE` | `document_type` | `string` (human-readable label) |
| `FIELD_PAYMENT_STATUS` | `payment_status` | `PAYMENT_STATUS_*` |
| `FIELD_MAIL_STATUS` | `mail_status` | `MAIL_STATUS_*` |
| `FIELD_DESCRIPTION` | `description` | `string` |
| `FIELD_CONTACT_EMAIL` | `contact_email` | `string` |
| `FIELD_DEDUCTION_STATUS` | `deduction_status` | `DEDUCTION_STATUS_*` |
| `FIELD_FIRST_REMINDER_DATE` | `first_reminder_date` | `string` (Y-m-d) |
| `FIELD_SECOND_REMINDER_DATE` | `second_reminder_date` | `string` (Y-m-d) |
| `FIELD_PRE_LITIGATION_DATE` | `pre_litigation_date` | `string` (Y-m-d) |
| `FIELD_ITEMS` | `items` | `array` of line items |
| `FIELD_DIRECTION` | `direction` | `DIRECTION_*` |
| `FIELD_BANK_ACCOUNT` | `bank_account` | `string` |
| `FIELD_ACCOUNTED` | `accounted` | `bool` |
| `FIELD_MATCHED` | `matched` | `bool` |
| `FIELD_NAME` | `name` | `string` (contact name) |
| `FIELD_EMAIL` | `email` | `string` |
| `FIELD_PHONE` | `phone` | `string` |
| `FIELD_STREET` | `street` | `string` |
| `FIELD_CITY` | `city` | `string` |
| `FIELD_BUY_PRICE` | `buy_price` | `float` |
| `FIELD_SELL_PRICE` | `sell_price` | `float` |

### Value constants

**Payment status:**

| Constant | Value |
|---|---|
| `PAYMENT_STATUS_UNPAID` | `unpaid` |
| `PAYMENT_STATUS_PARTIAL` | `partial` |
| `PAYMENT_STATUS_PAID` | `paid` |
| `PAYMENT_STATUS_UNPAID_OR_PARTIAL` | `unpaid_or_partial` |

**Mail status:**

| Constant | Value |
|---|---|
| `MAIL_STATUS_SENT` | `sent` |
| `MAIL_STATUS_PENDING` | `pending` |
| `MAIL_STATUS_EMPTY` | `empty` |

**Document type:**

| Constant | Value |
|---|---|
| `DOCUMENT_TYPE_INVOICE` | `invoice` |
| `DOCUMENT_TYPE_PROFORMA` | `proforma` |
| `DOCUMENT_TYPE_CREDIT_NOTE` | `credit_note` |

**Deduction status:**

| Constant | Value |
|---|---|
| `DEDUCTION_STATUS_NONE` | `none` |
| `DEDUCTION_STATUS_PARTIAL` | `partial` |
| `DEDUCTION_STATUS_COMPLETE` | `complete` |
| `DEDUCTION_STATUS_TAX_DOCUMENT_CREATED` | `tax_document_created` |

**Direction:**

| Constant | Value |
|---|---|
| `DIRECTION_INCOMING` | `incoming` |
| `DIRECTION_OUTGOING` | `outgoing` |

### Interface methods

```php
public function getData(string $entity, array $conditions = [], array $columns = []): array;
public function getSystemName(): string;
public function getSupportedEntities(): array;
public function supportsFeature(string $feature): bool;
public function getCompanyInfo(): array;
public function formatDate(\DateTime $date): string;
public function formatDatePeriod(string $column, \DatePeriod $period): string;
```

---

## AbstractModule

Base class for all modules. Extend this instead of implementing `ModuleInterface` directly.

```php
abstract class AbstractModule implements ModuleInterface
{
    protected string $moduleName;
    protected string $heading;
    protected string $description;
    protected array  $requiredFeatures = [];

    abstract public function process(DataProviderInterface $provider, \DatePeriod $period): array;
}
```

### Protected helpers

**`createResult()`** — builds the standard result envelope:

```php
protected function createResult(
    \DatePeriod $period,
    bool $success,
    array $data = [],
    array $metadata = []
): array
```

**`formatCurrency()`** — returns a consistent currency array:

```php
protected function formatCurrency(float $amount, string $currency = 'CZK'): array
// returns: ['amount' => 125.50, 'currency' => 'CZK', 'formatted' => '125,50 CZK']
```

**`calculatePercentage()`**:

```php
protected function calculatePercentage(float $value, float $total): float
```

---

## ModuleRunner

Orchestrates execution of multiple modules.

```php
$runner = new ModuleRunner(DataProviderInterface $provider);

$runner->addModule(string $key, ModuleInterface $module): static;
$runner->getModules(): array;
$runner->getDataProvider(): DataProviderInterface;
$runner->run(\DatePeriod $period): array;
```

`run()` returns:

```php
[
    'digest' => [
        'provider' => 'abraflexi',
        'company'  => ['name' => '...'],
        'period'   => ['start' => 'Y-m-d', 'end' => 'Y-m-d'],
        'generated_at' => '...',
    ],
    'modules' => [
        'outcoming_invoices' => [ /* createResult() array */ ],
        // ...
    ],
    'benchmarks' => [
        'outcoming_invoices' => ['duration' => 0.123],
        // ...
    ],
]
```

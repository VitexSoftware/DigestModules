# Development Guide - Creating Custom Modules

## Quick Start Guide

### 1. Create a Custom Module

```php
<?php declare(strict_types=1);

namespace YourApp\Analytics;

use VitexSoftware\DigestModules\Core\AbstractModule;
use VitexSoftware\DigestModules\Core\DataProviderInterface;

class CustomSalesModule extends AbstractModule
{
    protected string $moduleName = 'custom_sales';
    protected string $heading = 'Sales Performance Analysis';

    public function process(DataProviderInterface $provider): array
    {
        // Validate provider supports required data
        $this->validateProvider($provider);
        
        // Get data from accounting system
        $invoices = $provider->getInvoices();
        $customers = $provider->getCustomers();
        
        // Perform your analytics
        $analysis = $this->analyzeSalesData($invoices, $customers);
        
        // Return standardized format
        return [
            'summary' => [
                'total_sales' => $analysis['total'],
                'customer_count' => count($customers),
                'average_order' => $analysis['average'],
                'processing_time' => $this->getProcessingTime()
            ],
            'details' => $analysis['breakdown'],
            'metadata' => $this->getMetadata()
        ];
    }

    protected function validateProvider(DataProviderInterface $provider): void
    {
        if (!$provider->isAvailable()) {
            throw new \RuntimeException('Accounting system is not available');
        }
        
        // Add any specific validation your module needs
        if (!method_exists($provider, 'getInvoices')) {
            throw new \InvalidArgumentException('Provider must support invoice data');
        }
    }

    private function analyzeSalesData(array $invoices, array $customers): array
    {
        $total = 0;
        $breakdown = [];
        
        foreach ($invoices as $invoice) {
            $total += $invoice['amount'];
            
            $breakdown[] = [
                'invoice_id' => $invoice['id'],
                'customer' => $invoice['customer_name'],
                'amount' => $invoice['amount'],
                'date' => $invoice['date']
            ];
        }
        
        return [
            'total' => $total,
            'average' => count($invoices) > 0 ? $total / count($invoices) : 0,
            'breakdown' => $breakdown
        ];
    }
}
```

### 2. Register Your Module

```php
<?php
use VitexSoftware\DigestModules\Core\ModuleRunner;
use YourApp\Analytics\CustomSalesModule;

$runner = new ModuleRunner($dataProvider);

// Register your custom module
$runner->registerModule(new CustomSalesModule());

// Now you can use it
$salesData = $runner->runModule('custom_sales');
```

### 3. Create a Custom Data Provider

```php
<?php declare(strict_types=1);

namespace YourApp\DataProviders;

use VitexSoftware\DigestModules\Core\DataProviderInterface;

class CustomSystemDataProvider implements DataProviderInterface
{
    private string $apiUrl;
    private string $apiKey;
    private ?\PDO $connection = null;

    public function __construct(string $apiUrl, string $apiKey)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->makeApiCall('/health');
            return $response['status'] === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getInvoices(): array
    {
        $response = $this->makeApiCall('/invoices');
        
        // Normalize data to standard format
        return array_map([$this, 'normalizeInvoice'], $response['data']);
    }

    public function getCustomers(): array
    {
        $response = $this->makeApiCall('/customers');
        
        return array_map([$this, 'normalizeCustomer'], $response['data']);
    }

    public function getSystemInfo(): array
    {
        return [
            'system' => 'Custom Accounting System',
            'version' => $this->getSystemVersion(),
            'provider' => static::class,
            'api_url' => $this->apiUrl
        ];
    }

    private function makeApiCall(string $endpoint): array
    {
        $url = $this->apiUrl . $endpoint;
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Accept: application/json'
                ]
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new \RuntimeException("Failed to fetch data from $endpoint");
        }
        
        return json_decode($response, true);
    }

    private function normalizeInvoice(array $rawInvoice): array
    {
        return [
            'id' => $rawInvoice['invoice_id'],
            'number' => $rawInvoice['invoice_number'],
            'customer_name' => $rawInvoice['client_name'],
            'amount' => (float) $rawInvoice['total_amount'],
            'currency' => $rawInvoice['currency'] ?? 'USD',
            'date' => $rawInvoice['invoice_date'],
            'due_date' => $rawInvoice['due_date'],
            'status' => $this->normalizeStatus($rawInvoice['status'])
        ];
    }

    private function normalizeCustomer(array $rawCustomer): array
    {
        return [
            'id' => $rawCustomer['customer_id'],
            'name' => $rawCustomer['company_name'],
            'email' => $rawCustomer['contact_email'],
            'phone' => $rawCustomer['phone_number'] ?? null
        ];
    }

    private function normalizeStatus(string $rawStatus): string
    {
        $statusMap = [
            'PAID' => 'paid',
            'UNPAID' => 'unpaid', 
            'OVERDUE' => 'overdue',
            'CANCELLED' => 'cancelled'
        ];
        
        return $statusMap[$rawStatus] ?? 'unknown';
    }

    private function getSystemVersion(): string
    {
        try {
            $response = $this->makeApiCall('/version');
            return $response['version'] ?? 'unknown';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }
}
```

## Best Practices

### 1. Error Handling

Always handle errors gracefully and provide meaningful error information:

```php
public function process(DataProviderInterface $provider): array
{
    try {
        $this->validateProvider($provider);
        $data = $provider->getInvoices();
        
        if (empty($data)) {
            return $this->createEmptyResult('No invoices found');
        }
        
        return $this->processData($data);
        
    } catch (\Exception $e) {
        return $this->createErrorResult($e);
    }
}

private function createEmptyResult(string $message): array
{
    return [
        'summary' => ['count' => 0, 'message' => $message],
        'details' => [],
        'metadata' => $this->getMetadata()
    ];
}

private function createErrorResult(\Exception $e): array
{
    return [
        'error' => [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'type' => get_class($e)
        ],
        'metadata' => $this->getMetadata()
    ];
}
```

### 2. Performance Optimization

```php
// Use caching for expensive operations
private function getCachedData(string $cacheKey, callable $dataFetcher): array
{
    if ($this->cache && $this->cache->has($cacheKey)) {
        return $this->cache->get($cacheKey);
    }
    
    $data = $dataFetcher();
    
    if ($this->cache) {
        $this->cache->set($cacheKey, $data, 3600); // 1 hour
    }
    
    return $data;
}

// Batch operations when possible
private function processInvoicesInBatches(array $invoices): array
{
    $batchSize = 100;
    $results = [];
    
    foreach (array_chunk($invoices, $batchSize) as $batch) {
        $batchResults = $this->processBatch($batch);
        $results = array_merge($results, $batchResults);
    }
    
    return $results;
}
```

### 3. Testing Your Module

```php
<?php
use PHPUnit\Framework\TestCase;
use YourApp\Analytics\CustomSalesModule;

class CustomSalesModuleTest extends TestCase
{
    private $mockProvider;
    private $module;

    protected function setUp(): void
    {
        $this->mockProvider = $this->createMock(DataProviderInterface::class);
        $this->module = new CustomSalesModule();
    }

    public function testProcessReturnsValidFormat(): void
    {
        // Arrange
        $mockInvoices = [
            ['id' => 1, 'amount' => 1000, 'customer_name' => 'Test Corp'],
            ['id' => 2, 'amount' => 2000, 'customer_name' => 'Demo Ltd']
        ];
        
        $this->mockProvider->method('isAvailable')->willReturn(true);
        $this->mockProvider->method('getInvoices')->willReturn($mockInvoices);
        $this->mockProvider->method('getCustomers')->willReturn([]);

        // Act
        $result = $this->module->process($this->mockProvider);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('details', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals(3000, $result['summary']['total_sales']);
    }

    public function testHandlesUnavailableProvider(): void
    {
        // Arrange
        $this->mockProvider->method('isAvailable')->willReturn(false);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->module->process($this->mockProvider);
    }
}
```

## Integration Examples

### With Caching

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$cache = new FilesystemAdapter();
$dataProvider = new CachedDataProvider($realProvider, $cache);
$runner = new ModuleRunner($dataProvider);
```

### With Multiple Providers

```php
$providers = [
    'abraflexi' => new AbraFlexiDataProvider($config1),
    'pohoda' => new PohodaDataProvider($config2),
    'custom' => new CustomSystemDataProvider($config3)
];

$allResults = [];
foreach ($providers as $name => $provider) {
    if ($provider->isAvailable()) {
        $runner = new ModuleRunner($provider);
        $allResults[$name] = $runner->runModule('outcoming_invoices');
    }
}
```

### With Error Aggregation

```php
$modules = ['outcoming_invoices', 'debtors', 'custom_sales'];
$results = [];
$errors = [];

foreach ($modules as $moduleName) {
    try {
        $result = $runner->runModule($moduleName);
        
        if (isset($result['error'])) {
            $errors[$moduleName] = $result['error'];
        } else {
            $results[$moduleName] = $result;
        }
    } catch (\Exception $e) {
        $errors[$moduleName] = [
            'message' => $e->getMessage(),
            'type' => get_class($e)
        ];
    }
}

// Generate summary report
$summary = [
    'successful_modules' => count($results),
    'failed_modules' => count($errors),
    'total_modules' => count($modules),
    'results' => $results,
    'errors' => $errors
];
```
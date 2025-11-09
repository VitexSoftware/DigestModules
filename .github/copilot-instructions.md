---
description: DigestModules library for modular accounting analytics
applyTo: '**'
---

# DigestModules Library - Copilot Instructions

## Project Overview
DigestModules is a **modular analytics library** for accounting systems that provides:
- **Data Collection**: Modules that return structured JSON data for analysis
- **System-Agnostic**: Works with AbraFlexi, Pohoda, and other accounting systems
- **Standardized Interface**: Consistent module contracts for analytics development
- **Rapid Development**: Abstract base classes for quick module creation
- **JSON-Only Output**: Pure data layer - no HTML rendering (use DigestRenderer for that)

## 🏗️ Core Architecture
This library implements a **plugin-style architecture** where:
- **Modules** = Analytics plugins that process accounting data
- **Data Providers** = Abstraction layer for different accounting systems
- **ModuleRunner** = Orchestrates module execution with data providers

## 📋 Key Interfaces & Components

### Core Contracts (`src/Core/`)
- **ModuleInterface**: Contract for all analytics modules
  - `getName()`: Return module identifier (e.g., 'outcoming_invoices')  
  - `getHeading()`: Return human-readable title
  - `process(DataProviderInterface $provider)`: Main analytics logic
  - `validateProvider()`: Check if provider supports required data

- **DataProviderInterface**: Contract for accounting system connections
  - `getInvoices()`: Retrieve invoice data
  - `getCustomers()`: Retrieve customer/client data
  - `isAvailable()`: Check system connectivity
  - `getSystemInfo()`: Return system metadata

- **AbstractModule**: Base implementation with common functionality
  - Handles timing, error management, result formatting
  - Provides helper methods for data validation
  - Implements consistent JSON output structure

### Built-in Analytics Modules (`src/Modules/`)
- **OutcomingInvoices**: Invoice analysis with currency breakdown, totals
- **Debtors**: Overdue receivables analysis with aging reports
- **Custom modules**: Follow established patterns for consistency

### Data Provider Implementations (`src/Providers/`)
- **AbraFlexiDataProvider**: Integration with AbraFlexi system via REST API
- **Future providers**: Implement for Pohoda, other accounting systems

## 🔧 Development Guidelines

### Coding Standards
- **PHP 8.1+**: Use strict types: `declare(strict_types=1);`
- **PSR-4**: Follow autoloading standards with proper namespacing
- **Error Handling**: Comprehensive try-catch with meaningful messages
- **Documentation**: PHPDoc blocks for all public methods and classes
- **Testing**: PHPUnit tests for all modules and providers

### Module Development Pattern
```php
<?php declare(strict_types=1);

namespace VitexSoftware\DigestModules\Modules;

use VitexSoftware\DigestModules\Core\AbstractModule;
use VitexSoftware\DigestModules\Core\DataProviderInterface;

class CustomModule extends AbstractModule
{
    protected string $moduleName = 'custom_analysis';
    protected string $heading = 'Custom Business Analysis';

    public function process(DataProviderInterface $provider): array
    {
        $this->validateProvider($provider);
        
        // Your analytics logic here
        $data = $provider->getCustomData();
        $analysis = $this->analyzeData($data);
        
        return [
            'summary' => $analysis['totals'],
            'details' => $analysis['breakdown'],
            'metadata' => $this->getMetadata()
        ];
    }

    protected function validateProvider(DataProviderInterface $provider): void
    {
        if (!$provider->supportsCustomData()) {
            throw new \InvalidArgumentException('Provider does not support custom data');
        }
    }
}
```

### Data Provider Pattern
```php
<?php declare(strict_types=1);

namespace VitexSoftware\DigestModules\Providers;

use VitexSoftware\DigestModules\Core\DataProviderInterface;

class CustomSystemDataProvider implements DataProviderInterface
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct(string $apiUrl, string $apiKey)
    {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
    }

    public function isAvailable(): bool
    {
        // Test connection to accounting system
        return $this->testConnection();
    }

    public function getInvoices(): array
    {
        // Fetch and return invoice data
        return $this->apiCall('/invoices');
    }

    public function getCustomers(): array
    {
        // Fetch and return customer data
        return $this->apiCall('/customers');
    }

    public function getSystemInfo(): array
    {
        return [
            'system' => 'Custom Accounting System',
            'version' => $this->getSystemVersion(),
            'provider' => static::class
        ];
    }
}
```

## 📊 Standard JSON Output Format
All modules must return data in this standardized structure:

```json
{
    "module": "module_identifier",
    "heading": "Human Readable Title", 
    "summary": {
        "total_amount": 125000.50,
        "currency": "CZK",
        "count": 45,
        "processing_time": 0.234
    },
    "details": [
        {
            "item": "Detail item",
            "value": 1234.56,
            "metadata": {"key": "value"}
        }
    ],
    "metadata": {
        "generated_at": "2024-12-23T10:30:45+01:00",
        "provider": "AbraFlexiDataProvider",
        "system_info": {"version": "2023.1"},
        "cache_used": false
    }
}
```

## 🔄 Usage Examples

### Basic Module Execution
```php
use VitexSoftware\DigestModules\ModuleRunner;
use VitexSoftware\DigestModules\Providers\AbraFlexiDataProvider;

// Initialize data provider
$provider = new AbraFlexiDataProvider('https://demo.abraflexi.eu', 'demo', 'demo');

// Run analytics module
$runner = new ModuleRunner($provider);
$result = $runner->runModule('outcoming_invoices');

// Result is JSON-ready array
echo json_encode($result, JSON_PRETTY_PRINT);
```

### Multiple Module Execution  
```php
$modules = ['outcoming_invoices', 'debtors', 'custom_analysis'];
$results = [];

foreach ($modules as $moduleName) {
    try {
        $results[$moduleName] = $runner->runModule($moduleName);
    } catch (\Exception $e) {
        $results[$moduleName] = ['error' => $e->getMessage()];
    }
}
```

## 🚀 Integration Guidelines

### With DigestRenderer (HTML Output)
```php
// DigestModules handles data collection
$analyticsData = $runner->runModule('outcoming_invoices');

// DigestRenderer handles HTML presentation  
$renderer = new \VitexSoftware\DigestRenderer\BootstrapTheme();
$html = $renderer->renderModule($analyticsData);
```

### With Caching Systems
```php
// Implement caching in your data provider
class CachedDataProvider implements DataProviderInterface 
{
    private DataProviderInterface $realProvider;
    private CacheInterface $cache;

    public function getInvoices(): array
    {
        $cacheKey = 'invoices_' . date('Y-m-d');
        
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }
        
        $data = $this->realProvider->getInvoices();
        $this->cache->set($cacheKey, $data, 3600); // 1 hour
        
        return $data;
    }
}
```

## ⚠️ Important Notes for Copilot

1. **Pure Data Layer**: This library NEVER generates HTML - only JSON data
2. **Provider Validation**: Always check provider capabilities before processing
3. **Error Handling**: Modules should gracefully handle provider failures
4. **Performance**: Include timing metadata for performance monitoring
5. **Extensibility**: New modules should follow existing patterns
6. **Dependencies**: Minimize external dependencies to maintain flexibility

When working with this codebase:
- Always implement both interfaces when creating new components
- Use the AbstractModule base class for common functionality
- Follow the standardized JSON output format
- Include comprehensive error handling and validation
- Add PHPUnit tests for new modules and providers
    protected string $description = 'Description of analysis';
    
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        // Implementation with error handling
        // Return structured JSON data
    }
}
```

## Data Structure Standards
Return format:
```json
{
    "module_name": "string",
    "heading": "string", 
    "description": "string",
    "period": {"start": "date", "end": "date"},
    "success": true,
    "data": {}, // Analysis results
    "metadata": {} // Processing info
}
```

## Testing
- Use mock data providers for testing
- Test both successful and error scenarios
- Validate JSON output structure
- Check processing performance

## Integration
This library works with:
- **DigestRenderer**: For HTML output generation
- **AbraFlexi-Digest**: Legacy integration
- **Pohoda-Digest**: Pohoda system integration
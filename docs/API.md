# API Documentation - DigestModules

## Core Interfaces

### ModuleInterface

All analytics modules must implement this interface.

```php
<?php
namespace VitexSoftware\DigestModules\Core;

interface ModuleInterface 
{
    /**
     * Get module identifier (e.g., 'outcoming_invoices', 'debtors')
     */
    public function getName(): string;
    
    /**
     * Get human-readable module title
     */
    public function getHeading(): string;
    
    /**
     * Process analytics and return JSON data
     * 
     * @param DataProviderInterface $provider Data source
     * @return array JSON-serializable array with analytics results
     * @throws \Exception When provider is incompatible or processing fails
     */
    public function process(DataProviderInterface $provider): array;
}
```

### DataProviderInterface

All accounting system connectors must implement this interface.

```php
<?php
namespace VitexSoftware\DigestModules\Core;

interface DataProviderInterface 
{
    /**
     * Test if the accounting system is available
     */
    public function isAvailable(): bool;
    
    /**
     * Get invoice data from the system
     * @return array Array of invoice objects
     */
    public function getInvoices(): array;
    
    /**
     * Get customer/client data from the system  
     * @return array Array of customer objects
     */
    public function getCustomers(): array;
    
    /**
     * Get system information and metadata
     * @return array System details (version, name, etc.)
     */
    public function getSystemInfo(): array;
}
```

## Built-in Classes

### ModuleRunner

Main orchestrator for running analytics modules.

```php
<?php
use VitexSoftware\DigestModules\Core\ModuleRunner;

$runner = new ModuleRunner($dataProvider);

// Run single module
$result = $runner->runModule('outcoming_invoices');

// Run multiple modules
$modules = ['outcoming_invoices', 'debtors'];
foreach ($modules as $moduleName) {
    $results[$moduleName] = $runner->runModule($moduleName);
}

// Get available modules
$availableModules = $runner->getAvailableModules();
```

### AbraFlexiDataProvider  

Data provider for AbraFlexi accounting system.

```php
<?php
use VitexSoftware\DigestModules\Providers\AbraFlexiDataProvider;

// Initialize with connection details
$provider = new AbraFlexiDataProvider(
    'https://demo.abraflexi.eu',  // Server URL
    'demo',                        // Username  
    'demo',                        // Password
    'demo'                         // Company database
);

// Test connection
if ($provider->isAvailable()) {
    $invoices = $provider->getInvoices();
    $customers = $provider->getCustomers();
    $systemInfo = $provider->getSystemInfo();
}
```

## Standard JSON Output Format

All modules return data in this standardized structure:

```json
{
    "module": "string",           // Module identifier
    "heading": "string",          // Human-readable title
    "summary": {                  // High-level aggregated data
        "total_amount": "number",
        "currency": "string", 
        "count": "number",
        "processing_time": "number"
    },
    "details": [                  // Detailed breakdown array
        {
            "item": "string",
            "value": "number",
            "metadata": "object"
        }
    ],
    "metadata": {                 // Technical metadata
        "generated_at": "string",  // ISO 8601 timestamp
        "provider": "string",      // Provider class name
        "system_info": "object",   // System details
        "cache_used": "boolean"
    }
}
```

## Module Examples

### OutcomingInvoices Module Output

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
            "customer": "ACME Corporation",
            "invoice_number": "2024001",
            "amount": 25000.00,
            "currency": "CZK", 
            "due_date": "2024-12-31",
            "status": "paid",
            "days_overdue": 0
        },
        {
            "customer": "Beta Ltd",
            "invoice_number": "2024002", 
            "amount": 15000.00,
            "currency": "CZK",
            "due_date": "2024-12-15",
            "status": "overdue",
            "days_overdue": 8
        }
    ],
    "metadata": {
        "generated_at": "2024-12-23T10:30:45+01:00",
        "provider": "AbraFlexiDataProvider",
        "system_info": {
            "system": "AbraFlexi",
            "version": "2023.1",
            "company": "Demo Company"
        },
        "cache_used": false
    }
}
```

### Debtors Module Output

```json
{
    "module": "debtors",
    "heading": "Overdue Receivables Analysis", 
    "summary": {
        "total_overdue": 85000.00,
        "currency": "CZK",
        "count": 12,
        "average_days_overdue": 22.5,
        "processing_time": 0.156
    },
    "details": [
        {
            "customer": "Problem Client Ltd",
            "total_overdue": 50000.00,
            "oldest_invoice_date": "2024-10-15",
            "days_overdue": 45,
            "invoice_count": 3,
            "contact_email": "accounting@problemclient.com"
        },
        {
            "customer": "Slow Payer Inc", 
            "total_overdue": 35000.00,
            "oldest_invoice_date": "2024-11-01",
            "days_overdue": 15,
            "invoice_count": 2,
            "contact_email": "finance@slowpayer.com"
        }
    ],
    "metadata": {
        "generated_at": "2024-12-23T10:30:45+01:00",
        "provider": "AbraFlexiDataProvider",
        "system_info": {
            "system": "AbraFlexi", 
            "version": "2023.1",
            "company": "Demo Company"
        },
        "cache_used": false
    }
}
```

## Error Handling

Modules should handle errors gracefully and return error information:

```json
{
    "module": "custom_module",
    "heading": "Custom Analysis",
    "error": {
        "message": "Data provider does not support required functionality",
        "code": "PROVIDER_INCOMPATIBLE",
        "details": "Missing getCustomData() method"
    },
    "metadata": {
        "generated_at": "2024-12-23T10:30:45+01:00",
        "provider": "UnsupportedProvider",
        "processing_time": 0.001
    }
}
```
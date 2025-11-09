---
description: DigestModules library for modular accounting analytics
applyTo: '**'
---

# DigestModules Library - Copilot Instructions

## Project Overview
DigestModules is a modular analytics library for accounting systems that provides:
- Data collection modules that return structured JSON data
- System-agnostic data providers (AbraFlexi, Pohoda, etc.)
- Standardized module interfaces for consistent analytics
- Abstract base classes for rapid module development

## Architecture Guidelines
- **ModuleInterface**: All modules must implement this interface
- **DataProviderInterface**: All data sources must implement this interface  
- **AbstractModule**: Extend this for common functionality
- **JSON Output**: Modules return associative arrays (JSON-serializable)
- **No HTML**: This library focuses only on data collection, not presentation

## Development Best Practices
- Use strict types: `declare(strict_types=1);`
- Follow PSR-4 autoloading standards
- Implement comprehensive error handling
- Return structured data with metadata
- Include processing benchmarks and timestamps
- Validate data provider capabilities before processing

## Key Components
1. **Core Interfaces** (`src/Core/`):
   - ModuleInterface: Contract for all analytics modules
   - DataProviderInterface: Contract for data source connections
   - AbstractModule: Base implementation with common methods

2. **Modules** (`src/Modules/`):
   - OutcomingInvoices: Invoice analysis with currency breakdown
   - Debtors: Overdue receivables analysis
   - Custom modules: Follow the established patterns

3. **Data Providers** (`src/Providers/`):
   - AbraFlexiDataProvider: AbraFlexi system integration
   - Additional providers: Implement DataProviderInterface

## Module Development Pattern
```php
class CustomModule extends AbstractModule
{
    protected string $moduleName = 'custom_analysis';
    protected string $heading = 'Custom Analysis';
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
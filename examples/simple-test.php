<?php

declare(strict_types=1);

/**
 * Simple test demonstrating the new modular structure
 */

// Mock autoloading for this example
spl_autoload_register(function ($class) {
    $paths = [
        'VitexSoftware\\DigestModules\\' => '/home/vitex/Projects/VitexSoftware/DigestModules/src/',
        'VitexSoftware\\DigestRenderer\\' => '/home/vitex/Projects/VitexSoftware/DigestRenderer/src/',
    ];
    
    foreach ($paths as $prefix => $baseDir) {
        if (strpos($class, $prefix) === 0) {
            $relativeClass = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});

use VitexSoftware\DigestModules\Core\ModuleRunner;
use VitexSoftware\DigestModules\Modules\OutcomingInvoices;
use VitexSoftware\DigestModules\Modules\Debtors;
use VitexSoftware\DigestRenderer\DigestRenderer;

echo "=== DigestModules Test with Mock Data ===\n\n";

// Create mock data provider
$mockProvider = new class implements VitexSoftware\DigestModules\Core\DataProviderInterface {
    public function getData(string $entity, array $conditions = [], array $columns = []): array
    {
        switch ($entity) {
            case 'outcoming_invoices':
                return [
                    [
                        'kod' => 'INV001',
                        'typDokl' => 'FAKTURA',
                        'sumCelkem' => 25000.00,
                        'sumCelkemMen' => 1000.00,
                        'sumZalohy' => 0.00,
                        'sumZalohyMen' => 0.00,
                        'storno' => 'false',
                        'mena' => 'CZK',
                    ],
                    [
                        'kod' => 'INV002', 
                        'typDokl' => 'FAKTURA',
                        'sumCelkem' => 15000.00,
                        'sumCelkemMen' => 600.00,
                        'sumZalohy' => 0.00,
                        'sumZalohyMen' => 0.00,
                        'storno' => 'false',
                        'mena' => 'CZK',
                    ],
                    [
                        'kod' => 'INV003',
                        'typDokl' => 'DOBROPIS',
                        'sumCelkem' => -3000.00,
                        'sumCelkemMen' => -120.00,
                        'sumZalohy' => 0.00,
                        'sumZalohyMen' => 0.00,
                        'storno' => 'false',
                        'mena' => 'CZK',
                    ],
                ];
            default:
                return [];
        }
    }

    public function getSystemName(): string { return 'mock'; }
    public function getSupportedEntities(): array { return ['outcoming_invoices']; }
    public function supportsFeature(string $feature): bool { return true; }
    public function getCompanyInfo(): array { 
        return [
            'name' => 'Demo Company Ltd.',
            'system' => 'Mock System',
        ];
    }
    public function formatDate(\DateTime $date): string { return $date->format('Y-m-d'); }
    public function formatDatePeriod(string $column, \DatePeriod $period): string { 
        return $column . ' between ' . $this->formatDate($period->getStartDate()) . ' and ' . $this->formatDate($period->getEndDate());
    }
};

// Create module runner
$runner = new ModuleRunner($mockProvider);

// Add modules
$runner->addModule('outcoming_invoices', OutcomingInvoices::class);

// Create test period
$period = new DatePeriod(
    new DateTime('2024-01-01'),
    new DateInterval('P1M'),
    new DateTime('2024-02-01')
);

echo "1. Running data collection modules...\n";
$results = $runner->run($period);

echo "2. JSON Output:\n";
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "3. Rendering HTML...\n";
$renderer = new DigestRenderer();
$html = $renderer->render($results);

echo "4. HTML Output (first 500 characters):\n";
echo substr($html, 0, 500) . "...\n\n";

echo "=== Test completed successfully ===\n";
#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI test script for DigestModules
 * 
 * Demonstrates JSON output from data collection modules
 */

require_once __DIR__ . '/../../DigestModules/vendor/autoload.php';
require_once __DIR__ . '/../../DigestRenderer/vendor/autoload.php';

use VitexSoftware\DigestModules\Core\ModuleRunner;
use VitexSoftware\DigestModules\Providers\AbraFlexiDataProvider;
use VitexSoftware\DigestModules\Modules\OutcomingInvoices;
use VitexSoftware\DigestModules\Modules\Debtors;
use VitexSoftware\DigestRenderer\DigestRenderer;

echo "=== DigestModules & DigestRenderer Test ===\n\n";

try {
    // Create data provider (mock for demonstration)
    echo "1. Creating AbraFlexi data provider...\n";
    $dataProvider = new AbraFlexiDataProvider([
        'url' => 'https://demo.abraflexi.cz:5434',
        'company' => 'demo',
        'username' => 'admin',
        'password' => 'admin',
    ]);

    // Create module runner
    echo "2. Setting up module runner...\n";
    $runner = new ModuleRunner($dataProvider);

    // Add modules
    $runner->addModule('outcoming_invoices', OutcomingInvoices::class);
    $runner->addModule('debtors', Debtors::class);

    // Define period (last month)
    $endDate = new DateTime('first day of this month');
    $startDate = new DateTime('first day of last month');
    $period = new DatePeriod($startDate, new DateInterval('P1M'), $endDate);

    echo "3. Processing data for period: " . $startDate->format('Y-m-d') . " to " . $endDate->format('Y-m-d') . "\n";
    
    // Run modules (this would normally fetch real data)
    $results = $runner->run($period);

    echo "4. Data collection completed. Generating outputs...\n\n";

    // Output JSON
    echo "=== JSON OUTPUT ===\n";
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    // Create HTML renderer
    echo "=== HTML RENDERING ===\n";
    $renderer = new DigestRenderer();
    
    // Render with Bootstrap theme
    echo "Rendering with Bootstrap theme...\n";
    $renderer->setTheme('bootstrap');
    $htmlBootstrap = $renderer->render($results);
    file_put_contents(__DIR__ . '/test_output_bootstrap.html', $htmlBootstrap);
    echo "Bootstrap HTML saved to: test_output_bootstrap.html\n";

    // Render with Email theme
    echo "Rendering with Email theme...\n";
    $renderer->setTheme('email');
    $htmlEmail = $renderer->render($results);
    file_put_contents(__DIR__ . '/test_output_email.html', $htmlEmail);
    echo "Email HTML saved to: test_output_email.html\n";

    echo "\n=== TEST COMPLETED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Mock data for demonstration when AbraFlexi is not available
 */
class MockAbraFlexiDataProvider extends VitexSoftware\DigestModules\Providers\AbraFlexiDataProvider
{
    public function getData(string $entity, array $conditions = [], array $columns = []): array
    {
        // Return mock data based on entity type
        switch ($entity) {
            case 'outcoming_invoices':
                return $this->getMockInvoiceData();
                
            case 'customers':
                return $this->getMockCustomerData();
                
            default:
                return [];
        }
    }

    public function getCompanyInfo(): array
    {
        return [
            'name' => 'Demo Company Ltd.',
            'code' => 'demo',
            'system' => 'AbraFlexi (Mock)',
            'url' => 'https://demo.example.com',
        ];
    }

    private function getMockInvoiceData(): array
    {
        return [
            [
                'kod' => 'INV001',
                'typDokl' => 'FAKTURA',
                'sumCelkem' => 25000.00,
                'sumCelkemMen' => 1000.00,
                'sumZalohy' => 0.00,
                'sumZalohyMen' => 0.00,
                'uhrazeno' => 20000.00,
                'storno' => 'false',
                'mena' => 'CZK',
                'firma' => 'Customer A',
                'zbyvaUhradit' => 5000.00,
                'datSplat' => '2024-01-15',
            ],
            [
                'kod' => 'INV002',
                'typDokl' => 'FAKTURA',
                'sumCelkem' => 15000.00,
                'sumCelkemMen' => 600.00,
                'sumZalohy' => 0.00,
                'sumZalohyMen' => 0.00,
                'uhrazeno' => 15000.00,
                'storno' => 'false',
                'mena' => 'CZK',
                'firma' => 'Customer B',
                'zbyvaUhradit' => 0.00,
                'datSplat' => '2024-01-20',
            ],
            [
                'kod' => 'INV003',
                'typDokl' => 'DOBROPIS',
                'sumCelkem' => -5000.00,
                'sumCelkemMen' => -200.00,
                'sumZalohy' => 0.00,
                'sumZalohyMen' => 0.00,
                'uhrazeno' => -5000.00,
                'storno' => 'false',
                'mena' => 'CZK',
                'firma' => 'Customer A',
                'zbyvaUhradit' => 0.00,
                'datSplat' => '2024-01-25',
            ],
        ];
    }

    private function getMockCustomerData(): array
    {
        return [
            [
                'kod' => 'CUST001',
                'nazev' => 'Customer A Ltd.',
                'email' => 'contact@customer-a.com',
                'telefon' => '+420123456789',
            ],
            [
                'kod' => 'CUST002',
                'nazev' => 'Customer B s.r.o.',
                'email' => 'info@customer-b.cz',
                'telefon' => '+420987654321',
            ],
        ];
    }
}

// Use mock provider if AbraFlexi classes are not available
if (!class_exists('\\AbraFlexi\\FakturaVydana')) {
    echo "Note: Using mock data provider (AbraFlexi classes not available)\n\n";
    $dataProvider = new MockAbraFlexiDataProvider();
}
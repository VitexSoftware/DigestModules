<?php

declare(strict_types=1);

namespace VitexSoftware\DigestModules\Tests\Modules;

use PHPUnit\Framework\TestCase;
use VitexSoftware\DigestModules\Core\DataProviderInterface;
use VitexSoftware\DigestModules\Modules\Debtors;

class DebtorsTest extends TestCase
{
    private function makePeriod(): \DatePeriod
    {
        return new \DatePeriod(
            new \DateTime('2024-01-01'),
            new \DateInterval('P1M'),
            new \DateTime('2024-02-01'),
        );
    }

    private function makeProvider(array $returnData): DataProviderInterface
    {
        $mock = $this->createMock(DataProviderInterface::class);
        $mock->method('getData')->willReturn($returnData);
        $mock->method('getSystemName')->willReturn('test');
        $mock->method('supportsFeature')->willReturn(true);

        return $mock;
    }

    public function testEmptyResultWhenNoUnpaidInvoices(): void
    {
        $result = (new Debtors())->process($this->makeProvider([]), $this->makePeriod());

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['data']['summary']['total_debtors']);
    }

    public function testCountsDebtorsByCompany(): void
    {
        $invoices = [
            [
                DataProviderInterface::FIELD_COMPANY          => 'ACME Corp',
                DataProviderInterface::FIELD_CURRENCY         => 'CZK',
                DataProviderInterface::FIELD_REMAINING_AMOUNT => 50000.0,
                DataProviderInterface::FIELD_DUE_DATE         => '2023-11-01',
            ],
            [
                DataProviderInterface::FIELD_COMPANY          => 'ACME Corp',
                DataProviderInterface::FIELD_CURRENCY         => 'CZK',
                DataProviderInterface::FIELD_REMAINING_AMOUNT => 25000.0,
                DataProviderInterface::FIELD_DUE_DATE         => '2023-10-15',
            ],
            [
                DataProviderInterface::FIELD_COMPANY          => 'Beta Ltd',
                DataProviderInterface::FIELD_CURRENCY         => 'CZK',
                DataProviderInterface::FIELD_REMAINING_AMOUNT => 10000.0,
                DataProviderInterface::FIELD_DUE_DATE         => '2023-12-01',
            ],
        ];

        $result = (new Debtors())->process($this->makeProvider($invoices), $this->makePeriod());

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['data']['summary']['total_debtors']);
        $this->assertSame(3, $result['data']['summary']['total_invoices']);
        $this->assertArrayHasKey('ACME Corp', $result['data']['all_debtors']);
        $this->assertArrayHasKey('Beta Ltd', $result['data']['all_debtors']);
        $this->assertSame(2, $result['data']['all_debtors']['ACME Corp']['invoices_count']);
    }

    public function testTotalsByCurrencyPreservesKeys(): void
    {
        $invoices = [
            [
                DataProviderInterface::FIELD_COMPANY          => 'Alpha GmbH',
                DataProviderInterface::FIELD_CURRENCY         => 'EUR',
                DataProviderInterface::FIELD_REMAINING_AMOUNT_FOREIGN => 1200.0,
                DataProviderInterface::FIELD_DUE_DATE         => '2023-10-01',
            ],
            [
                DataProviderInterface::FIELD_COMPANY          => 'Beta Corp',
                DataProviderInterface::FIELD_CURRENCY         => 'CZK',
                DataProviderInterface::FIELD_REMAINING_AMOUNT => 30000.0,
                DataProviderInterface::FIELD_DUE_DATE         => '2023-10-01',
            ],
        ];

        $result = (new Debtors())->process($this->makeProvider($invoices), $this->makePeriod());

        $totals = $result['data']['totals_by_currency'];
        $this->assertArrayHasKey('EUR', $totals, 'Currency key EUR must be preserved');
        $this->assertArrayHasKey('CZK', $totals, 'Currency key CZK must be preserved');
        $this->assertSame(1200.0, $totals['EUR']['amount']);
        $this->assertSame(30000.0, $totals['CZK']['amount']);
    }

    public function testOverdueRangesAreClassified(): void
    {
        $today = new \DateTime();
        $invoices = [
            [
                DataProviderInterface::FIELD_COMPANY          => 'A',
                DataProviderInterface::FIELD_CURRENCY         => 'CZK',
                DataProviderInterface::FIELD_REMAINING_AMOUNT => 1000.0,
                DataProviderInterface::FIELD_DUE_DATE         => (clone $today)->modify('-15 days')->format('Y-m-d'),
            ],
            [
                DataProviderInterface::FIELD_COMPANY          => 'B',
                DataProviderInterface::FIELD_CURRENCY         => 'CZK',
                DataProviderInterface::FIELD_REMAINING_AMOUNT => 2000.0,
                DataProviderInterface::FIELD_DUE_DATE         => (clone $today)->modify('-45 days')->format('Y-m-d'),
            ],
            [
                DataProviderInterface::FIELD_COMPANY          => 'C',
                DataProviderInterface::FIELD_CURRENCY         => 'CZK',
                DataProviderInterface::FIELD_REMAINING_AMOUNT => 3000.0,
                DataProviderInterface::FIELD_DUE_DATE         => (clone $today)->modify('-100 days')->format('Y-m-d'),
            ],
        ];

        $result = (new Debtors())->process($this->makeProvider($invoices), $this->makePeriod());

        $ranges = $result['data']['overdue_ranges'];
        $this->assertSame(1, $ranges['0-30']);
        $this->assertSame(1, $ranges['31-60']);
        $this->assertSame(0, $ranges['61-90']);
        $this->assertSame(1, $ranges['90+']);
    }

    public function testProviderExceptionReturnsFailed(): void
    {
        $mock = $this->createMock(DataProviderInterface::class);
        $mock->method('getData')->willThrowException(new \RuntimeException('connection error'));
        $mock->method('supportsFeature')->willReturn(true);

        $result = (new Debtors())->process($mock, $this->makePeriod());

        $this->assertFalse($result['success']);
        $this->assertSame('connection error', $result['metadata']['error']['message']);
    }

    public function testResultStructure(): void
    {
        $result = (new Debtors())->process($this->makeProvider([]), $this->makePeriod());

        $this->assertArrayHasKey('module_name', $result);
        $this->assertArrayHasKey('heading', $result);
        $this->assertArrayHasKey('period', $result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertSame('debtors', $result['module_name']);
        $this->assertSame('2024-01-01', $result['period']['start']);
        $this->assertSame('2024-02-01', $result['period']['end']);
    }
}

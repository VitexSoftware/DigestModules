<?php

declare(strict_types=1);

/**
 * This file is part of the DigestModules package
 *
 * https://github.com/VitexSoftware/DigestModules/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace VitexSoftware\DigestModules\Tests\Modules;

use PHPUnit\Framework\TestCase;
use VitexSoftware\DigestModules\Core\DataProviderInterface;
use VitexSoftware\DigestModules\Modules\OutcomingInvoices;

class OutcomingInvoicesTest extends TestCase
{
    public function testEmptyPeriodReturnsSuccess(): void
    {
        $result = (new OutcomingInvoices())->process($this->makeProvider([]), self::makePeriod());

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['data']['summary']['total_count']);
    }

    public function testCountsActiveAndCancelled(): void
    {
        $invoices = [
            [
                DataProviderInterface::FIELD_CURRENCY => 'CZK',
                DataProviderInterface::FIELD_TOTAL_AMOUNT => 10000.0,
                DataProviderInterface::FIELD_CANCELLED => false,
                DataProviderInterface::FIELD_DOCUMENT_TYPE => 'invoice',
            ],
            [
                DataProviderInterface::FIELD_CURRENCY => 'CZK',
                DataProviderInterface::FIELD_TOTAL_AMOUNT => 5000.0,
                DataProviderInterface::FIELD_CANCELLED => true,
                DataProviderInterface::FIELD_DOCUMENT_TYPE => 'invoice',
            ],
            [
                DataProviderInterface::FIELD_CURRENCY => 'CZK',
                DataProviderInterface::FIELD_TOTAL_AMOUNT => 3000.0,
                DataProviderInterface::FIELD_CANCELLED => false,
                DataProviderInterface::FIELD_DOCUMENT_TYPE => 'invoice',
            ],
        ];

        $result = (new OutcomingInvoices())->process($this->makeProvider($invoices), self::makePeriod());

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['data']['summary']['total_count']);
        $this->assertSame(2, $result['data']['summary']['active_count']);
        $this->assertSame(1, $result['data']['summary']['cancelled_count']);
    }

    public function testTotalsByCurrencyFromActiveOnly(): void
    {
        $invoices = [
            [
                DataProviderInterface::FIELD_CURRENCY => 'CZK',
                DataProviderInterface::FIELD_TOTAL_AMOUNT => 10000.0,
                DataProviderInterface::FIELD_CANCELLED => false,
                DataProviderInterface::FIELD_DOCUMENT_TYPE => 'invoice',
            ],
            [
                DataProviderInterface::FIELD_CURRENCY => 'CZK',
                DataProviderInterface::FIELD_TOTAL_AMOUNT => 999.0,
                DataProviderInterface::FIELD_CANCELLED => true,  // cancelled — must not be summed
                DataProviderInterface::FIELD_DOCUMENT_TYPE => 'invoice',
            ],
            [
                DataProviderInterface::FIELD_CURRENCY => 'EUR',
                DataProviderInterface::FIELD_TOTAL_AMOUNT => 0.0,
                DataProviderInterface::FIELD_TOTAL_AMOUNT_FOREIGN => 400.0,
                DataProviderInterface::FIELD_CANCELLED => false,
                DataProviderInterface::FIELD_DOCUMENT_TYPE => 'invoice',
            ],
        ];

        $result = (new OutcomingInvoices())->process($this->makeProvider($invoices), self::makePeriod());

        $totals = $result['data']['totals_by_currency'];
        $this->assertArrayHasKey('CZK', $totals);
        $this->assertSame(10000.0, $totals['CZK']['amount']);
        $this->assertArrayHasKey('EUR', $totals);
        $this->assertSame(400.0, $totals['EUR']['amount']);
    }

    public function testGroupsByDocumentType(): void
    {
        $invoices = [
            [
                DataProviderInterface::FIELD_CURRENCY => 'CZK',
                DataProviderInterface::FIELD_TOTAL_AMOUNT => 1000.0,
                DataProviderInterface::FIELD_CANCELLED => false,
                DataProviderInterface::FIELD_DOCUMENT_TYPE => 'invoice',
            ],
            [
                DataProviderInterface::FIELD_CURRENCY => 'CZK',
                DataProviderInterface::FIELD_TOTAL_AMOUNT => 500.0,
                DataProviderInterface::FIELD_CANCELLED => false,
                DataProviderInterface::FIELD_DOCUMENT_TYPE => 'proforma',
            ],
        ];

        $result = (new OutcomingInvoices())->process($this->makeProvider($invoices), self::makePeriod());

        $this->assertArrayHasKey('invoice', $result['data']['by_document_type']);
        $this->assertArrayHasKey('proforma', $result['data']['by_document_type']);
        $this->assertSame(1, $result['data']['by_document_type']['invoice']['count']);
        $this->assertSame(1, $result['data']['by_document_type']['proforma']['count']);
    }

    public function testProviderExceptionReturnsFailed(): void
    {
        $mock = $this->createMock(DataProviderInterface::class);
        $mock->method('getData')->willThrowException(new \RuntimeException('timeout'));
        $mock->method('supportsFeature')->willReturn(true);

        $result = (new OutcomingInvoices())->process($mock, self::makePeriod());

        $this->assertFalse($result['success']);
    }
    private static function makePeriod(): \DatePeriod
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
}

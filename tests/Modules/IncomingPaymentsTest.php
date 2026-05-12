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
use VitexSoftware\DigestModules\Modules\IncomingPayments;

class IncomingPaymentsTest extends TestCase
{
    public function testEmptyResultWhenNoPayments(): void
    {
        $result = (new IncomingPayments())->process($this->makeProvider([]), self::makePeriod());

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['data']['summary']['total_count']);
        $this->assertSame([], $result['data']['summary']['currencies']);
    }

    public function testSumsByCurrency(): void
    {
        $payments = [
            [
                DataProviderInterface::FIELD_CURRENCY => 'CZK',
                DataProviderInterface::FIELD_TOTAL_AMOUNT => 5000.0,
            ],
            [
                DataProviderInterface::FIELD_CURRENCY => 'CZK',
                DataProviderInterface::FIELD_TOTAL_AMOUNT => 3000.0,
            ],
            [
                DataProviderInterface::FIELD_CURRENCY => 'EUR',
                DataProviderInterface::FIELD_TOTAL_AMOUNT_FOREIGN => 200.0,
            ],
        ];

        $result = (new IncomingPayments())->process($this->makeProvider($payments), self::makePeriod());

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['data']['summary']['total_count']);

        $totals = $result['data']['totals_by_currency'];
        $this->assertArrayHasKey('CZK', $totals);
        $this->assertSame(8000.0, $totals['CZK']['amount']);
        $this->assertArrayHasKey('EUR', $totals);
        $this->assertSame(200.0, $totals['EUR']['amount']);
    }

    public function testCurrencyKeysArePreserved(): void
    {
        $payments = [
            [DataProviderInterface::FIELD_CURRENCY => 'USD', DataProviderInterface::FIELD_TOTAL_AMOUNT_FOREIGN => 100.0],
            [DataProviderInterface::FIELD_CURRENCY => 'GBP', DataProviderInterface::FIELD_TOTAL_AMOUNT_FOREIGN => 50.0],
        ];

        $result = (new IncomingPayments())->process($this->makeProvider($payments), self::makePeriod());
        $totals = $result['data']['totals_by_currency'];

        $this->assertArrayHasKey('USD', $totals, 'USD key must be preserved');
        $this->assertArrayHasKey('GBP', $totals, 'GBP key must be preserved');
        $this->assertArrayNotHasKey(0, $totals, 'Numeric keys must not appear (array_map regression)');
        $this->assertArrayNotHasKey(1, $totals, 'Numeric keys must not appear (array_map regression)');
    }

    public function testFormattedFieldsArePresent(): void
    {
        $payments = [
            [DataProviderInterface::FIELD_CURRENCY => 'CZK', DataProviderInterface::FIELD_TOTAL_AMOUNT => 1234.56],
        ];

        $result = (new IncomingPayments())->process($this->makeProvider($payments), self::makePeriod());
        $czk = $result['data']['totals_by_currency']['CZK'];

        $this->assertArrayHasKey('amount', $czk);
        $this->assertArrayHasKey('currency', $czk);
        $this->assertArrayHasKey('formatted', $czk);
        $this->assertSame(1234.56, $czk['amount']);
        $this->assertSame('CZK', $czk['currency']);
    }

    public function testProviderExceptionReturnsFailed(): void
    {
        $mock = $this->createMock(DataProviderInterface::class);
        $mock->method('getData')->willThrowException(new \RuntimeException('timeout'));
        $mock->method('getSystemName')->willReturn('test');
        $mock->method('supportsFeature')->willReturn(true);

        $result = (new IncomingPayments())->process($mock, self::makePeriod());

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

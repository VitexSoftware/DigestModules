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

namespace VitexSoftware\DigestModules\Modules\Monthly;

use VitexSoftware\DigestModules\Core\AbstractModule;
use VitexSoftware\DigestModules\Core\DataProviderInterface;

/**
 * Daily income chart data module (monthly period)
 *
 * Aggregates incoming bank payments by day for charting.
 * Provides per-day totals by currency and average calculations.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class DailyIncomeChart extends AbstractModule
{
    protected string $moduleName = 'daily_income_chart';
    protected string $heading = 'Incoming payments chart';
    protected string $description = 'Daily income aggregation for chart visualization';
    protected array $requiredFeatures = ['date_filtering'];

    /**
     * {@inheritDoc}
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        try {
            $incomes = $provider->getData(
                DataProviderInterface::ENTITY_BANK_STATEMENTS,
                [
                    'date_period' => ['column' => 'datVyst', 'period' => $period],
                    'typPohybuK' => 'typPohybu.prijem',
                    'storno' => false,
                    'limit' => 0,
                ],
                ['mena', 'sumCelkem', 'sumCelkemMen', 'datVyst'],
            );

            if (empty($incomes)) {
                return $this->createResult($period, true, [
                    'summary' => ['total_days' => 0],
                    'days' => [],
                    'averages' => [],
                ]);
            }

            $days = [];
            $currencyTotals = [];

            foreach ($incomes as $income) {
                $currency = $this->extractCurrency($income);
                $amount = ($currency === 'CZK')
                    ? (float) ($income['sumCelkem'] ?? 0)
                    : (float) ($income['sumCelkemMen'] ?? 0);
                $day = (string) ($income['datVyst'] ?? '');

                if (empty($day)) {
                    continue;
                }

                $days[$day][$currency] = ($days[$day][$currency] ?? 0.0) + $amount;
                $currencyTotals[$currency] = ($currencyTotals[$currency] ?? 0.0) + $amount;
            }

            // Calculate averages per currency
            $averages = [];

            foreach ($currencyTotals as $currency => $total) {
                $daysWithCurrency = 0;

                foreach ($days as $dayData) {
                    if (isset($dayData[$currency])) {
                        ++$daysWithCurrency;
                    }
                }

                $averages[$currency] = [
                    'average' => $daysWithCurrency > 0 ? ceil($total / $daysWithCurrency) : 0,
                    'total' => $total,
                    'days_count' => $daysWithCurrency,
                ];
            }

            // Format days data with percentage relative to average
            $formattedDays = [];

            foreach ($days as $day => $currencies) {
                $dayEntry = ['date' => $day, 'currencies' => []];

                foreach ($currencies as $currency => $amount) {
                    $avg = $averages[$currency]['average'] ?? 1;
                    $percent = $avg > 0 ? round(($amount / $avg) * 100) : 0;
                    $dayEntry['currencies'][$currency] = [
                        'amount' => $amount,
                        'percent_of_average' => $percent,
                    ];
                }

                $formattedDays[] = $dayEntry;
            }

            return $this->createResult($period, true, [
                'summary' => [
                    'total_days' => \count($days),
                    'currencies' => array_keys($currencyTotals),
                ],
                'days' => $formattedDays,
                'averages' => $averages,
            ], [
                'provider' => $provider->getSystemName(),
            ]);
        } catch (\Throwable $e) {
            return $this->createResult($period, false, [], [
                'provider' => $provider->getSystemName(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract currency code from payment data
     */
    private function extractCurrency(array $payment): string
    {
        $mena = $payment['mena'] ?? 'CZK';

        return \is_string($mena) ? str_replace('code:', '', $mena) : 'CZK';
    }
}

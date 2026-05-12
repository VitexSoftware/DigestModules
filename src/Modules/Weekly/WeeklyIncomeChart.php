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

namespace VitexSoftware\DigestModules\Modules\Weekly;

use VitexSoftware\DigestModules\Core\AbstractModule;
use VitexSoftware\DigestModules\Core\DataProviderInterface;

/**
 * Weekly income chart data module.
 *
 * Aggregates incoming bank payments by day for weekly chart visualization.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class WeeklyIncomeChart extends AbstractModule
{
    protected string $moduleName = 'weekly_income_chart';
    protected string $heading = 'Incoming payments chart';
    protected string $description = 'Weekly income aggregation for chart visualization';
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
                    DataProviderInterface::FILTER_DATE_PERIOD => [
                        'column' => DataProviderInterface::DATE_COLUMN_ISSUE_DATE,
                        'period' => $period,
                    ],
                    DataProviderInterface::FILTER_PAYMENT_DIRECTION => DataProviderInterface::DIRECTION_INCOMING,
                    DataProviderInterface::FILTER_CANCELLED => false,
                    DataProviderInterface::FILTER_LIMIT => 0,
                ],
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
                $currency = (string) ($income[DataProviderInterface::FIELD_CURRENCY] ?? 'CZK');
                $amount = $currency !== 'CZK'
                    ? (float) ($income[DataProviderInterface::FIELD_TOTAL_AMOUNT_FOREIGN] ?? 0)
                    : (float) ($income[DataProviderInterface::FIELD_TOTAL_AMOUNT] ?? 0);
                $day = (string) ($income[DataProviderInterface::FIELD_DATE] ?? '');

                if (empty($day)) {
                    continue;
                }

                $days[$day][$currency] = ($days[$day][$currency] ?? 0.0) + $amount;
                $currencyTotals[$currency] = ($currencyTotals[$currency] ?? 0.0) + $amount;
            }

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
                'summary' => ['total_days' => \count($days), 'currencies' => array_keys($currencyTotals)],
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
}

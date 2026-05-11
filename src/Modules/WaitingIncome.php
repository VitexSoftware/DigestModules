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

namespace VitexSoftware\DigestModules\Modules;

use VitexSoftware\DigestModules\Core\AbstractModule;
use VitexSoftware\DigestModules\Core\DataProviderInterface;
use VitexSoftware\DigestModules\Core\ZabbixOutputInterface;

/**
 * Waiting income analysis module
 *
 * Analyzes unpaid outgoing invoices with due date within the period.
 * Reports expected income that has not yet been received.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class WaitingIncome extends AbstractModule implements ZabbixOutputInterface
{
    protected string $moduleName = 'waiting_income';
    protected string $heading = 'Waiting income';
    protected string $description = 'Unpaid outgoing invoices due within the analyzed period';
    protected array $requiredFeatures = ['date_filtering', 'payment_status'];

    /**
     * {@inheritDoc}
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        try {
            $invoices = $provider->getData(
                DataProviderInterface::ENTITY_OUTCOMING_INVOICES,
                [
                    DataProviderInterface::FILTER_DATE_PERIOD => [
                        'column' => DataProviderInterface::DATE_COLUMN_DUE_DATE,
                        'period' => $period,
                    ],
                    DataProviderInterface::FILTER_PAYMENT_STATUS => DataProviderInterface::PAYMENT_STATUS_UNPAID_OR_PARTIAL,
                    DataProviderInterface::FILTER_CANCELLED       => false,
                    DataProviderInterface::FILTER_LIMIT           => 0,
                ],
            );

            if (empty($invoices)) {
                return $this->createResult($period, true, [
                    'summary'            => ['total_count' => 0, 'total_amount' => $this->formatCurrency(0.0)],
                    'totals_by_currency' => [],
                    'invoices'           => [],
                ]);
            }

            $totalsByCurrency = [];
            $invoiceList      = [];

            foreach ($invoices as $invoice) {
                $currency = (string) ($invoice[DataProviderInterface::FIELD_CURRENCY] ?? 'CZK');
                $amount   = $currency !== 'CZK'
                    ? (float) ($invoice[DataProviderInterface::FIELD_TOTAL_AMOUNT_FOREIGN] ?? 0)
                    : (float) ($invoice[DataProviderInterface::FIELD_TOTAL_AMOUNT] ?? 0);

                $totalsByCurrency[$currency] = ($totalsByCurrency[$currency] ?? 0.0) + $amount;

                $invoiceList[] = [
                    'code'    => $invoice[DataProviderInterface::FIELD_CODE] ?? '',
                    'company' => $invoice[DataProviderInterface::FIELD_COMPANY] ?? '',
                    'amount'  => $this->formatCurrency($amount, $currency),
                ];
            }

            $formattedTotals = [];

            foreach ($totalsByCurrency as $currency => $total) {
                $formattedTotals[$currency] = $this->formatCurrency($total, $currency);
            }

            $mainCurrency = array_key_first($totalsByCurrency) ?? 'CZK';

            return $this->createResult($period, true, [
                'summary' => [
                    'total_count'  => \count($invoices),
                    'total_amount' => $this->formatCurrency($totalsByCurrency[$mainCurrency] ?? 0.0, $mainCurrency),
                ],
                'totals_by_currency' => $formattedTotals,
                'invoices'           => $invoiceList,
            ], [
                'provider' => $provider->getSystemName(),
            ]);
        } catch (\Throwable $e) {
            return $this->createResult($period, false, [], [
                'provider' => $provider->getSystemName(),
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function toZabbixItems(array $processedData): array
    {
        $data    = $processedData['data'] ?? [];
        $summary = $data['summary'] ?? [];

        return [
            'waiting_income.count'        => $summary['total_count'] ?? 0,
            'waiting_income.total_amount' => $summary['total_amount']['amount'] ?? 0,
        ];
    }
}

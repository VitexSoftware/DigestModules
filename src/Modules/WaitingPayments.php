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
 * Waiting payments analysis module.
 *
 * Analyzes unpaid incoming invoices with due date within the period.
 * Reports amounts that the company needs to pay to suppliers.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class WaitingPayments extends AbstractModule implements ZabbixOutputInterface
{
    protected string $moduleName = 'waiting_payments';
    protected string $heading = 'Waiting payments';
    protected string $description = 'Unpaid incoming invoices due within the analyzed period (amounts we must pay)';
    protected array $requiredFeatures = ['date_filtering', 'payment_status'];

    /**
     * {@inheritDoc}
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        try {
            $invoices = $provider->getData(
                DataProviderInterface::ENTITY_INCOMING_INVOICES,
                [
                    DataProviderInterface::FILTER_DATE_PERIOD => [
                        'column' => DataProviderInterface::DATE_COLUMN_DUE_DATE,
                        'period' => $period,
                    ],
                    DataProviderInterface::FILTER_PAYMENT_STATUS => DataProviderInterface::PAYMENT_STATUS_UNPAID_OR_PARTIAL,
                    DataProviderInterface::FILTER_CANCELLED => false,
                    DataProviderInterface::FILTER_LIMIT => 0,
                ],
            );

            if (empty($invoices)) {
                return $this->createResult($period, true, [
                    'summary' => ['total_count' => 0, 'total_amount' => $this->formatCurrency(0.0)],
                    'totals_by_currency' => [],
                    'invoices' => [],
                ]);
            }

            $totalsByCurrency = [];
            $invoiceList = [];

            foreach ($invoices as $invoice) {
                $currency = (string) ($invoice[DataProviderInterface::FIELD_CURRENCY] ?? 'CZK');
                $remaining = $currency !== 'CZK'
                    ? (float) ($invoice[DataProviderInterface::FIELD_REMAINING_AMOUNT_FOREIGN] ?? 0)
                    : (float) ($invoice[DataProviderInterface::FIELD_REMAINING_AMOUNT] ?? $invoice[DataProviderInterface::FIELD_TOTAL_AMOUNT] ?? 0);

                $totalsByCurrency[$currency] = ($totalsByCurrency[$currency] ?? 0.0) + $remaining;

                $invoiceList[] = [
                    'code' => $invoice[DataProviderInterface::FIELD_CODE] ?? '',
                    'company' => $invoice[DataProviderInterface::FIELD_COMPANY] ?? '',
                    'due_date' => $invoice[DataProviderInterface::FIELD_DUE_DATE] ?? '',
                    'remaining' => $this->formatCurrency($remaining, $currency),
                ];
            }

            $formattedTotals = [];

            foreach ($totalsByCurrency as $currency => $total) {
                $formattedTotals[$currency] = $this->formatCurrency($total, $currency);
            }

            $mainCurrency = array_key_first($totalsByCurrency) ?? 'CZK';

            return $this->createResult($period, true, [
                'summary' => [
                    'total_count' => \count($invoices),
                    'total_amount' => $this->formatCurrency($totalsByCurrency[$mainCurrency] ?? 0.0, $mainCurrency),
                ],
                'totals_by_currency' => $formattedTotals,
                'invoices' => $invoiceList,
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
     * {@inheritDoc}
     */
    public function toZabbixItems(array $processedData): array
    {
        $data = $processedData['data'] ?? [];
        $summary = $data['summary'] ?? [];

        return [
            'waiting_payments.count' => $summary['total_count'] ?? 0,
            'waiting_payments.total_amount' => $summary['total_amount']['amount'] ?? 0,
        ];
    }
}

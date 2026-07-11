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
 * Outcoming invoices analysis module.
 *
 * Analyzes issued invoices for the given period.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class OutcomingInvoices extends AbstractModule implements ZabbixOutputInterface
{
    protected string $moduleName = 'outcoming_invoices';
    protected string $heading = 'Outcoming Invoices';
    protected string $description = 'Analysis of issued invoices including totals, document types, and currency breakdown';
    protected array $requiredFeatures = ['date_filtering', 'document_types'];

    /**
     * {@inheritDoc}
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        try {
            $invoicesData = $provider->getData(
                DataProviderInterface::ENTITY_OUTCOMING_INVOICES,
                [
                    DataProviderInterface::FILTER_DATE_PERIOD => [
                        'column' => DataProviderInterface::DATE_COLUMN_ISSUE_DATE,
                        'period' => $period,
                    ],
                    DataProviderInterface::FILTER_LIMIT => 0,
                ],
            );

            if (empty($invoicesData)) {
                return $this->createResult($period, true, [
                    'summary' => [
                        'total_count' => 0,
                        'total_amount' => $this->formatCurrency(0.0),
                        'message' => 'No outcoming invoices found for the specified period',
                    ],
                ]);
            }

            return $this->createResult($period, true, $this->analyzeInvoices($invoicesData));
        } catch (\Throwable $e) {
            return $this->createResult($period, false, [], [
                'error' => [
                    'message' => $e->getMessage(),
                    'type' => $e::class,
                ],
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
            'outcoming_invoices.count' => $summary['total_count'] ?? 0,
            'outcoming_invoices.active_count' => $summary['active_count'] ?? 0,
            'outcoming_invoices.cancelled_count' => $summary['cancelled_count'] ?? 0,
        ];
    }

    /**
     * @param array<array<string, mixed>> $invoicesData
     */
    private function analyzeInvoices(array $invoicesData): array
    {
        $totalCount = 0;
        $stornoCount = 0;
        $totalsByCurrency = [];
        $typDoklCounts = [];
        $typDoklTotals = [];

        foreach ($invoicesData as $invoice) {
            ++$totalCount;

            if ($invoice[DataProviderInterface::FIELD_CANCELLED] ?? false) {
                ++$stornoCount;

                continue;
            }

            $currency = (string) ($invoice[DataProviderInterface::FIELD_CURRENCY] ?? 'CZK');
            $documentType = (string) ($invoice[DataProviderInterface::FIELD_DOCUMENT_TYPE] ?? 'unknown');
            $amount = $currency !== 'CZK'
                ? (float) ($invoice[DataProviderInterface::FIELD_TOTAL_AMOUNT_FOREIGN] ?? 0)
                    + (float) ($invoice[DataProviderInterface::FIELD_DEPOSIT_AMOUNT_FOREIGN] ?? 0)
                : (float) ($invoice[DataProviderInterface::FIELD_TOTAL_AMOUNT] ?? 0)
                    + (float) ($invoice[DataProviderInterface::FIELD_DEPOSIT_AMOUNT] ?? 0);

            $totalsByCurrency[$currency] = ($totalsByCurrency[$currency] ?? 0.0) + $amount;

            $typDoklCounts[$documentType] = ($typDoklCounts[$documentType] ?? 0) + 1;

            if (!isset($typDoklTotals[$documentType])) {
                $typDoklTotals[$documentType] = [];
            }

            $typDoklTotals[$documentType][$currency] =
                ($typDoklTotals[$documentType][$currency] ?? 0.0) + $amount;
        }

        $analysis = [
            'summary' => [
                'total_count' => $totalCount,
                'active_count' => $totalCount - $stornoCount,
                'cancelled_count' => $stornoCount,
                'document_types_count' => \count($typDoklCounts),
                'currencies' => array_keys($totalsByCurrency),
            ],
            'totals_by_currency' => [],
            'by_document_type' => [],
        ];

        foreach ($totalsByCurrency as $currency => $total) {
            $analysis['totals_by_currency'][$currency] = $this->formatCurrency($total, $currency);
        }

        foreach ($typDoklTotals as $docType => $currencyTotals) {
            $analysis['by_document_type'][$docType] = [
                'count' => $typDoklCounts[$docType],
                'totals' => [],
            ];

            foreach ($currencyTotals as $currency => $total) {
                $analysis['by_document_type'][$docType]['totals'][$currency] =
                    $this->formatCurrency($total, $currency);
            }
        }

        return $analysis;
    }
}

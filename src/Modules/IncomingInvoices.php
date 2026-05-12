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
 * Incoming invoices analysis module.
 *
 * Analyzes received invoices for a given period, providing totals
 * by document type and currency.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class IncomingInvoices extends AbstractModule implements ZabbixOutputInterface
{
    protected string $moduleName = 'incoming_invoices';
    protected string $heading = 'Incoming invoices';
    protected string $description = 'Analysis of received invoices including totals by document type and currency';
    protected array $requiredFeatures = ['date_filtering', 'document_types'];

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
                        'column' => DataProviderInterface::DATE_COLUMN_ISSUE_DATE,
                        'period' => $period,
                    ],
                    DataProviderInterface::FILTER_LIMIT => 0,
                ],
            );

            if (empty($invoices)) {
                return $this->createResult($period, true, [
                    'summary' => [
                        'total_count' => 0,
                        'active_count' => 0,
                        'cancelled_count' => 0,
                    ],
                    'totals_by_currency' => [],
                    'by_document_type' => [],
                ]);
            }

            $analysis = $this->analyzeInvoices($invoices);

            return $this->createResult($period, true, $analysis, [
                'provider' => $provider->getSystemName(),
                'records_processed' => \count($invoices),
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
            'incoming_invoices.count' => $summary['total_count'] ?? 0,
            'incoming_invoices.active_count' => $summary['active_count'] ?? 0,
            'incoming_invoices.cancelled_count' => $summary['cancelled_count'] ?? 0,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $invoices
     */
    private function analyzeInvoices(array $invoices): array
    {
        $totalsByCurrency = [];
        $byDocumentType = [];
        $activeCount = 0;
        $cancelledCount = 0;

        foreach ($invoices as $invoice) {
            $currency = (string) ($invoice[DataProviderInterface::FIELD_CURRENCY] ?? 'CZK');
            $amount = $currency !== 'CZK'
                ? (float) ($invoice[DataProviderInterface::FIELD_TOTAL_AMOUNT_FOREIGN] ?? 0)
                : (float) ($invoice[DataProviderInterface::FIELD_TOTAL_AMOUNT] ?? 0);
            $documentType = (string) ($invoice[DataProviderInterface::FIELD_DOCUMENT_TYPE] ?? _('Unknown'));
            $cancelled = (bool) ($invoice[DataProviderInterface::FIELD_CANCELLED] ?? false);

            if ($cancelled) {
                ++$cancelledCount;

                continue;
            }

            ++$activeCount;

            $totalsByCurrency[$currency] = ($totalsByCurrency[$currency] ?? 0.0) + $amount;

            if (!isset($byDocumentType[$documentType])) {
                $byDocumentType[$documentType] = ['count' => 0, 'totals' => []];
            }

            ++$byDocumentType[$documentType]['count'];
            $byDocumentType[$documentType]['totals'][$currency] =
                ($byDocumentType[$documentType]['totals'][$currency] ?? 0.0) + $amount;
        }

        $formattedTotals = [];

        foreach ($totalsByCurrency as $currency => $total) {
            $formattedTotals[$currency] = $this->formatCurrency($total, $currency);
        }

        $formattedByType = [];

        foreach ($byDocumentType as $type => $typeData) {
            $typeTotals = [];

            foreach ($typeData['totals'] as $currency => $total) {
                $typeTotals[$currency] = $this->formatCurrency($total, $currency);
            }

            $formattedByType[$type] = ['count' => $typeData['count'], 'totals' => $typeTotals];
        }

        return [
            'summary' => [
                'total_count' => $activeCount + $cancelledCount,
                'active_count' => $activeCount,
                'cancelled_count' => $cancelledCount,
            ],
            'totals_by_currency' => $formattedTotals,
            'by_document_type' => $formattedByType,
        ];
    }
}

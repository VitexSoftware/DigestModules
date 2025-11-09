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

/**
 * Outcoming invoices analysis module
 *
 * Analyzes issued invoices for the given period
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class OutcomingInvoices extends AbstractModule
{
    /**
     * {@inheritDoc}
     */
    protected string $moduleName = 'outcoming_invoices';

    /**
     * {@inheritDoc}
     */
    protected string $heading = 'Outcoming Invoices';

    /**
     * {@inheritDoc}
     */
    protected string $description = 'Analysis of issued invoices including totals, document types, and currency breakdown';

    /**
     * {@inheritDoc}
     */
    protected array $requiredFeatures = ['date_filtering', 'document_types'];

    /**
     * {@inheritDoc}
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        try {
            // Get invoice data from the provider
            $conditions = [
                'date_period' => [
                    'column' => 'datVyst',
                    'period' => $period,
                ],
                'limit' => 0, // Get all records
            ];

            $columns = [
                'kod', 'typDokl', 'sumCelkem', 'sumCelkemMen',
                'sumZalohy', 'sumZalohyMen', 'uhrazeno', 'storno', 'mena'
            ];

            $invoicesData = $provider->getData('outcoming_invoices', $conditions, $columns);

            if (empty($invoicesData)) {
                return $this->createResult($period, true, [
                    'summary' => [
                        'total_count' => 0,
                        'total_amount' => $this->formatCurrency(0.0),
                        'message' => 'No outcoming invoices found for the specified period',
                    ],
                ]);
            }

            // Process the data
            $analysis = $this->analyzeInvoices($invoicesData);

            return $this->createResult($period, true, $analysis, [
                'processing_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'] ?? 0,
            ]);

        } catch (\Throwable $e) {
            return $this->createResult($period, false, [], [
                'error' => [
                    'message' => $e->getMessage(),
                    'type' => get_class($e),
                ],
            ]);
        }
    }

    /**
     * Analyze invoices data
     *
     * @param array<array<string, mixed>> $invoicesData Raw invoice data
     * @return array<string, mixed> Analyzed data
     */
    private function analyzeInvoices(array $invoicesData): array
    {
        $totalCount = 0;
        $totalsByCurrency = [];
        $typDoklCounts = [];
        $typDoklTotals = [];
        $stornoCount = 0;

        foreach ($invoicesData as $invoice) {
            $totalCount++;

            // Handle storno (cancelled) invoices
            if (isset($invoice['storno']) && $invoice['storno'] === 'true') {
                $stornoCount++;
                continue; // Skip cancelled invoices in totals
            }

            $currency = $this->getCurrency($invoice);
            $documentType = (string)($invoice['typDokl'] ?? 'unknown');

            // Calculate amount (including deposits)
            $amount = $this->calculateInvoiceAmount($invoice, $currency);

            // Update totals by currency
            if (!isset($totalsByCurrency[$currency])) {
                $totalsByCurrency[$currency] = 0;
            }
            $totalsByCurrency[$currency] += $amount;

            // Update document type statistics
            if (!isset($typDoklCounts[$documentType])) {
                $typDoklCounts[$documentType] = 0;
                $typDoklTotals[$documentType] = [];
            }
            $typDoklCounts[$documentType]++;

            if (!isset($typDoklTotals[$documentType][$currency])) {
                $typDoklTotals[$documentType][$currency] = 0;
            }
            $typDoklTotals[$documentType][$currency] += $amount;
        }

        // Build structured result
        $analysis = [
            'summary' => [
                'total_count' => $totalCount,
                'active_count' => $totalCount - $stornoCount,
                'cancelled_count' => $stornoCount,
                'document_types_count' => count($typDoklCounts),
                'currencies' => array_keys($totalsByCurrency),
            ],
            'totals_by_currency' => [],
            'by_document_type' => [],
        ];

        // Format currency totals
        foreach ($totalsByCurrency as $currency => $total) {
            $analysis['totals_by_currency'][$currency] = $this->formatCurrency($total, $currency);
        }

        // Format document type breakdown
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

    /**
     * Get currency from invoice data
     *
     * @param array<string, mixed> $invoice Invoice data
     * @return string Currency code
     */
    private function getCurrency(array $invoice): string
    {
        $currency = $invoice['mena'] ?? 'CZK';
        
        // Handle AbraFlexi currency format
        if (is_array($currency) && isset($currency['kod'])) {
            return (string)$currency['kod'];
        }
        
        return (string)$currency;
    }

    /**
     * Calculate total invoice amount including deposits
     *
     * @param array<string, mixed> $invoice Invoice data
     * @param string $currency Currency code
     * @return float Total amount
     */
    private function calculateInvoiceAmount(array $invoice, string $currency): float
    {
        if ($currency !== 'CZK') {
            // Use foreign currency amounts
            $base = (float)($invoice['sumCelkemMen'] ?? 0);
            $deposit = (float)($invoice['sumZalohyMen'] ?? 0);
        } else {
            // Use CZK amounts
            $base = (float)($invoice['sumCelkem'] ?? 0);
            $deposit = (float)($invoice['sumZalohy'] ?? 0);
        }

        return $base + $deposit;
    }
}
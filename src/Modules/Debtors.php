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
 * Debtors analysis module
 *
 * Analyzes unpaid invoices and tracks debtors
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class Debtors extends AbstractModule
{
    /**
     * {@inheritDoc}
     */
    protected string $moduleName = 'debtors';

    /**
     * {@inheritDoc}
     */
    protected string $heading = 'Debtors';

    /**
     * {@inheritDoc}
     */
    protected string $description = 'Analysis of unpaid invoices and overdue amounts by customer';

    /**
     * {@inheritDoc}
     */
    protected array $requiredFeatures = ['payment_status', 'date_filtering'];

    /**
     * {@inheritDoc}
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        try {
            // Get unpaid invoices data
            $conditions = [
                'datSplat lte \'' . date('Y-m-d') . '\' AND (stavUhrK is null OR stavUhrK eq \'stavUhr.castUhr\') AND storno eq false',
                "not(typDokl.typDoklK eq 'typDokladu.dobropis')",
                'includes' => 'faktura-vydana/typDokl/',
                'limit' => 0,
            ];

            $columns = [
                'kod', 'firma', 'sumCelkem', 'typDokl(typDoklK)',
                'sumCelkemMen', 'zbyvaUhradit', 'zbyvaUhraditMen', 'mena', 'datSplat'
            ];

            $unpaidInvoices = $provider->getData('outcoming_invoices', $conditions, $columns);

            if (empty($unpaidInvoices)) {
                return $this->createResult($period, true, [
                    'summary' => [
                        'total_debtors' => 0,
                        'total_unpaid_amount' => $this->formatCurrency(0.0),
                        'message' => 'No unpaid invoices found',
                    ],
                ]);
            }

            $analysis = $this->analyzeDebtors($unpaidInvoices);

            return $this->createResult($period, true, $analysis);

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
     * Analyze debtor data
     *
     * @param array<array<string, mixed>> $unpaidInvoices Unpaid invoices data
     * @return array<string, mixed> Analysis results
     */
    private function analyzeDebtors(array $unpaidInvoices): array
    {
        $debtorsByCompany = [];
        $totalsByCurrency = [];
        $overdueRanges = [
            '0-30' => 0,
            '31-60' => 0,
            '61-90' => 0,
            '90+' => 0,
        ];

        foreach ($unpaidInvoices as $invoice) {
            $company = (string)($invoice['firma'] ?? 'unknown');
            $currency = $this->getCurrency($invoice);
            $amount = $this->getUnpaidAmount($invoice, $currency);
            $overdueDays = $this->calculateOverdueDays($invoice['datSplat'] ?? '');

            // Group by company
            if (!isset($debtorsByCompany[$company])) {
                $debtorsByCompany[$company] = [
                    'company' => $company,
                    'invoices_count' => 0,
                    'total_amount' => [],
                    'overdue_days_max' => 0,
                ];
            }

            $debtorsByCompany[$company]['invoices_count']++;
            
            if (!isset($debtorsByCompany[$company]['total_amount'][$currency])) {
                $debtorsByCompany[$company]['total_amount'][$currency] = 0;
            }
            $debtorsByCompany[$company]['total_amount'][$currency] += $amount;
            
            $debtorsByCompany[$company]['overdue_days_max'] = max(
                $debtorsByCompany[$company]['overdue_days_max'],
                $overdueDays
            );

            // Total by currency
            if (!isset($totalsByCurrency[$currency])) {
                $totalsByCurrency[$currency] = 0;
            }
            $totalsByCurrency[$currency] += $amount;

            // Overdue ranges
            if ($overdueDays <= 30) {
                $overdueRanges['0-30']++;
            } elseif ($overdueDays <= 60) {
                $overdueRanges['31-60']++;
            } elseif ($overdueDays <= 90) {
                $overdueRanges['61-90']++;
            } else {
                $overdueRanges['90+']++;
            }
        }

        // Format amounts for debtors
        foreach ($debtorsByCompany as &$debtor) {
            $formattedAmounts = [];
            foreach ($debtor['total_amount'] as $currency => $amount) {
                $formattedAmounts[$currency] = $this->formatCurrency($amount, $currency);
            }
            $debtor['total_amount'] = $formattedAmounts;
        }

        // Sort debtors by total amount (descending)
        uasort($debtorsByCompany, function($a, $b) {
            $totalA = array_sum(array_column($a['total_amount'], 'amount'));
            $totalB = array_sum(array_column($b['total_amount'], 'amount'));
            return $totalB <=> $totalA;
        });

        return [
            'summary' => [
                'total_debtors' => count($debtorsByCompany),
                'total_invoices' => count($unpaidInvoices),
                'currencies' => array_keys($totalsByCurrency),
            ],
            'totals_by_currency' => array_map(
                fn($amount, $currency) => $this->formatCurrency($amount, $currency),
                $totalsByCurrency,
                array_keys($totalsByCurrency)
            ),
            'overdue_ranges' => $overdueRanges,
            'top_debtors' => array_slice($debtorsByCompany, 0, 20, true),
            'all_debtors' => $debtorsByCompany,
        ];
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
        
        if (is_array($currency) && isset($currency['kod'])) {
            return (string)$currency['kod'];
        }
        
        return (string)$currency;
    }

    /**
     * Get unpaid amount from invoice
     *
     * @param array<string, mixed> $invoice Invoice data
     * @param string $currency Currency code
     * @return float Unpaid amount
     */
    private function getUnpaidAmount(array $invoice, string $currency): float
    {
        if ($currency !== 'CZK') {
            return (float)($invoice['zbyvaUhraditMen'] ?? 0);
        }
        
        return (float)($invoice['zbyvaUhradit'] ?? 0);
    }

    /**
     * Calculate overdue days
     *
     * @param string $dateSplat Due date
     * @return int Days overdue (0 if not overdue)
     */
    private function calculateOverdueDays(string $dateSplat): int
    {
        if (empty($dateSplat)) {
            return 0;
        }

        try {
            $dueDate = new \DateTime($dateSplat);
            $today = new \DateTime();
            $diff = $today->diff($dueDate);
            
            return $dueDate < $today ? (int)$diff->days : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
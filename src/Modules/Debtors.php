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
 * Debtors analysis module.
 *
 * Analyzes unpaid overdue invoices and tracks debtors.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class Debtors extends AbstractModule
{
    protected string $moduleName = 'debtors';
    protected string $heading = 'Debtors';
    protected string $description = 'Analysis of unpaid invoices and overdue amounts by customer';
    protected array $requiredFeatures = ['payment_status', 'date_filtering'];

    /**
     * {@inheritDoc}
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        try {
            $unpaidInvoices = $provider->getData(
                DataProviderInterface::ENTITY_OUTCOMING_INVOICES,
                [
                    DataProviderInterface::FILTER_OVERDUE => true,
                    DataProviderInterface::FILTER_PAYMENT_STATUS => DataProviderInterface::PAYMENT_STATUS_UNPAID_OR_PARTIAL,
                    DataProviderInterface::FILTER_CANCELLED => false,
                    DataProviderInterface::FILTER_EXCLUDE_DOCUMENT_TYPE => DataProviderInterface::DOCUMENT_TYPE_CREDIT_NOTE,
                    DataProviderInterface::FILTER_LIMIT => 0,
                ],
            );

            if (empty($unpaidInvoices)) {
                return $this->createResult($period, true, [
                    'summary' => [
                        'total_debtors' => 0,
                        'total_unpaid_amount' => $this->formatCurrency(0.0),
                        'message' => 'No unpaid invoices found',
                    ],
                ]);
            }

            return $this->createResult($period, true, $this->analyzeDebtors($unpaidInvoices));
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
     * @param array<array<string, mixed>> $unpaidInvoices
     */
    private function analyzeDebtors(array $unpaidInvoices): array
    {
        $debtorsByCompany = [];
        $totalsByCurrency = [];
        $overdueRanges = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];

        foreach ($unpaidInvoices as $invoice) {
            $company = (string) ($invoice[DataProviderInterface::FIELD_COMPANY] ?? 'unknown');
            $currency = (string) ($invoice[DataProviderInterface::FIELD_CURRENCY] ?? 'CZK');
            $amount = $currency !== 'CZK'
                ? (float) ($invoice[DataProviderInterface::FIELD_REMAINING_AMOUNT_FOREIGN] ?? 0)
                : (float) ($invoice[DataProviderInterface::FIELD_REMAINING_AMOUNT] ?? 0);
            $overdueDays = self::calculateOverdueDays($invoice[DataProviderInterface::FIELD_DUE_DATE] ?? '');

            if (!isset($debtorsByCompany[$company])) {
                $debtorsByCompany[$company] = [
                    'company' => $company,
                    'invoices_count' => 0,
                    'total_amount' => [],
                    'overdue_days_max' => 0,
                ];
            }

            ++$debtorsByCompany[$company]['invoices_count'];

            if (!isset($debtorsByCompany[$company]['total_amount'][$currency])) {
                $debtorsByCompany[$company]['total_amount'][$currency] = 0.0;
            }

            $debtorsByCompany[$company]['total_amount'][$currency] += $amount;
            $debtorsByCompany[$company]['overdue_days_max'] = max(
                $debtorsByCompany[$company]['overdue_days_max'],
                $overdueDays,
            );

            $totalsByCurrency[$currency] = ($totalsByCurrency[$currency] ?? 0.0) + $amount;

            match (true) {
                $overdueDays <= 30 => $overdueRanges['0-30']++,
                $overdueDays <= 60 => $overdueRanges['31-60']++,
                $overdueDays <= 90 => $overdueRanges['61-90']++,
                default => $overdueRanges['90+']++,
            };
        }

        foreach ($debtorsByCompany as &$debtor) {
            $formatted = [];

            foreach ($debtor['total_amount'] as $currency => $amount) {
                $formatted[$currency] = $this->formatCurrency($amount, $currency);
            }

            $debtor['total_amount'] = $formatted;
        }

        uasort($debtorsByCompany, static function ($a, $b): int {
            $totalA = array_sum(array_column($a['total_amount'], 'amount'));
            $totalB = array_sum(array_column($b['total_amount'], 'amount'));

            return $totalB <=> $totalA;
        });

        $formattedTotals = [];

        foreach ($totalsByCurrency as $currency => $amount) {
            $formattedTotals[$currency] = $this->formatCurrency($amount, $currency);
        }

        return [
            'summary' => [
                'total_debtors' => \count($debtorsByCompany),
                'total_invoices' => \count($unpaidInvoices),
                'currencies' => array_keys($totalsByCurrency),
            ],
            'totals_by_currency' => $formattedTotals,
            'overdue_ranges' => $overdueRanges,
            'top_debtors' => \array_slice($debtorsByCompany, 0, 20, true),
            'all_debtors' => $debtorsByCompany,
        ];
    }

    private static function calculateOverdueDays(string $dueDateStr): int
    {
        if (empty($dueDateStr)) {
            return 0;
        }

        try {
            $dueDate = new \DateTime($dueDateStr);
            $today = new \DateTime();
            $diff = $today->diff($dueDate);

            return $dueDate < $today ? (int) $diff->days : 0;
        } catch (\Exception) {
            return 0;
        }
    }
}

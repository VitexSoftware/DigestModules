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
 * Waiting payments analysis module
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
                    'date_period' => ['column' => 'datSplat', 'period' => $period],
                    "(stavUhrK is null OR stavUhrK eq 'stavUhr.castUhr')",
                    'storno' => false,
                    'limit' => 0,
                ],
                ['kod', 'firma', 'sumCelkem', 'zbyvaUhradit', 'zbyvaUhraditMen', 'datSplat', 'mena'],
            );

            if (empty($invoices)) {
                return $this->createResult($period, true, [
                    'summary' => [
                        'total_count' => 0,
                        'total_amount' => $this->formatCurrency(0.0),
                    ],
                    'totals_by_currency' => [],
                    'invoices' => [],
                ]);
            }

            $totalsByCurrency = [];
            $invoiceList = [];

            foreach ($invoices as $invoice) {
                $currency = $this->extractCurrency($invoice);
                $remaining = ($currency === 'CZK')
                    ? (float) ($invoice['zbyvaUhradit'] ?? $invoice['sumCelkem'] ?? 0)
                    : (float) ($invoice['zbyvaUhraditMen'] ?? 0);

                $totalsByCurrency[$currency] = ($totalsByCurrency[$currency] ?? 0.0) + $remaining;

                $invoiceList[] = [
                    'code' => $invoice['kod'] ?? '',
                    'company' => (string) ($invoice['firma'] ?? ''),
                    'due_date' => (string) ($invoice['datSplat'] ?? ''),
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
                    'total_amount' => $this->formatCurrency(
                        $totalsByCurrency[$mainCurrency] ?? 0.0,
                        $mainCurrency,
                    ),
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

    /**
     * Extract currency code from invoice data
     */
    private function extractCurrency(array $invoice): string
    {
        $mena = $invoice['mena'] ?? 'CZK';

        return \is_string($mena) ? str_replace('code:', '', $mena) : 'CZK';
    }
}

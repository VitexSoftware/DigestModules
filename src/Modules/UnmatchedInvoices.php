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
 * Non-deducted proforma invoices analysis module
 *
 * Identifies paid proforma invoices that have not yet been
 * fully deducted (tax document not created).
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class UnmatchedInvoices extends AbstractModule
{
    protected string $moduleName = 'unmatched_invoices';
    protected string $heading = 'Non-deducted proformas';
    protected string $description = 'Paid proforma invoices not yet fully deducted';
    protected array $requiredFeatures = ['date_filtering', 'document_types'];

    /**
     * {@inheritDoc}
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        try {
            $proformas = $provider->getData(
                DataProviderInterface::ENTITY_OUTCOMING_INVOICES,
                [
                    'date_period' => ['column' => 'datVyst', 'period' => $period],
                    'storno' => false,
                    'zuctovano' => false,
                    'typDokl.typDoklK' => 'typDokladu.zalohFaktura',
                    'stavUhrK' => 'stavUhr.uhrazeno',
                    'limit' => 0,
                ],
                ['kod', 'mena', 'popis', 'sumCelkem', 'sumCelkemMen',
                    'stavOdpocetK', 'typDokl', 'firma', 'datVyst'],
            );

            $totalsByCurrency = [];
            $countsByCurrency = [];
            $invoiceList = [];

            foreach ($proformas as $proforma) {
                $deductionState = $proforma['stavOdpocetK'] ?? '';

                // Skip fully deducted or with tax document created
                if (\in_array($deductionState, ['stavOdp.komplet', 'stavOdp.vytvZdd'], true)) {
                    continue;
                }

                $currency = $this->extractCurrency($proforma);
                $amount = ($currency === 'CZK')
                    ? (float) ($proforma['sumCelkem'] ?? 0)
                    : (float) ($proforma['sumCelkemMen'] ?? 0);

                $totalsByCurrency[$currency] = ($totalsByCurrency[$currency] ?? 0.0) + $amount;
                $countsByCurrency[$currency] = ($countsByCurrency[$currency] ?? 0) + 1;

                $invoiceList[] = [
                    'code' => $proforma['kod'] ?? '',
                    'description' => $proforma['popis'] ?? '',
                    'deduction_state' => $deductionState,
                    'document_type' => (string) ($proforma['typDokl'] ?? ''),
                    'company' => (string) ($proforma['firma'] ?? ''),
                    'date' => (string) ($proforma['datVyst'] ?? ''),
                    'amount' => $this->formatCurrency($amount, $currency),
                ];
            }

            $formattedTotals = [];

            foreach ($totalsByCurrency as $currency => $total) {
                $formattedTotals[$currency] = [
                    'count' => $countsByCurrency[$currency],
                    'total' => $this->formatCurrency($total, $currency),
                ];
            }

            return $this->createResult($period, true, [
                'summary' => [
                    'total_count' => \count($invoiceList),
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
     * Extract currency code from invoice data
     */
    private function extractCurrency(array $invoice): string
    {
        $mena = $invoice['mena'] ?? 'CZK';

        return \is_string($mena) ? str_replace('code:', '', $mena) : 'CZK';
    }
}

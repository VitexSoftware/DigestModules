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
 * Outcoming payments analysis module
 *
 * Analyzes outgoing bank payments (expenses) for a given period,
 * providing totals grouped by currency.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class OutcomingPayments extends AbstractModule implements ZabbixOutputInterface
{
    protected string $moduleName = 'outcoming_payments';
    protected string $heading = 'Outcoming payments';
    protected string $description = 'Analysis of outgoing bank payments (expenses) by currency';
    protected array $requiredFeatures = ['date_filtering'];

    /**
     * {@inheritDoc}
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        try {
            $payments = $provider->getData(
                DataProviderInterface::ENTITY_BANK_STATEMENTS,
                [
                    'date_period' => ['column' => 'datVyst', 'period' => $period],
                    'typPohybuK' => 'typPohybu.vydej',
                    'storno' => false,
                    'limit' => 0,
                ],
                ['mena', 'sumCelkem', 'sumCelkemMen'],
            );

            $totalsByCurrency = [];

            foreach ($payments as $payment) {
                $currency = $this->extractCurrency($payment);
                $amount = ($currency === 'CZK')
                    ? (float) ($payment['sumCelkem'] ?? 0)
                    : (float) ($payment['sumCelkemMen'] ?? 0);

                $totalsByCurrency[$currency] = ($totalsByCurrency[$currency] ?? 0.0) + $amount;
            }

            $formattedTotals = [];

            foreach ($totalsByCurrency as $currency => $total) {
                $formattedTotals[$currency] = $this->formatCurrency($total, $currency);
            }

            return $this->createResult($period, true, [
                'summary' => [
                    'total_count' => \count($payments),
                    'currencies' => array_keys($totalsByCurrency),
                ],
                'totals_by_currency' => $formattedTotals,
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

        return [
            'outcoming_payments.count' => $data['summary']['total_count'] ?? 0,
        ];
    }

    /**
     * Extract currency code from payment data
     */
    private function extractCurrency(array $payment): string
    {
        $mena = $payment['mena'] ?? 'CZK';

        return \is_string($mena) ? str_replace('code:', '', $mena) : 'CZK';
    }
}

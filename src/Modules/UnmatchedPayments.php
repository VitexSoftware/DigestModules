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
 * Unmatched payments analysis module.
 *
 * Identifies incoming bank payments that have not been matched
 * to any invoice (unrecognized or undeducted earnings).
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class UnmatchedPayments extends AbstractModule implements ZabbixOutputInterface
{
    protected string $moduleName = 'unmatched_payments';
    protected string $heading = 'Unmatched payments';
    protected string $description = 'Incoming payments not matched to any invoice';
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
                    DataProviderInterface::FILTER_DATE_PERIOD => [
                        'column' => DataProviderInterface::DATE_COLUMN_ISSUE_DATE,
                        'period' => $period,
                    ],
                    DataProviderInterface::FILTER_PAYMENT_DIRECTION => DataProviderInterface::DIRECTION_INCOMING,
                    DataProviderInterface::FILTER_CANCELLED => false,
                    DataProviderInterface::FILTER_ACCOUNTED => false,
                    DataProviderInterface::FILTER_MATCHED => false,
                    DataProviderInterface::FILTER_LIMIT => 0,
                ],
            );

            $totalsByCurrency = [];
            $paymentList = [];

            foreach ($payments as $payment) {
                $currency = (string) ($payment[DataProviderInterface::FIELD_CURRENCY] ?? 'CZK');
                $amount = $currency !== 'CZK'
                    ? (float) ($payment[DataProviderInterface::FIELD_TOTAL_AMOUNT_FOREIGN] ?? 0)
                    : (float) ($payment[DataProviderInterface::FIELD_TOTAL_AMOUNT] ?? 0);

                $totalsByCurrency[$currency] = ($totalsByCurrency[$currency] ?? 0.0) + $amount;

                $paymentList[] = [
                    'code' => $payment[DataProviderInterface::FIELD_CODE] ?? '',
                    'description' => $payment[DataProviderInterface::FIELD_DESCRIPTION] ?? '',
                    'bank_account' => $payment[DataProviderInterface::FIELD_BANK_ACCOUNT] ?? '',
                    'company' => $payment[DataProviderInterface::FIELD_COMPANY] ?? '',
                    'date' => $payment[DataProviderInterface::FIELD_DATE] ?? '',
                    'amount' => $this->formatCurrency($amount, $currency),
                ];
            }

            $formattedTotals = [];

            foreach ($totalsByCurrency as $currency => $total) {
                $formattedTotals[$currency] = $this->formatCurrency($total, $currency);
            }

            $mainCurrency = array_key_first($totalsByCurrency) ?? 'CZK';

            return $this->createResult($period, true, [
                'summary' => [
                    'total_count' => \count($payments),
                    'total_amount' => $this->formatCurrency($totalsByCurrency[$mainCurrency] ?? 0.0, $mainCurrency),
                ],
                'totals_by_currency' => $formattedTotals,
                'payments' => $paymentList,
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
            'unmatched_payments.count' => $summary['total_count'] ?? 0,
            'unmatched_payments.total_amount' => $summary['total_amount']['amount'] ?? 0,
        ];
    }
}

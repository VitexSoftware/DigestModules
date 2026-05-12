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
 * Non-deducted proforma invoices analysis module.
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
                    DataProviderInterface::FILTER_DATE_PERIOD => [
                        'column' => DataProviderInterface::DATE_COLUMN_ISSUE_DATE,
                        'period' => $period,
                    ],
                    DataProviderInterface::FILTER_CANCELLED => false,
                    DataProviderInterface::FILTER_ACCOUNTED => false,
                    DataProviderInterface::FILTER_DOCUMENT_TYPE => DataProviderInterface::DOCUMENT_TYPE_PROFORMA,
                    DataProviderInterface::FILTER_PAYMENT_STATUS => DataProviderInterface::PAYMENT_STATUS_PAID,
                    DataProviderInterface::FILTER_LIMIT => 0,
                ],
            );

            $totalsByCurrency = [];
            $countsByCurrency = [];
            $invoiceList = [];

            foreach ($proformas as $proforma) {
                $deductionStatus = (string) ($proforma[DataProviderInterface::FIELD_DEDUCTION_STATUS] ?? '');

                // Skip fully deducted or with tax document created
                if (\in_array($deductionStatus, [
                    DataProviderInterface::DEDUCTION_STATUS_COMPLETE,
                    DataProviderInterface::DEDUCTION_STATUS_TAX_DOCUMENT_CREATED,
                ], true)) {
                    continue;
                }

                $currency = (string) ($proforma[DataProviderInterface::FIELD_CURRENCY] ?? 'CZK');
                $amount = $currency !== 'CZK'
                    ? (float) ($proforma[DataProviderInterface::FIELD_TOTAL_AMOUNT_FOREIGN] ?? 0)
                    : (float) ($proforma[DataProviderInterface::FIELD_TOTAL_AMOUNT] ?? 0);

                $totalsByCurrency[$currency] = ($totalsByCurrency[$currency] ?? 0.0) + $amount;
                $countsByCurrency[$currency] = ($countsByCurrency[$currency] ?? 0) + 1;

                $invoiceList[] = [
                    'code' => $proforma[DataProviderInterface::FIELD_CODE] ?? '',
                    'description' => $proforma[DataProviderInterface::FIELD_DESCRIPTION] ?? '',
                    'deduction_status' => $deductionStatus,
                    'document_type' => $proforma[DataProviderInterface::FIELD_DOCUMENT_TYPE] ?? '',
                    'company' => $proforma[DataProviderInterface::FIELD_COMPANY] ?? '',
                    'date' => $proforma[DataProviderInterface::FIELD_DATE] ?? '',
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
                'summary' => ['total_count' => \count($invoiceList)],
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
}

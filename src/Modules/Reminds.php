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
 * Payment reminders analysis module
 *
 * Analyzes payment reminders (1st, 2nd, pre-litigation) sent within
 * the analyzed period, grouped by reminder level.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class Reminds extends AbstractModule
{
    protected string $moduleName = 'reminds';
    protected string $heading = 'Reminders';
    protected string $description = 'Payment reminders sent within the analyzed period';
    protected array $requiredFeatures = ['date_filtering', 'payment_status'];

    /**
     * {@inheritDoc}
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        try {
            $invoices = $provider->getData(
                DataProviderInterface::ENTITY_REMINDERS,
                [
                    DataProviderInterface::FILTER_DATE_PERIOD => [
                        'column' => DataProviderInterface::DATE_COLUMN_FIRST_REMINDER,
                        'period' => $period,
                    ],
                    DataProviderInterface::FILTER_LIMIT => 0,
                ],
            );

            $reminderCounts = ['first' => 0, 'second' => 0, 'pre_litigation' => 0];
            $invoiceList    = [];

            foreach ($invoices as $invoice) {
                $currency  = (string) ($invoice[DataProviderInterface::FIELD_CURRENCY] ?? 'CZK');
                $remaining = $currency !== 'CZK'
                    ? (float) ($invoice[DataProviderInterface::FIELD_REMAINING_AMOUNT_FOREIGN] ?? 0)
                    : (float) ($invoice[DataProviderInterface::FIELD_REMAINING_AMOUNT] ?? 0);

                $firstDate  = $invoice[DataProviderInterface::FIELD_FIRST_REMINDER_DATE] ?? '';
                $secondDate = $invoice[DataProviderInterface::FIELD_SECOND_REMINDER_DATE] ?? '';
                $preDate    = $invoice[DataProviderInterface::FIELD_PRE_LITIGATION_DATE] ?? '';

                if (!empty($firstDate) && $this->isDateInPeriod($firstDate, $period)) {
                    ++$reminderCounts['first'];
                }

                if (!empty($secondDate) && $this->isDateInPeriod($secondDate, $period)) {
                    ++$reminderCounts['second'];
                }

                if (!empty($preDate) && $this->isDateInPeriod($preDate, $period)) {
                    ++$reminderCounts['pre_litigation'];
                }

                $invoiceList[] = [
                    'code'             => $invoice[DataProviderInterface::FIELD_CODE] ?? '',
                    'company'          => $invoice[DataProviderInterface::FIELD_COMPANY] ?? '',
                    'description'      => $invoice[DataProviderInterface::FIELD_DESCRIPTION] ?? '',
                    'remaining'        => $this->formatCurrency($remaining, $currency),
                    'first_reminder'   => $firstDate,
                    'second_reminder'  => $secondDate,
                    'pre_litigation'   => $preDate,
                ];
            }

            return $this->createResult($period, true, [
                'summary' => [
                    'total_invoices'  => \count($invoices),
                    'reminder_counts' => $reminderCounts,
                    'total_reminders' => array_sum($reminderCounts),
                ],
                'invoices' => $invoiceList,
            ], [
                'provider' => $provider->getSystemName(),
            ]);
        } catch (\Throwable $e) {
            return $this->createResult($period, false, [], [
                'provider' => $provider->getSystemName(),
                'error'    => $e->getMessage(),
            ]);
        }
    }

    private function isDateInPeriod(string $dateStr, \DatePeriod $period): bool
    {
        if (empty($dateStr)) {
            return false;
        }

        try {
            $date = new \DateTime($dateStr);

            return $date >= $period->getStartDate() && $date <= $period->getEndDate();
        } catch (\Exception) {
            return false;
        }
    }
}

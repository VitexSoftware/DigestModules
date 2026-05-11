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
            // Reminders are tracked on outcoming invoices via datUp1, datUp2, datSmir columns
            $invoices = $provider->getData(
                DataProviderInterface::ENTITY_REMINDERS,
                [
                    'date_period' => ['column' => 'datUp1', 'period' => $period],
                    'limit' => 0,
                ],
                ['kod', 'firma', 'popis', 'sumCelkem', 'sumCelkemMen',
                    'zbyvaUhradit', 'zbyvaUhraditMen', 'mena', 'datUp1', 'datUp2', 'datSmir'],
            );

            $reminderCounts = [
                'first' => 0,
                'second' => 0,
                'pre_litigation' => 0,
            ];
            $invoiceList = [];

            foreach ($invoices as $invoice) {
                $currency = $this->extractCurrency($invoice);
                $remaining = ($currency === 'CZK')
                    ? (float) ($invoice['zbyvaUhradit'] ?? 0)
                    : (float) ($invoice['zbyvaUhraditMen'] ?? 0);

                $hasFirst = !empty($invoice['datUp1']) && $this->isDateInPeriod($invoice['datUp1'], $period);
                $hasSecond = !empty($invoice['datUp2']) && $this->isDateInPeriod($invoice['datUp2'], $period);
                $hasPreLitigation = !empty($invoice['datSmir']) && $this->isDateInPeriod($invoice['datSmir'], $period);

                if ($hasFirst) {
                    ++$reminderCounts['first'];
                }

                if ($hasSecond) {
                    ++$reminderCounts['second'];
                }

                if ($hasPreLitigation) {
                    ++$reminderCounts['pre_litigation'];
                }

                $invoiceList[] = [
                    'code' => $invoice['kod'] ?? '',
                    'company' => (string) ($invoice['firma'] ?? ''),
                    'description' => $invoice['popis'] ?? '',
                    'remaining' => $this->formatCurrency($remaining, $currency),
                    'first_reminder' => $invoice['datUp1'] ?? null,
                    'second_reminder' => $invoice['datUp2'] ?? null,
                    'pre_litigation' => $invoice['datSmir'] ?? null,
                ];
            }

            return $this->createResult($period, true, [
                'summary' => [
                    'total_invoices' => \count($invoices),
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
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if a date falls within the given period
     *
     * @param mixed $date Date value (DateTime or string)
     * @param \DatePeriod $period Period to check against
     */
    private function isDateInPeriod(mixed $date, \DatePeriod $period): bool
    {
        try {
            $dateObj = ($date instanceof \DateTime) ? $date : new \DateTime((string) $date);

            return $dateObj >= $period->getStartDate() && $dateObj <= $period->getEndDate();
        } catch (\Exception) {
            return false;
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

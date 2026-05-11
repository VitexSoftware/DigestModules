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
 * Outcoming invoices hidden to customer module
 *
 * Identifies issued invoices that have not been emailed
 * to the customer (mail status is 'pending' or 'empty').
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class OutcomingInvoicesHiddenToCustomer extends AbstractModule
{
    protected string $moduleName = 'outcoming_invoices_hidden';
    protected string $heading = 'Issued invoices not notified to the client';
    protected string $description = 'Outcoming invoices with pending or missing email notification';
    protected array $requiredFeatures = ['date_filtering'];

    /**
     * {@inheritDoc}
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        try {
            $invoices = $provider->getData(
                DataProviderInterface::ENTITY_OUTCOMING_INVOICES,
                [
                    DataProviderInterface::FILTER_DATE_PERIOD => [
                        'column' => DataProviderInterface::DATE_COLUMN_ISSUE_DATE,
                        'period' => $period,
                    ],
                    DataProviderInterface::FILTER_MAIL_PENDING => true,
                    DataProviderInterface::FILTER_CANCELLED    => false,
                    DataProviderInterface::FILTER_LIMIT        => 0,
                ],
            );

            $invoiceList = [];

            foreach ($invoices as $invoice) {
                $mailStatus = $invoice[DataProviderInterface::FIELD_MAIL_STATUS] ?? DataProviderInterface::MAIL_STATUS_EMPTY;

                $invoiceList[] = [
                    'code'          => $invoice[DataProviderInterface::FIELD_CODE] ?? '',
                    'document_type' => $invoice[DataProviderInterface::FIELD_DOCUMENT_TYPE] ?? '',
                    'company'       => $invoice[DataProviderInterface::FIELD_COMPANY] ?? '',
                    'mail_status'   => $mailStatus,
                    'contact_email' => $invoice[DataProviderInterface::FIELD_CONTACT_EMAIL] ?? '',
                ];
            }

            return $this->createResult($period, true, [
                'summary'  => ['total_count' => \count($invoiceList)],
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
}

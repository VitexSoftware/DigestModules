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
 * Contacts without phone analysis module.
 *
 * Identifies customer/supplier contacts that are missing
 * a notification phone number.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class WithoutTel extends AbstractModule
{
    protected string $moduleName = 'without_tel';
    protected string $heading = 'Customers without notification phone number';
    protected string $description = 'Contacts missing phone number for notifications';

    /**
     * {@inheritDoc}
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        try {
            $contacts = $provider->getData(
                DataProviderInterface::ENTITY_CONTACTS,
                [
                    DataProviderInterface::FILTER_MISSING_PHONE => true,
                    DataProviderInterface::FILTER_RELATIONSHIP => 'customer',
                    DataProviderInterface::FILTER_LIMIT => 0,
                ],
            );

            $contactList = [];

            foreach ($contacts as $contact) {
                $contactList[] = [
                    'name' => $contact[DataProviderInterface::FIELD_NAME] ?? '',
                    'code' => $contact[DataProviderInterface::FIELD_CODE] ?? '',
                    'street' => $contact[DataProviderInterface::FIELD_STREET] ?? '',
                    'city' => $contact[DataProviderInterface::FIELD_CITY] ?? '',
                    'email' => $contact[DataProviderInterface::FIELD_EMAIL] ?? '',
                ];
            }

            return $this->createResult($period, true, [
                'summary' => ['total_count' => \count($contactList)],
                'contacts' => $contactList,
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

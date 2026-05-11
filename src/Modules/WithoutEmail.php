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
 * Contacts without email analysis module
 *
 * Identifies customer/supplier contacts that are missing
 * a notification email address.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class WithoutEmail extends AbstractModule
{
    protected string $moduleName = 'without_email';
    protected string $heading = 'Customers without notification email address';
    protected string $description = 'Contacts missing email address for notifications';

    /**
     * {@inheritDoc}
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        try {
            $contacts = $provider->getData(
                DataProviderInterface::ENTITY_CONTACTS,
                [
                    'email' => 'is empty',
                    "(typVztahuK='typVztahu.odberDodav' OR typVztahuK='typVztahu.odberatel')",
                    'limit' => 0,
                ],
                ['nazev', 'kod', 'ulice', 'mesto', 'tel'],
            );

            $contactList = [];

            foreach ($contacts as $contact) {
                $contactList[] = [
                    'name' => $contact['nazev'] ?? '',
                    'code' => $contact['kod'] ?? '',
                    'street' => $contact['ulice'] ?? '',
                    'city' => $contact['mesto'] ?? '',
                    'phone' => $contact['tel'] ?? '',
                ];
            }

            return $this->createResult($period, true, [
                'summary' => [
                    'total_count' => \count($contactList),
                ],
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

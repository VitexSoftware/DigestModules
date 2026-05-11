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
 * New customers analysis module
 *
 * Identifies contacts created or updated within the analyzed period.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class NewCustomers extends AbstractModule implements ZabbixOutputInterface
{
    protected string $moduleName = 'new_customers';
    protected string $heading = 'New or updated customers';
    protected string $description = 'Contacts created or updated within the analyzed period';
    protected array $requiredFeatures = ['date_filtering'];

    /**
     * {@inheritDoc}
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        try {
            $contacts = $provider->getData(
                DataProviderInterface::ENTITY_CONTACTS,
                [
                    'date_period' => ['column' => 'lastUpdate', 'period' => $period],
                    'limit' => 0,
                ],
                ['kod', 'nazev', 'tel', 'email'],
            );

            $customerList = [];

            foreach ($contacts as $pos => $contact) {
                $customerList[] = [
                    'position' => $pos + 1,
                    'code' => $contact['kod'] ?? '',
                    'name' => $contact['nazev'] ?? '',
                    'email' => $contact['email'] ?? '',
                    'phone' => $contact['tel'] ?? '',
                ];
            }

            return $this->createResult($period, true, [
                'summary' => [
                    'total_count' => \count($contacts),
                ],
                'customers' => $customerList,
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
        return [
            'new_customers.count' => $processedData['data']['summary']['total_count'] ?? 0,
        ];
    }
}

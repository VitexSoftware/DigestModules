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

namespace VitexSoftware\DigestModules\Providers;

use VitexSoftware\DigestModules\Core\DataProviderInterface;

/**
 * AbraFlexi data provider
 *
 * Provides data access to AbraFlexi accounting system
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class AbraFlexiDataProvider implements DataProviderInterface
{
    /**
     * AbraFlexi connection configuration
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Supported entities mapping
     *
     * @var array<string, string>
     */
    private array $entityMapping = [
        'outcoming_invoices' => 'faktura-vydana',
        'incoming_invoices' => 'faktura-prijata',
        'customers' => 'adresar',
        'payments' => 'banka',
        'products' => 'cenik',
        'contacts' => 'adresar',
        'orders' => 'objednavka-prijata',
    ];

    /**
     * Supported features
     *
     * @var array<string>
     */
    private array $supportedFeatures = [
        'date_filtering',
        'currency_conversion',
        'document_types',
        'payment_status',
        'custom_fields',
        'relations',
    ];

    /**
     * Constructor
     *
     * @param array<string, mixed> $config AbraFlexi connection configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function getData(string $entity, array $conditions = [], array $columns = []): array
    {
        // Map entity to AbraFlexi endpoint
        if (!isset($this->entityMapping[$entity])) {
            throw new \InvalidArgumentException("Unsupported entity: $entity");
        }

        $abraFlexiEntity = $this->entityMapping[$entity];

        // Create appropriate AbraFlexi class instance
        $abraFlexiClass = $this->createAbraFlexiInstance($abraFlexiEntity);

        if (!$abraFlexiClass) {
            throw new \RuntimeException("Could not create AbraFlexi instance for entity: $entity");
        }

        // Convert conditions to AbraFlexi format
        $abraFlexiConditions = $this->convertConditions($conditions);

        // Get data from AbraFlexi
        if (empty($columns)) {
            $data = $abraFlexiClass->getColumnsFromAbraFlexi(['*'], $abraFlexiConditions);
        } else {
            $data = $abraFlexiClass->getColumnsFromAbraFlexi($columns, $abraFlexiConditions);
        }

        return is_array($data) ? $data : [];
    }

    /**
     * {@inheritDoc}
     */
    public function getSystemName(): string
    {
        return 'abraflexi';
    }

    /**
     * {@inheritDoc}
     */
    public function getSupportedEntities(): array
    {
        return array_keys($this->entityMapping);
    }

    /**
     * {@inheritDoc}
     */
    public function supportsFeature(string $feature): bool
    {
        return in_array($feature, $this->supportedFeatures, true);
    }

    /**
     * {@inheritDoc}
     */
    public function getCompanyInfo(): array
    {
        try {
            if (class_exists('\\AbraFlexi\\Company')) {
                $company = new \AbraFlexi\Company();
                $companyData = $company->getFlexiData();
                
                if (is_array($companyData) && !empty($companyData)) {
                    $firstCompany = reset($companyData);
                    return [
                        'name' => $firstCompany['nazev'] ?? 'Unknown',
                        'code' => $firstCompany['dbNazev'] ?? '',
                        'system' => 'AbraFlexi',
                        'url' => $company->getApiURL() ?? '',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Fall back to basic info
        }

        return [
            'name' => 'AbraFlexi Company',
            'system' => 'AbraFlexi',
            'error' => 'Could not fetch company info',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function formatDate(\DateTime $date): string
    {
        // AbraFlexi uses ISO date format
        return $date->format('Y-m-d');
    }

    /**
     * {@inheritDoc}
     */
    public function formatDatePeriod(string $column, \DatePeriod $period): string
    {
        $start = $this->formatDate($period->getStartDate());
        $end = $this->formatDate($period->getEndDate());
        
        return "$column between '$start' and '$end'";
    }

    /**
     * Create AbraFlexi instance for entity
     *
     * @param string $entity AbraFlexi entity name
     * @return mixed AbraFlexi instance or null
     */
    private function createAbraFlexiInstance(string $entity)
    {
        // Map common entities to their AbraFlexi classes
        $classMapping = [
            'faktura-vydana' => '\\AbraFlexi\\FakturaVydana',
            'faktura-prijata' => '\\AbraFlexi\\FakturaPrijata',
            'adresar' => '\\AbraFlexi\\Adresar',
            'banka' => '\\AbraFlexi\\Banka',
            'cenik' => '\\AbraFlexi\\Cenik',
            'objednavka-prijata' => '\\AbraFlexi\\ObjednavkaPrijata',
        ];

        if (isset($classMapping[$entity]) && class_exists($classMapping[$entity])) {
            return new $classMapping[$entity]();
        }

        // Try generic RO (read-only) class
        if (class_exists('\\AbraFlexi\\RO')) {
            return new \AbraFlexi\RO(null, ['evidence' => $entity]);
        }

        return null;
    }

    /**
     * Convert conditions to AbraFlexi format
     *
     * @param array<string, mixed> $conditions Input conditions
     * @return array<string, mixed> AbraFlexi formatted conditions
     */
    private function convertConditions(array $conditions): array
    {
        $abraFlexiConditions = [];

        foreach ($conditions as $key => $value) {
            if ($key === 'limit') {
                $abraFlexiConditions['limit'] = $value;
            } elseif ($key === 'includes') {
                $abraFlexiConditions['includes'] = $value;
            } elseif ($key === 'date_period' && is_array($value)) {
                // Handle date period conditions
                if (isset($value['column'], $value['period'])) {
                    $condition = $this->formatDatePeriod($value['column'], $value['period']);
                    $abraFlexiConditions[] = $condition;
                }
            } elseif (is_string($value)) {
                $abraFlexiConditions[] = $value;
            } else {
                $abraFlexiConditions[$key] = $value;
            }
        }

        return $abraFlexiConditions;
    }
}
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

namespace VitexSoftware\DigestModules\Core;

/**
 * Interface for data providers (accounting systems)
 *
 * Data providers abstract the connection to different accounting systems
 * like AbraFlexi, Pohoda, Money S3, etc.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
interface DataProviderInterface
{
    /**
     * Get data from the accounting system
     *
     * @param string $entity Entity type (invoices, customers, etc.)
     * @param array<string, mixed> $conditions Query conditions
     * @param array<string> $columns Columns to retrieve
     * @return array<array<string, mixed>> Raw data from the system
     */
    public function getData(string $entity, array $conditions = [], array $columns = []): array;

    /**
     * Get system name/type
     *
     * @return string Name of the accounting system (abraflexi, pohoda, etc.)
     */
    public function getSystemName(): string;

    /**
     * Get supported entities
     *
     * @return array<string> List of supported entity types
     */
    public function getSupportedEntities(): array;

    /**
     * Check if provider supports a specific feature
     *
     * @param string $feature Feature name
     * @return bool Whether the feature is supported
     */
    public function supportsFeature(string $feature): bool;

    /**
     * Get company information
     *
     * @return array<string, mixed> Company details
     */
    public function getCompanyInfo(): array;

    /**
     * Format date for the accounting system
     *
     * @param \DateTime $date Date to format
     * @return string Formatted date string
     */
    public function formatDate(\DateTime $date): string;

    /**
     * Format date period condition for queries
     *
     * @param string $column Date column name
     * @param \DatePeriod $period Time period
     * @return string Formatted condition
     */
    public function formatDatePeriod(string $column, \DatePeriod $period): string;
}
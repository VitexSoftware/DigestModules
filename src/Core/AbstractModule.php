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
 * Abstract base class for digest modules.
 *
 * Provides common functionality for all data collection modules
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
abstract class AbstractModule implements ModuleInterface
{
    /**
     * Module name/identifier.
     */
    protected string $moduleName;

    /**
     * Module heading/display name.
     */
    protected string $heading;

    /**
     * Module description.
     */
    protected string $description;

    /**
     * Required provider features.
     *
     * @var array<string>
     */
    protected array $requiredFeatures = [];

    /**
     * Constructor.
     *
     * @param string $moduleName  Module identifier
     * @param string $heading     Display name
     * @param string $description Module description
     */
    public function __construct(
        string $moduleName = '',
        string $heading = '',
        string $description = '',
    ) {
        if ($moduleName) {
            $this->moduleName = $moduleName;
        }

        if ($heading) {
            $this->heading = $heading;
        }

        if ($description) {
            $this->description = $description;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getModuleName(): string
    {
        return $this->moduleName ?: strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', basename(str_replace('\\', '/', static::class))));
    }

    /**
     * {@inheritDoc}
     */
    public function getHeading(): string
    {
        return $this->heading ?: ucwords(str_replace('_', ' ', $this->getModuleName()));
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return $this->description ?: 'Analysis module: '.$this->getHeading();
    }

    /**
     * {@inheritDoc}
     */
    public function getRequiredFeatures(): array
    {
        return $this->requiredFeatures;
    }

    /**
     * Create base result structure.
     *
     * @param \DatePeriod          $period   Analysis period
     * @param bool                 $success  Processing success flag
     * @param array<string, mixed> $data     Module data
     * @param array<string, mixed> $metadata Additional metadata
     *
     * @return array<string, mixed>
     */
    protected function createResult(
        \DatePeriod $period,
        bool $success,
        array $data = [],
        array $metadata = [],
    ): array {
        return [
            'module_name' => $this->getModuleName(),
            'heading' => $this->getHeading(),
            'description' => $this->getDescription(),
            'period' => [
                'start' => $period->getStartDate()->format('Y-m-d'),
                'end' => $period->getEndDate()->format('Y-m-d'),
            ],
            'success' => $success,
            'data' => $data,
            'metadata' => array_merge([
                'timestamp' => (new \DateTime())->format(\DateTimeInterface::ATOM),
                'provider' => null,
            ], $metadata),
        ];
    }

    /**
     * Format currency value.
     *
     * @param float  $amount   Amount to format
     * @param string $currency Currency code
     *
     * @return array<string, mixed> Formatted amount data
     */
    protected function formatCurrency(float $amount, string $currency = 'CZK'): array
    {
        return [
            'amount' => $amount,
            'currency' => $currency,
            'formatted' => number_format($amount, 2, ',', ' ').' '.$currency,
        ];
    }

    /**
     * Calculate percentage.
     *
     * @param float $value Current value
     * @param float $total Total value
     *
     * @return float Percentage (0-100)
     */
    protected function calculatePercentage(float $value, float $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 2) : 0.0;
    }
}

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
 * Interface for digest data modules.
 *
 * All data collection modules must implement this interface
 * to ensure consistent data structure and processing
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
interface ModuleInterface
{
    /**
     * Process data for the given period using the provided data source.
     *
     * @param DataProviderInterface $provider Data source provider (AbraFlexi, Pohoda, etc.)
     * @param \DatePeriod           $period   Time period to analyze
     *
     * @return array<string, mixed> Structured data array with module results
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array;

    /**
     * Get module name/identifier.
     *
     * @return string Module name used for identification and output keys
     */
    public function getModuleName(): string;

    /**
     * Get human-readable module heading.
     *
     * @return string Display name for the module
     */
    public function getHeading(): string;

    /**
     * Get module description.
     *
     * @return string Brief description of what this module analyzes
     */
    public function getDescription(): string;

    /**
     * Get required data provider features.
     *
     * @return array<string> List of required provider capabilities
     */
    public function getRequiredFeatures(): array;
}

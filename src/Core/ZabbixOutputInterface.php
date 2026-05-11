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
 * Interface for modules that can export data as Zabbix metrics
 *
 * Modules implementing this interface can provide key→value pairs
 * suitable for consumption by zabbix_agent2 UserParameters.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
interface ZabbixOutputInterface
{
    /**
     * Convert module result data to Zabbix item key→value pairs
     *
     * The returned array should use Zabbix item keys as array keys
     * and scalar values (int, float, string) as values.
     *
     * Example return:
     * [
     *     'debtors.count' => 5,
     *     'debtors.total_amount' => 125000.50,
     *     'debtors.max_overdue_days' => 45,
     * ]
     *
     * The consuming application (abraflexi-zabbix, pohoda-zabbix) will
     * prepend the system prefix (e.g. 'abraflexi.digest.' or 'pohoda.digest.')
     *
     * @param array<string, mixed> $processedData Result from process() method
     *
     * @return array<string, int|float|string> Zabbix item key→value pairs
     */
    public function toZabbixItems(array $processedData): array;
}

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
 * Best selling products analysis module
 *
 * Identifies top selling products/services by analyzing invoice
 * line items within the given period. Requires 'relations' feature.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class BestSellers extends AbstractModule
{
    protected string $moduleName = 'best_sellers';
    protected string $heading = 'Best selling products';
    protected string $description = 'Top selling products/services by invoice line items';
    protected array $requiredFeatures = ['date_filtering', 'relations'];

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
                    DataProviderInterface::FILTER_WITH_ITEMS => true,
                    DataProviderInterface::FILTER_LIMIT      => 0,
                ],
            );

            $products = [];
            $totals   = [];

            foreach ($invoices as $invoice) {
                $items = $invoice[DataProviderInterface::FIELD_ITEMS] ?? [];

                if (!is_array($items)) {
                    continue;
                }

                foreach ($items as $item) {
                    if (($item['item_type'] ?? '') !== DataProviderInterface::ITEM_TYPE_CATALOG) {
                        continue;
                    }

                    $itemIdent = !empty($item['product_code'])
                        ? (string) $item['product_code']
                        : ($item['name'] ?? _('Unknown'));

                    $products[$itemIdent] = ($products[$itemIdent] ?? 0) + 1;
                    $totals[$itemIdent]   = ($totals[$itemIdent] ?? 0.0) + (float) ($item['amount'] ?? 0);
                }
            }

            arsort($products);

            $productList = [];

            foreach ($products as $code => $count) {
                if ($count <= 1) {
                    continue;
                }

                $productList[] = [
                    'code'     => $code,
                    'quantity' => $count,
                    'total'    => $totals[$code] ?? 0.0,
                ];
            }

            return $this->createResult($period, true, [
                'summary'  => ['total_products' => \count($productList)],
                'products' => $productList,
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

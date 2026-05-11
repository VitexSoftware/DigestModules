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

namespace VitexSoftware\DigestModules\Modules\AllTime;

use VitexSoftware\DigestModules\Core\AbstractModule;
use VitexSoftware\DigestModules\Core\DataProviderInterface;

/**
 * Purchase price vs. sales price analysis module
 *
 * Identifies products where the purchase (buy) price is higher
 * than the sales (sell) price — indicating a potential loss.
 * This module ignores the time period and analyzes the full catalog.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class PurchasePriceLowerThanSales extends AbstractModule
{
    protected string $moduleName = 'purchase_price_lower_than_sales';
    protected string $heading = 'Product purchase price lower than sales';
    protected string $description = 'Products where buy price exceeds sell price (potential loss)';

    /**
     * {@inheritDoc}
     */
    public function process(DataProviderInterface $provider, \DatePeriod $period): array
    {
        try {
            $productsRaw = $provider->getData(
                DataProviderInterface::ENTITY_PRODUCTS,
                [
                    'nakupCena' => 'is not empty',
                    'cenaZakl' => 'is not empty',
                    'limit' => 0,
                ],
                ['kod', 'nazev', 'nakupCena', 'cenaZakl'],
            );

            $disadvantageous = [];

            foreach ($productsRaw as $product) {
                $buyPrice = (float) ($product['nakupCena'] ?? 0);
                $sellPrice = (float) ($product['cenaZakl'] ?? 0);

                if ($buyPrice > $sellPrice && $sellPrice > 0) {
                    $disadvantageous[] = [
                        'code' => $product['kod'] ?? '',
                        'name' => $product['nazev'] ?? '',
                        'buy_price' => $buyPrice,
                        'sell_price' => $sellPrice,
                        'difference' => $buyPrice - $sellPrice,
                    ];
                }
            }

            // Sort by difference descending
            usort($disadvantageous, static fn ($a, $b) => $b['difference'] <=> $a['difference']);

            return $this->createResult($period, true, [
                'summary' => [
                    'total_count' => \count($disadvantageous),
                ],
                'products' => $disadvantageous,
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

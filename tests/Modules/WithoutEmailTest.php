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

namespace VitexSoftware\DigestModules\Tests\Modules;

use PHPUnit\Framework\TestCase;
use VitexSoftware\DigestModules\Core\DataProviderInterface;
use VitexSoftware\DigestModules\Modules\WithoutEmail;

class WithoutEmailTest extends TestCase
{
    public function testRequestsOnlyCustomersWithoutEmail(): void
    {
        $captured = null;
        $provider = $this->createMock(DataProviderInterface::class);
        $provider->method('getSystemName')->willReturn('test');
        $provider->method('supportsFeature')->willReturn(true);
        $provider->method('getData')->willReturnCallback(
            static function (string $entity, array $conditions) use (&$captured): array {
                $captured = $conditions;

                return [[
                    DataProviderInterface::FIELD_NAME => 'ACME',
                    DataProviderInterface::FIELD_CODE => 'ACME',
                ]];
            },
        );

        $result = (new WithoutEmail())->process($provider, new \DatePeriod(
            new \DateTime('2024-01-01'),
            new \DateInterval('P1D'),
            new \DateTime('2024-01-02'),
        ));

        $this->assertTrue($result['success']);
        $this->assertTrue($captured[DataProviderInterface::FILTER_MISSING_EMAIL]);
        $this->assertSame(
            DataProviderInterface::RELATIONSHIP_CUSTOMER,
            $captured[DataProviderInterface::FILTER_RELATIONSHIP],
        );
        $this->assertSame(1, $result['data']['summary']['total_count']);
    }
}

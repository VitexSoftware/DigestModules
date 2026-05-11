<?php

declare(strict_types=1);

namespace VitexSoftware\DigestModules\Tests\Core;

use PHPUnit\Framework\TestCase;
use VitexSoftware\DigestModules\Core\DataProviderInterface;
use VitexSoftware\DigestModules\Core\ModuleInterface;
use VitexSoftware\DigestModules\Core\ModuleRunner;

class ModuleRunnerTest extends TestCase
{
    private ModuleRunner $runner;
    private DataProviderInterface $mockProvider;

    protected function setUp(): void
    {
        $this->mockProvider = $this->createMock(DataProviderInterface::class);
        $this->mockProvider->method('getSystemName')->willReturn('test');
        $this->mockProvider->method('supportsFeature')->willReturn(true);
        $this->mockProvider->method('getCompanyInfo')->willReturn([
            'name' => 'Test Company',
            'ico' => '12345678',
        ]);

        $this->runner = new ModuleRunner($this->mockProvider);
    }

    public function testConstructor(): void
    {
        $this->assertSame($this->mockProvider, $this->runner->getDataProvider());
        $this->assertSame([], $this->runner->getModules());
    }

    public function testAddModuleInstance(): void
    {
        $module = $this->createMock(ModuleInterface::class);
        $module->method('getRequiredFeatures')->willReturn([]);

        $result = $this->runner->addModule('test', $module);

        $this->assertSame($this->runner, $result);
        $this->assertCount(1, $this->runner->getModules());
        $this->assertSame($module, $this->runner->getModules()['test']);
    }

    public function testAddModuleUnsupportedFeature(): void
    {
        $provider = $this->createMock(DataProviderInterface::class);
        $provider->method('supportsFeature')->willReturn(false);
        $provider->method('getSystemName')->willReturn('test');

        $runner = new ModuleRunner($provider);

        $module = $this->createMock(ModuleInterface::class);
        $module->method('getRequiredFeatures')->willReturn(['unsupported_feature']);
        $module->method('getModuleName')->willReturn('test_module');

        $this->expectException(\InvalidArgumentException::class);
        $runner->addModule('test', $module);
    }

    public function testRunWithNoModules(): void
    {
        $period = new \DatePeriod(
            new \DateTime('2024-01-01'),
            new \DateInterval('P1D'),
            new \DateTime('2024-02-01'),
        );

        $result = $this->runner->run($period);

        $this->assertArrayHasKey('digest', $result);
        $this->assertArrayHasKey('modules', $result);
        $this->assertArrayHasKey('benchmarks', $result);
        $this->assertSame([], $result['modules']);
        $this->assertSame('test', $result['digest']['provider']);
        $this->assertSame('2024-01-01', $result['digest']['period']['start']);
        $this->assertSame('2024-02-01', $result['digest']['period']['end']);
    }

    public function testRunWithModule(): void
    {
        $module = $this->createMock(ModuleInterface::class);
        $module->method('getRequiredFeatures')->willReturn([]);
        $module->method('getModuleName')->willReturn('test_mod');
        $module->method('getHeading')->willReturn('Test');
        $module->method('process')->willReturn([
            'module_name' => 'test_mod',
            'success' => true,
            'data' => ['count' => 5],
            'metadata' => ['provider' => null],
        ]);

        $this->runner->addModule('test_mod', $module);

        $period = new \DatePeriod(
            new \DateTime('2024-01-01'),
            new \DateInterval('P1D'),
            new \DateTime('2024-02-01'),
        );

        $result = $this->runner->run($period);

        $this->assertArrayHasKey('test_mod', $result['modules']);
        $this->assertTrue($result['modules']['test_mod']['success']);
        $this->assertSame('test', $result['modules']['test_mod']['metadata']['provider']);
        $this->assertArrayHasKey('test_mod', $result['benchmarks']);
        $this->assertArrayHasKey('duration', $result['benchmarks']['test_mod']);
    }

    public function testRunWithFailingModule(): void
    {
        $module = $this->createMock(ModuleInterface::class);
        $module->method('getRequiredFeatures')->willReturn([]);
        $module->method('getModuleName')->willReturn('failing');
        $module->method('getHeading')->willReturn('Failing Module');
        $module->method('process')->willThrowException(new \RuntimeException('Connection failed'));

        $this->runner->addModule('failing', $module);

        $period = new \DatePeriod(
            new \DateTime('2024-01-01'),
            new \DateInterval('P1D'),
            new \DateTime('2024-02-01'),
        );

        $result = $this->runner->run($period);

        $this->assertFalse($result['modules']['failing']['success']);
        $this->assertSame('Connection failed', $result['modules']['failing']['error']['message']);
        $this->assertSame('RuntimeException', $result['modules']['failing']['error']['type']);
    }
}

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
 * Module runner - orchestrates data collection from multiple modules
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class ModuleRunner
{
    /**
     * Data provider instance
     */
    private DataProviderInterface $dataProvider;

    /**
     * Registered modules
     *
     * @var array<string, ModuleInterface>
     */
    private array $modules = [];

    /**
     * Processing benchmarks
     *
     * @var array<string, array<string, float>>
     */
    private array $benchmarks = [];

    /**
     * Constructor
     *
     * @param DataProviderInterface $dataProvider Data source provider
     */
    public function __construct(DataProviderInterface $dataProvider)
    {
        $this->dataProvider = $dataProvider;
    }

    /**
     * Add a module to the runner
     *
     * @param string $key Module key/identifier
     * @param ModuleInterface|string $module Module instance or class name
     * @return self
     */
    public function addModule(string $key, ModuleInterface|string $module): self
    {
        if (is_string($module)) {
            $module = new $module();
        }

        // Check if provider supports required features
        foreach ($module->getRequiredFeatures() as $feature) {
            if (!$this->dataProvider->supportsFeature($feature)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Data provider "%s" does not support required feature "%s" for module "%s"',
                        $this->dataProvider->getSystemName(),
                        $feature,
                        $module->getModuleName()
                    )
                );
            }
        }

        $this->modules[$key] = $module;

        return $this;
    }

    /**
     * Run all registered modules for the given period
     *
     * @param \DatePeriod $period Time period to analyze
     * @return array<string, mixed> Complete results from all modules
     */
    public function run(\DatePeriod $period): array
    {
        $results = [
            'digest' => [
                'period' => [
                    'start' => $period->getStartDate()->format('Y-m-d'),
                    'end' => $period->getEndDate()->format('Y-m-d'),
                ],
                'provider' => $this->dataProvider->getSystemName(),
                'timestamp' => (new \DateTime())->format(\DateTimeInterface::ATOM),
                'company' => $this->dataProvider->getCompanyInfo(),
            ],
            'modules' => [],
            'benchmarks' => [],
        ];

        foreach ($this->modules as $key => $module) {
            $this->startTimer($key);

            try {
                $moduleResult = $module->process($this->dataProvider, $period);
                $moduleResult['metadata']['provider'] = $this->dataProvider->getSystemName();
                $results['modules'][$key] = $moduleResult;
            } catch (\Throwable $e) {
                $results['modules'][$key] = [
                    'module_name' => $module->getModuleName(),
                    'heading' => $module->getHeading(),
                    'success' => false,
                    'error' => [
                        'message' => $e->getMessage(),
                        'type' => get_class($e),
                    ],
                    'period' => [
                        'start' => $period->getStartDate()->format('Y-m-d'),
                        'end' => $period->getEndDate()->format('Y-m-d'),
                    ],
                ];
            }

            $this->stopTimer($key);
        }

        $results['benchmarks'] = $this->benchmarks;

        return $results;
    }

    /**
     * Get registered modules
     *
     * @return array<string, ModuleInterface>
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Get data provider
     *
     * @return DataProviderInterface
     */
    public function getDataProvider(): DataProviderInterface
    {
        return $this->dataProvider;
    }

    /**
     * Start timing a module
     *
     * @param string $moduleKey Module key
     */
    private function startTimer(string $moduleKey): void
    {
        $this->benchmarks[$moduleKey] = ['start' => microtime(true)];
    }

    /**
     * Stop timing a module
     *
     * @param string $moduleKey Module key
     */
    private function stopTimer(string $moduleKey): void
    {
        $this->benchmarks[$moduleKey]['end'] = microtime(true);
        $this->benchmarks[$moduleKey]['duration'] = 
            $this->benchmarks[$moduleKey]['end'] - $this->benchmarks[$moduleKey]['start'];
    }
}
<?php

namespace AbuseIO\Collectors;

use Composer\Autoload\ClassMapGenerator;
use Illuminate\Support\Arr;
use Log;

/**
 * Class Factory
 * @package AbuseIO\Collectors
 */
class Factory
{
    /**
     * Create a new Factory instance
     */
    public function __construct()
    {
        //
    }

    /**
     * Get a list of installed AbuseIO collectors and return as an array
     *
     * @return array
     */
    public static function getCollectors()
    {
        $collectorClassList = ClassMapGenerator::createMap(base_path().'/vendor/abuseio');
        /** @noinspection PhpUnusedParameterInspection */
        $collectorClassListFiltered = Arr::where(
            array_keys($collectorClassList),
            function ($value, $key) {
                // Get all collectors, ignore all other packages.
                if (strpos($value, 'AbuseIO\Collectors\\') !== false) {
                    return $value;
                }
                return false;
            }
        );

        $collectors = [];
        $collectorList = array_map('class_basename', $collectorClassListFiltered);
        foreach ($collectorList as $collector) {
            if (!in_array($collector, ['Factory', 'Collector'])) {
                $collectors[] = $collector;
            }
        }
        return $collectors;
    }

    /**
     * Create and return a Collector object and it's configuration
     *
     * @param string $requiredName
     * @return object
     */
    public static function create($requiredName)
    {
        /**
         * Loop through the collector list and try to find a match by name
         */
        $collectors = Factory::getCollectors();
        foreach ($collectors as $collectorName) {

            if ($collectorName === ucfirst($requiredName)) {
                $collectorClass = 'AbuseIO\\Collectors\\' . $collectorName;

                // Collector is enabled, then return its object
                if (config("collectors.{$collectorName}.collector.enabled") === true) {

                    return new $collectorClass();

                } else {
                    Log::info(
                        'AbuseIO\Collectors\Factory: ' .
                        "The collector {$collectorName} has been disabled and will not be used."
                    );
                }
            }
        }

        // No valid collectors found
        Log::info(
            'AbuseIO\Collectors\Factory: ' .
            "The collector {$requiredName} is not present on this system"
        );
        return false;
    }
}

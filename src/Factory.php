<?php

namespace AbuseIO\Collectors;

use Symfony\Component\ClassLoader\ClassMapGenerator;

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
     * @return array
     */
    public static function getCollectors()
    {
        $collectorClassList = ClassMapGenerator::createMap(base_path().'/vendor/abuseio');
        $collectorClassListFiltered = array_where(
            array_keys($collectorClassList),
            function ($key, $value) {
                // Get all collectors, ignore all other packages.
                if (strpos($value, 'AbuseIO\Collectors\\') !== false) {
                    return $value;
                }
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
}

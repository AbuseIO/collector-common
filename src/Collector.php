<?php

namespace AbuseIO\Collectors;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use ReflectionClass;
use Uuid;
use Log;

/**
 * Class Collector
 * @package AbuseIO\Collectors
 */
class Collector
{
    /**
     * Configuration Basename (collector name)
     * @var String
     */
    public $configBase;

    /**
     * Filesystem object
     * @var Object
     */
    public $fs;

    /**
     * Temporary working dir
     * @var String
     */
    public $tempPath;

    /**
     * Contains the name of the currently used feed within the collector
     * @var String
     */
    public $feedName;

    /**
     * Contains an array of found incidents that need to be handled
     * @var array
     */
    public $incidents = [ ];

    /**
     * Warning counter
     * @var Integer
     */
    public $warningCount = 0;

    /**
     * Create a new Collector instance
     *
     * @param  object $collector
     */
    public function __construct($collector)
    {
        $this->startup($collector);
    }

    /**
     * Generalize the local config based on the collector class object
     *
     * @param  object $collector
     * @return void
     */
    protected function startup($collector)
    {
        $reflect = new ReflectionClass($collector);

        $this->configBase = 'collectors.' . $reflect->getShortName();

        if (empty(config("{$this->configBase}.collector.name"))) {
            $this->failed("Required collector.name is missing in collector configuration");
        }

        Log::info(
            get_class($this) . ': ' .
            'Collector startup initiated for collector : ' . config("{$this->configBase}.collector.name")
        );

    }

    /**
     * Return failed
     * @param  string $message
     * @return array
     */
    protected function failed($message)
    {
        $this->cleanup();

        Log::warning(
            get_class($this) . ': ' .
            'Collector ' . config("{$this->configBase}.collector.name") . ' has ended with errors. ' . $message
        );

        return [
            'errorStatus'   => true,
            'errorMessage'  => $message,
            'warningCount'  => $this->warningCount,
            'data'          => false,
        ];
    }

    /**
     * Return success
     * @return array
     */
    protected function success()
    {
        $this->cleanup();

        if (empty($this->incidents)) {
            Log::warning(
                'The collector ' . config("{$this->configBase}.collector.name") . ' did not return any incidents ' .
                'which should be investigated for collector and/or configuration errors'
            );
        }

        Log::info(
            get_class($this) . ': ' .
            'Collector run completed for collector : ' . config("{$this->configBase}.collector.name")
        );

        return [
            'errorStatus'   => false,
            'errorMessage'  => 'Data successfully collected',
            'warningCount'  => $this->warningCount,
            'data'          => $this->incidents,
        ];
    }

    /**
     * Cleanup anything a collector might have left (basically, remove the working dir)
     * @return void
     */
    protected function cleanup()
    {
        // if $this->fs is an object, the Filesystem has been used, clean it up.
        if (is_object($this->fs)) {
            if ($this->fs->isDirectory($this->tempPath)) {
                $this->fs->deleteDirectory($this->tempPath, false);
            }
        }
    }

    /**
     * Setup a working directory for the collector
     * @return Boolean Returns true or call $this->failed()
     */
    protected function createWorkingDir()
    {
        $uuid = Uuid::generate(4);
        $this->tempPath = "/tmp/abuseio-{$uuid}/";
        $this->fs = new Filesystem;

        if (!$this->fs->makeDirectory($this->tempPath)) {
            return $this->failed("Unable to create directory {$this->tempPath}");
        }

        return true;
    }

    /**
     * Check if the feed specified is known in the collector config.
     * @return Boolean Returns true or false
     */
    protected function isKnownFeed()
    {
        if (empty(config("{$this->configBase}.feeds.{$this->feedName}"))) {
            $this->warningCount++;
            Log::warning(
                "The feed referred as '{$this->feedName}' is not configured in the collector " .
                config("{$this->configBase}.collector.name") .
                ' therefore skipping processing of this e-mail'
            );
            return false;
        } else {
            return true;
        }
    }

    /**
     * Check and see if a feed is enabled.
     * @return Boolean Returns true or false
     */
    protected function isEnabledFeed()
    {
        if (config("{$this->configBase}.feeds.{$this->feedName}.enabled") !== true) {
            Log::warning(
                "The feed '{$this->feedName}' is disabled in the configuration of collector " .
                config("{$this->configBase}.collector.name") .
                ' therefore skipping processing of this e-mail'
            );
        }
        return true;
    }

    /**
     * Check if all required fields are in the report.
     * @param  array   $report Report data
     * @return boolean         Returns true or false
     */
    protected function hasRequiredFields($report)
    {
        if (is_array(config("{$this->configBase}.feeds.{$this->feedName}.fields"))) {
            $columns = Arr::filter(config("{$this->configBase}.feeds.{$this->feedName}.fields"));
            if (count($columns) > 0) {
                foreach ($columns as $column) {
                    if (!isset($report[$column])) {
                        Log::warning(
                            config("{$this->configBase}.collector.name") . " feed '{$this->feedName}' " .
                            "says $column is required but is missing, therefore skipping processing of this incidents"
                        );
                        $this->warningCount++;
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Filter the unwanted and empty fields from the report.
     * @param  array   $report      The report that needs filtering base on config elements
     * @param  boolean $removeEmpty Option to remove empty fields from report, default is true
     * @return array   $report      The filtered version of the report
     */
    protected function applyFilters($report, $removeEmpty = true)
    {
        if ((!empty(config("{$this->configBase}.feeds.{$this->feedName}.filters"))) &&
            (is_array(config("{$this->configBase}.feeds.{$this->feedName}.filters")))
        ) {
            $filter_columns = Arr::filter(config("{$this->configBase}.feeds.{$this->feedName}.filters"));
            foreach ($filter_columns as $column) {
                if (!empty($report[$column])) {
                    unset($report[$column]);
                }
            }
        }

        // No sense in adding empty fields, so we remove them
        if ($removeEmpty) {
            foreach ($report as $field => $value) {
                if ($value == "") {
                    unset($report[$field]);
                }
            }
        }

        return $report;
    }
}

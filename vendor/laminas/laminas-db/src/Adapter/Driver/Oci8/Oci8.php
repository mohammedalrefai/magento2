<?php

namespace Laminas\Db\Adapter\Driver\Oci8;

use Laminas\Db\Adapter\Driver\DriverInterface;
use Laminas\Db\Adapter\Driver\Feature\AbstractFeature;
use Laminas\Db\Adapter\Exception;
use Laminas\Db\Adapter\Profiler;

use function array_intersect_key;
use function array_merge;
use function extension_loaded;
use function get_resource_type;
use function is_array;
use function is_resource;
use function is_string;

class Oci8 implements DriverInterface, Profiler\ProfilerAwareInterface
{
    public const FEATURES_DEFAULT = 'default';

    /** @var Connection */
    protected $connection;

    /** @var Statement */
    protected $statementPrototype;

    /** @var Result */
    protected $resultPrototype;

    /** @var Profiler\ProfilerInterface */
    protected $profiler;

    /** @var array */
    protected $options = [];

    /** @var array */
    protected $features = [];

    /**
     * @param array|Connection|\oci8 $connection
     * @param array $options
     * @param string $features
     */
    public function __construct(
        $connection,
        ?Statement $statementPrototype = null,
        ?Result $resultPrototype = null,
        array $options = [],
        $features = self::FEATURES_DEFAULT
    ) {
        if (! $connection instanceof Connection) {
            $connection = new Connection($connection);
        }

        $options = array_intersect_key(array_merge($this->options, $options), $this->options);
        $this->registerConnection($connection);
        $this->registerStatementPrototype($statementPrototype ?: new Statement());
        $this->registerResultPrototype($resultPrototype ?: new Result());
        if (is_array($features)) {
            foreach ($features as $name => $feature) {
                $this->addFeature($name, $feature);
            }
        } elseif ($features instanceof AbstractFeature) {
            $this->addFeature($features->getName(), $features);
        } elseif ($features === self::FEATURES_DEFAULT) {
            $this->setupDefaultFeatures();
        }
    }

    /**
     * @return self Provides a fluent interface
     */
    public function setProfiler(Profiler\ProfilerInterface $profiler)
    {
        $this->profiler = $profiler;
        if ($this->connection instanceof Profiler\ProfilerAwareInterface) {
            $this->connection->setProfiler($profiler);
        }
        if ($this->statementPrototype instanceof Profiler\ProfilerAwareInterface) {
            $this->statementPrototype->setProfiler($profiler);
        }
        return $this;
    }

    /**
     * @return null|Profiler\ProfilerInterface
     */
    public function getProfiler()
    {
        return $this->profiler;
    }

    /**
     * Register connection
     *
     * @return self Provides a fluent interface
     */
    public function registerConnection(Connection $connection)
    {
        $this->connection = $connection;
        $this->connection->setDriver($this); // needs access to driver to createStatement()
        return $this;
    }

    /**
     * Register statement prototype
     *
     * @return self Provides a fluent interface
     */
    public function registerStatementPrototype(Statement $statementPrototype)
    {
        $this->statementPrototype = $statementPrototype;
        $this->statementPrototype->setDriver($this); // needs access to driver to createResult()
        return $this;
    }

    /**
     * @return null|Statement
     */
    public function getStatementPrototype()
    {
        return $this->statementPrototype;
    }

    /**
     * Register result prototype
     *
     * @return self Provides a fluent interface
     */
    public function registerResultPrototype(Result $resultPrototype)
    {
        $this->resultPrototype = $resultPrototype;
        return $this;
    }

    /**
     * @return null|Result
     */
    public function getResultPrototype()
    {
        return $this->resultPrototype;
    }

    /**
     * Add feature
     *
     * @param string $name
     * @param AbstractFeature $feature
     * @return self Provides a fluent interface
     */
    public function addFeature($name, $feature)
    {
        if ($feature instanceof AbstractFeature) {
            $name = $feature->getName(); // overwrite the name, just in case
            $feature->setDriver($this);
        }
        $this->features[$name] = $feature;
        return $this;
    }

    /**
     * Setup the default features for Pdo
     *
     * @return self Provides a fluent interface
     */
    public function setupDefaultFeatures()
    {
        $this->addFeature(null, new Feature\RowCounter());
        return $this;
    }

    /**
     * Get feature
     *
     * @param string $name
     * @return AbstractFeature|false
     */
    public function getFeature($name)
    {
        if (isset($this->features[$name])) {
            return $this->features[$name];
        }
        return false;
    }

    /**
     * Get database platform name
     *
     * @param  string $nameFormat
     * @return string
     */
    public function getDatabasePlatformName($nameFormat = self::NAME_FORMAT_CAMELCASE)
    {
        return 'Oracle';
    }

    /**
     * Check environment
     */
    public function checkEnvironment()
    {
        if (! extension_loaded('oci8')) {
            throw new Exception\RuntimeException(
                'The Oci8 extension is required for this adapter but the extension is not loaded'
            );
        }
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @param string $sqlOrResource
     * @return Statement
     */
    public function createStatement($sqlOrResource = null)
    {
        $statement = clone $this->statementPrototype;
        if (is_resource($sqlOrResource) && get_resource_type($sqlOrResource) === 'oci8 statement') {
            $statement->setResource($sqlOrResource);
        } else {
            if (is_string($sqlOrResource)) {
                $statement->setSql($sqlOrResource);
            } elseif ($sqlOrResource !== null) {
                throw new Exception\InvalidArgumentException(
                    'Oci8 only accepts an SQL string or an oci8 resource in ' . __FUNCTION__
                );
            }
            if (! $this->connection->isConnected()) {
                $this->connection->connect();
            }
            $statement->initialize($this->connection->getResource());
        }
        return $statement;
    }

    /**
     * @param  resource $resource
     * @param  null     $context
     * @return Result
     */
    public function createResult($resource, $context = null)
    {
        $result   = clone $this->resultPrototype;
        $rowCount = null;
        // special feature, oracle Oci counter
        if ($context && ($rowCounter = $this->getFeature('RowCounter')) && oci_num_fields($resource) > 0) {
            $rowCount = $rowCounter->getRowCountClosure($context);
        }
        $result->initialize($resource, null, $rowCount);
        return $result;
    }

    /**
     * @return string
     */
    public function getPrepareType()
    {
        return self::PARAMETERIZATION_NAMED;
    }

    /**
     * @param string $name
     * @param mixed  $type
     * @return string
     */
    public function formatParameterName($name, $type = null)
    {
        return ':' . $name;
    }

    /**
     * @return mixed
     */
    public function getLastGeneratedValue()
    {
        return $this->getConnection()->getLastGeneratedValue();
    }
}

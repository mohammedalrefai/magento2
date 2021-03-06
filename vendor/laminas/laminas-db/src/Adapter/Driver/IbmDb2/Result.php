<?php

namespace Laminas\Db\Adapter\Driver\IbmDb2;

use Laminas\Db\Adapter\Driver\ResultInterface;
use Laminas\Db\Adapter\Exception;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse
use ReturnTypeWillChange;

class Result implements ResultInterface
{
    /** @var resource */
    protected $resource;

    /** @var int */
    protected $position = 0;

    /** @var bool */
    protected $currentComplete = false;

    /** @var mixed */
    protected $currentData;

    /** @var mixed */
    protected $generatedValue;

    /**
     * @param  resource $resource
     * @param  mixed $generatedValue
     * @return self Provides a fluent interface
     */
    public function initialize($resource, $generatedValue = null)
    {
        $this->resource       = $resource;
        $this->generatedValue = $generatedValue;
        return $this;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     *
     * @link http://php.net/manual/en/iterator.current.php
     *
     * @return mixed Can return any type.
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        if ($this->currentComplete) {
            return $this->currentData;
        }

        $this->currentData = db2_fetch_assoc($this->resource);
        return $this->currentData;
    }

    /**
     * @return mixed
     */
    #[ReturnTypeWillChange]
    public function next()
    {
        $this->currentData     = db2_fetch_assoc($this->resource);
        $this->currentComplete = true;
        $this->position++;
        return $this->currentData;
    }

    /**
     * @return int|string
     */
    #[ReturnTypeWillChange]
    public function key()
    {
        return $this->position;
    }

    /**
     * @return bool
     */
    #[ReturnTypeWillChange]
    public function valid()
    {
        return $this->currentData !== false;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     *
     * @link http://php.net/manual/en/iterator.rewind.php
     *
     * @return void Any returned value is ignored.
     */
    #[ReturnTypeWillChange]
    public function rewind()
    {
        if ($this->position > 0) {
            throw new Exception\RuntimeException(
                'This result is a forward only result set, calling rewind() after moving forward is not supported'
            );
        }
        $this->currentData     = db2_fetch_assoc($this->resource);
        $this->currentComplete = true;
        $this->position        = 1;
    }

    /**
     * Force buffering
     *
     * @return null
     */
    public function buffer()
    {
        return null;
    }

    /**
     * Check if is buffered
     *
     * @return bool|null
     */
    public function isBuffered()
    {
        return false;
    }

    /**
     * Is query result?
     *
     * @return bool
     */
    public function isQueryResult()
    {
        return db2_num_fields($this->resource) > 0;
    }

    /**
     * Get affected rows
     *
     * @return int
     */
    public function getAffectedRows()
    {
        return db2_num_rows($this->resource);
    }

    /**
     * Get generated value
     *
     * @return mixed|null
     */
    public function getGeneratedValue()
    {
        return $this->generatedValue;
    }

    /**
     * Get the resource
     *
     * @return mixed
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Get field count
     *
     * @return int
     */
    public function getFieldCount()
    {
        return db2_num_fields($this->resource);
    }

    /**
     * @return int
     */
    #[ReturnTypeWillChange]
    public function count()
    {
        return 0;
    }
}

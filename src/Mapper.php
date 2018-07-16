<?php

namespace Otzy\CsvConverter;

/**
 * Class Mapper
 * 
 * generates target field from the input row
 * 
 * @package Otzy\CsvConverter
 */
class Mapper
{
    /**
     * @var \Closure
     */
    private $mapper;

    /**
     * @param string|int|\Closure $mapped_value the name of field
     *                                          or index on the fields array
     *                                          or a closure which accepts fields array parameter
     *                                          and return computed value for the output
     *
     * @throws \Exception
     */
    public function __construct($mapped_value)
    {
        // field mapped by the name of source field
        if (is_string($mapped_value)) {
            $this->mapper = function ($fields, $source_field_indexes) use ($mapped_value){
                if (!array_key_exists($source_field_indexes[$mapped_value], $fields)) {
                    throw new InvalidSourceRowException('Invalid number of fields in the source row');
                }
                return $fields[$source_field_indexes[$mapped_value]];
            };

            return;
        }

        // field mapped by the source field number (zero indexed)
        if (is_int($mapped_value)) {
            $this->mapper = function ($fields) use ($mapped_value){
                if (!array_key_exists($mapped_value, $fields)) {
                    throw new InvalidSourceRowException('Invalid number of fields in the source row');
                }
                return $fields[$mapped_value];
            };

            return;
        }

        if (is_callable($mapped_value)) {
            $this->mapper = $mapped_value;
            return;
        }

        throw new \Exception('Invalid mapping');
    }

    public function map($fields, $source_field_indexes)
    {
        return call_user_func($this->mapper, $fields, $source_field_indexes);
    }
}

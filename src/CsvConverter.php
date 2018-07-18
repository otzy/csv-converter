<?php

namespace Otzy\CsvConverter;
use League\Csv\EncloseField;
use League\Csv\Reader;
use League\Csv\Writer;

/**
 * Class CsvConverter
 * takes columns from the source csv file and saves desired columns to a target csv file
 */
class CsvConverter
{
    /**
     * @var Mapper[]
     */
    private $mapping = [];

    private $source_has_header = true;
    private $target_has_header = true;
    private $valid_source_header;

    private $row_count_saved = 0;
    private $row_count_processed = 0;
    private $row_count_skipped = 0;
    private $on_row_converted = null;
    private $on_completed = null;
    private $on_before_convert = null;

    private $force_enclosure = false;

    /**
     * set this parameter in order to validate that the source file has the same fields as you are expecting for.
     * If any fields are missing or there are unknown fields or field order changed the convert() function will throw
     * an exception
     *
     * If you don't need to validate fields just pass null to this function (default).
     *
     * @param array|null $valid_source_header array of all field names in the source file header line
     */
    public function setValidSourceHeader($valid_source_header)
    {
        $this->valid_source_header = $valid_source_header;
    }


    /**
     * @param array $mapping ['target_field_name' => 'source_field_name'|source_field_index|callback($row, $source_field_indexes)]
     */
    public function setMapping($mapping)
    {
        $this->mapping = [];
        foreach ($mapping as $target_field => $mapped_value) {
            $this->mapping[$target_field] = new Mapper($mapped_value);
        }
    }

    /**
     * perform conversion
     *
     * @param Reader $reader
     * @param Writer $writer
     *
     * @throws InvalidSourceHeaderException
     * @throws InvalidSourceRowException
     * @throws \Exception
     */
    public function convert(Reader $reader, Writer $writer)
    {
        $source_field_index = [];
        $source_header = [];
        if ($this->source_has_header) {
            if ($reader->getHeaderOffset() === null) {
                $reader->setHeaderOffset(0);
            }
            $source_header = $reader->getHeader();
            $this->row_count_processed++;
            if (is_array($this->valid_source_header) && !$this->isSourceHeaderValid($source_header)) {
                throw new InvalidSourceHeaderException('Invalid source file header');
            }
            $source_field_index = array_flip($source_header);
        }

        if ($this->force_enclosure) {
            EncloseField::addTo($writer, "\t\x1f");
        }

        if ($this->target_has_header) {
            $writer->insertOne(array_keys($this->mapping));
            $this->row_count_saved++;
            if (is_callable($this->on_row_converted)) {
                call_user_func($this->on_row_converted, $this->row_count_saved, $source_header, array_keys($this->mapping));
            }
        }

        foreach ($reader->getRecords() as $offset => $record) {
            $this->row_count_processed++;

            // we need an indexed array actually
            $record = array_values($record);

            if (is_array($this->valid_source_header) && !$this->validateSourceValues($record)) {
                throw new InvalidSourceRowException('Malformed row in the source CSV file');
            }

            if (is_callable($this->on_before_convert)) {
                if (call_user_func($this->on_before_convert, $this->row_count_processed, $record) === false) {
                    $this->row_count_skipped++;
                    continue;
                }
            }

            $target_values = [];
            foreach ($this->mapping as $target_field => $mapper) {
                $target_values[] = $mapper->map($record, $source_field_index);
            }
            $writer->insertOne($target_values);
            $this->row_count_saved++;
            if (is_callable($this->on_row_converted)) {
                call_user_func($this->on_row_converted, $this->row_count_saved, $record, $target_values);
            }
        }

        if (is_callable($this->on_completed)) {
            call_user_func($this->on_completed, $this->row_count_saved);
        }
    }

    /**
     * by default values are enclosed into enclosure only if they contain spaces, tabs etc.
     * If you need to enclose all fields into enclosure, set this to true
     *
     * @param bool $force
     */
    public function forceEnclosure(bool $force)
    {
        $this->force_enclosure = $force;
    }

    /**
     * how many rows were saved to the target file
     *
     * @return int
     */
    public function getRowCountSaved()
    {
        return $this->row_count_saved;
    }

    /**
     * @param callable $callback the function should accept on argument:
     *                              int $row_count - the number of saved rows including header row
     */
    public function onCompleted($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Event handler is not a function');
        }
        $this->on_completed = $callback;
    }

//    public function

    /**
     * Defines an event handler called after each processed row has been written to the target stream
     *
     * @param callable $callback The function should accept three arguments:
     *                              int $row_count - the number of saved rows including header row.
     *                              array $source_row - row read from the source file
     *                              array $target_ro - row written to the target file
     */
    public function onRowConverted($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Event handler is not a function');
        }
        $this->on_row_converted = $callback;
    }

    /**
     * Defines an event handler called before a row from source converted to the target.
     * If the handler returned boolean false, the row will be skipped.
     *
     * @param callable $callback The function should accept two arguments:
     *                              int $row_count - the number of already processed rows.
     *                              array $source_row - row read from the source file
     */
    public function onBeforeConvert($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Event handler is not a function');
        }
        $this->on_before_convert = $callback;
    }

    private function isSourceHeaderValid($fields)
    {
        return $fields === $this->valid_source_header;
    }

    private function validateSourceValues($values)
    {
        return count($values) === count($this->valid_source_header);
    }

    /**
     * @param mixed $source_has_header
     */
    public function setSourceHasHeader($source_has_header): void
    {
        $this->source_has_header = $source_has_header;
    }

    /**
     * @param mixed $target_has_header
     */
    public function setTargetHasHeader($target_has_header): void
    {
        $this->target_has_header = $target_has_header;
    }
}
<?php

namespace Otzy\CsvConverter;

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

    private $source;
    private $target;
    private $source_has_header = true;
    private $target_has_header = true;
    private $valid_source_header;

    private $source_delimiter = ';';
    private $source_enclosure = '"';
    private $source_escape = '\\';

    private $target_delimiter = ';';
    private $target_enclosure = '"';
    private $target_escape = '\\';

    private $row_count_saved = 0;
    private $row_count_processed = 0;
    private $row_count_skipped = 0;
    private $on_row_converted = null;
    private $on_completed = null;
    private $on_before_convert = null;

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

    public function setSourceFormat($delimiter = ';', $enclosure = '"', $escape = '\\')
    {
        $this->source_delimiter = $delimiter;
        $this->source_enclosure = $enclosure;
        $this->source_escape = $escape;
    }

    public function setTargetFormat($delimiter = ';', $enclosure = '"', $escape = '\\')
    {
        $this->target_delimiter = $delimiter;
        $this->target_enclosure = $enclosure;
        $this->target_escape = $escape;
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
     * @param resource $source
     * @param resource $target
     *
     * @throws InvalidSourceHeaderException
     * @throws InvalidSourceRowException
     * @throws \Exception
     */
    public function convert($source, $target)
    {
        $this->source = $source;
        $this->target = $target;

        $source_field_index = [];
        $source_header = [];
        if ($this->source_has_header) {
            $source_header = $this->fgetcsv();
            $this->row_count_processed++;
            if (is_array($this->valid_source_header) && !$this->isSourceHeaderValid($source_header)) {
                throw new InvalidSourceHeaderException('Invalid source file header');
            }
            $source_field_index = array_flip($source_header);
        }

        if ($this->target_has_header) {
            $this->fputcsv(array_keys($this->mapping));
            $this->row_count_saved++;
            if (is_callable($this->on_row_converted)) {
                call_user_func($this->on_row_converted, $this->row_count_saved, $source_header, array_keys($this->mapping));
            }
        }

        while (!feof($this->source)) {
            $source_values = $this->fgetcsv();
            if (!is_array($source_values)) {
                break;
            }
            $this->row_count_processed++;

            if (is_array($this->valid_source_header) && !$this->validateSourceValues($source_values)) {
                throw new InvalidSourceRowException('Malformed row in the source CSV file');
            }

            if (is_callable($this->on_before_convert)) {
                if (call_user_func($this->on_before_convert, $this->row_count_processed, $source_values) === false) {
                    $this->row_count_skipped++;
                    continue;
                }
            }

            $target_values = [];
            foreach ($this->mapping as $target_field => $mapper) {
                $target_values[] = $mapper->map($source_values, $source_field_index);
            }
            $this->fputcsv($target_values);
            $this->row_count_saved++;
            if (is_callable($this->on_row_converted)) {
                call_user_func($this->on_row_converted, $this->row_count_saved, $source_values, $target_values);
            }
        }

        if (is_callable($this->on_completed)) {
            call_user_func($this->on_completed, $this->row_count_saved);
        }
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

    /**
     * fgetcsv wrapper
     *
     * @return array|false|null
     * @throws \Exception
     */
    private function fgetcsv()
    {
        $result = fgetcsv($this->source, 0, $this->source_delimiter, $this->source_enclosure, $this->source_escape);
        if ($result === null) {
            throw new \Exception('Invalid stream handler');
        }

        return $result;
    }

    /**
     * fputcsv wrapper
     *
     * @param array $values
     * @return int
     * @throws \Exception
     */
    private function fputcsv($values)
    {
        $result = fputcsv($this->target, $values, $this->target_delimiter, $this->target_enclosure, $this->target_escape);
        if ($result === false) {
            throw new \Exception('Failed writing to the target stream');
        }

        return $result;
    }
}
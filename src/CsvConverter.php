<?php

namespace Otzy\CsvConverter;

/**
 * Class CsvConverter
 * takes columns from the source csv file and saves desired columns to a target csv file
 */
class CsvConverter
{
    private $mapping = [];
    private $source;
    private $target;
    private $source_has_header;
    private $target_has_header;
    private $valid_source_header;

    private $source_delimiter = ';';
    private $source_enclosure = '';
    private $source_escape = '\\';

    private $target_delimiter = ';';
    private $target_enclosure = '';
    private $target_escape = '\\';

    private $row_count = 0;
    private $done = false;

    /**
     * CSVConverter constructor.
     * @param resource $source source stream handle
     * @param resource $target target stream handle
     * @param bool $source_has_header whether source file has header line
     * @param bool $target_has_header whether target file should have header line
     */
    public function __construct($source, $target, $source_has_header, $target_has_header)
    {
        $this->source = $source;
        $this->target = $target;
        $this->source_has_header = $source_has_header;
        $this->target_has_header = $target_has_header;
    }

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
     * @param array $mapping ['target_field_name' => 'source_field_name']
     */
    public function setMapping($mapping)
    {
        $this->mapping = $mapping;
    }

    /**
     * perform conversion
     *
     * @throws InvalidSourceHeaderException
     * @throws InvalidSourceRowException
     * @throws \Exception
     */
    public function convert()
    {
        if ($this->done) {
            throw new \Exception('Conversion has already been done by this object. Create a new instance to convert other files.');
        }

        $source_field_index = [];
        if ($this->source_has_header) {
            $source_header = $this->fgetcsv();
            if (is_array($this->valid_source_header) && !$this->isSourceHeaderValid($source_header)) {
                throw new InvalidSourceHeaderException('Invalid source file header');
            }
            $this->row_count++;
            $source_field_index = array_flip($source_header);
        }

        if ($this->target_has_header) {
            $this->fputcsv(array_keys($this->mapping));
        }

        while (!feof($this->source)) {
            $source_values = $this->fgetcsv();
            if (!is_array($source_values)) {
                break;
            }
            $this->row_count++;

            if (is_array($this->valid_source_header) && !$this->validateSourceValues($source_values)) {
                throw new InvalidSourceRowException('Malformed row in the source CSV file');
            }

            $target_values = [];
            foreach ($this->mapping as $target_field => $source_field) {
                if ($this->source_has_header) {
                    if (!array_key_exists($source_field, $source_field_index)) {
                        throw new InvalidSourceHeaderException(
                            "The mapping is perhaps wrong. Source field $source_field is missing in the source file."
                        );
                    }
                    $target_values[] = $source_values[$source_field_index[$source_field]];
                } else {
                    // headless source file. Source fields have numeric index
                    if (!array_key_exists($source_field, $source_values)) {
                        throw new InvalidSourceRowException('Source row has invalid number of fields');
                    }
                    $target_values[] = $source_values[$source_field];
                }
            }
            $this->fputcsv($target_values);
        }
    }

    /**
     * how many rows were read from the source file
     *
     * @return int
     */
    public function getRowCount()
    {
        return $this->row_count;
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
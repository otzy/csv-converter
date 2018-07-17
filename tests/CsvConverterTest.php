<?php
/**
 * Created by PhpStorm.
 * User: eugene
 * Date: 7/16/2018
 * Time: 1:07 PM
 */

namespace otzy\CsvConverter\Tests;

use Otzy\CsvConverter\CsvConverter;
use PHPUnit\Framework\TestCase;

class CsvConverterTest extends TestCase
{
    private static $mapping;
    private static $mapping_headless;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::$mapping = [
            '2' => 'two',
            '1' => 'one',
            'concat' => function ($fields, $source_field_index) {
                return $fields[$source_field_index['one']] . $fields[$source_field_index['two']];
            },
        ];

        self::$mapping_headless = [
            'one' => 0,
            'three' => 2,
        ];
    }

    public function testConvertWithHeader()
    {
        $converter = new CsvConverter();
        $converter->setMapping(self::$mapping);
        $converter->setSourceHasHeader(true);
        $converter->setTargetHasHeader(true);
        $converter->setSourceFormat(';', '"', '\\');
        $converter->setTargetFormat(';', '"', '\\');
        $converter->setValidSourceHeader(['one', 'two', 'three']);

        $source = fopen(__DIR__ . '/data/source.csv', 'r');
        $converted_file_name = tempnam(sys_get_temp_dir(), 'csvconverter_test');
        $target = fopen($converted_file_name, 'w');
        $converter->convert($source, $target);
        fclose($source);
        fclose($target);

        $this->assertFileEquals(__DIR__ . '/data/target.csv', $converted_file_name);
        unlink($converted_file_name);
    }

    /**
     * @expectedException \Otzy\CsvConverter\InvalidSourceHeaderException
     */
    public function testInvalidHeaderException()
    {
        $converter = new CsvConverter();
        $converter->setMapping(self::$mapping);

        // the wrong header ("three" is missing). convert() must throw an exception
        $converter->setValidSourceHeader(['one', 'two']);

        $source = fopen(__DIR__ . '/data/source.csv', 'r');
        $converted_file_name = tempnam(sys_get_temp_dir(), 'csvconverter_test');
        $target = fopen($converted_file_name, 'w');

        // Exception must be thrown here
        $converter->convert($source, $target);

        fclose($source);
        fclose($target);

        $this->assertFileEquals(__DIR__ . '/data/target.csv', $converted_file_name);
        unlink($converted_file_name);
    }

    public function testConvertWithoutHeader()
    {
        $converter = new CsvConverter();
        $converter->setTargetHasHeader(false);
        $converter->setSourceHasHeader(false);
        $converter->setMapping(self::$mapping_headless);

        $source = fopen(__DIR__ . '/data/source_headerless.csv', 'r');
        $converted_file_name = tempnam(sys_get_temp_dir(), 'csvconverter_test');
        $target = fopen($converted_file_name, 'w');

        $converter->convert($source, $target);

        fclose($source);
        fclose($target);

        $this->assertFileEquals(__DIR__ . '/data/target_headerless.csv', $converted_file_name);
    }

    public function testOnBeforeConvert()
    {
        $converter = new CsvConverter();
        $converter->setMapping(self::$mapping);
        $converter->setSourceHasHeader(true);
        $converter->setTargetHasHeader(true);
        $converter->setSourceFormat(';', '"', '\\');
        $converter->setTargetFormat(';', '"', '\\');
        $converter->setValidSourceHeader(['one', 'two', 'three']);

        // skip row that has "a" in the first field
        $converter->onBeforeConvert(function ($row_count, $source_row) {
            if ($source_row[0] == 'a') {
                return false;
            }

            return true;
        });

        $source = fopen(__DIR__ . '/data/source.csv', 'r');
        $converted_file_name = tempnam(sys_get_temp_dir(), 'csvconverter_test');
        $target = fopen($converted_file_name, 'w');
        $converter->convert($source, $target);
        fclose($source);
        fclose($target);

        $this->assertFileEquals(__DIR__ . '/data/target._one_skipped.csv', $converted_file_name);
        unlink($converted_file_name);
    }
}
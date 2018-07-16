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
    public function testConvert()
    {
        $source = fopen(__DIR__ . '/data/source1.csv', 'r');
        $converted_file_name = tempnam(sys_get_temp_dir(), 'csvconverter_test');
        $target = fopen($converted_file_name, 'w');

        $mapping = [
            '2' => 'two',
            '1' => 'one',
            'concat' => function ($fields, $source_field_index) {
                return $fields[$source_field_index['one']] . $fields[$source_field_index['two']];
            },
        ];

        $converter = new CsvConverter($source, $target, true, true);
        $converter->setMapping($mapping);
        $converter->setSourceFormat(';', '"', '\\');
        $converter->setTargetFormat(';', '"', '\\');
        $converter->convert();
        fclose($source);
        fclose($target);
        $this->assertFileEquals(__DIR__ . '/data/target1.csv', $converted_file_name);
        unlink($converted_file_name);
    }
}
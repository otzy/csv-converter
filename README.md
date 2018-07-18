# CSV Converter
Transforms CSV file (or any plain text file with some delimiter) into a file with different set of fields.

Features
* Source and destination files may or may not have a header line
* Fields can be denoted by field name or by index
* Destination fields can be calculated using arbitrary callback functions
* Simple validity check of the source file header and data rows. CSV Converter checks that the header has exactly the same number and the order of fields that you expect.

## Installation

```
composer require otzy/csv-converter
```

## CsvConverter class
To read source CSV and write to destination CSV the class uses league/csv Reader and Writer.
Thus you can use reach functionality of league/csv package when you need to read/write using different encodings, BOM, stream filters, etc.

#### Mapping
To convert you should first create mapping.

Mapping is an associative array that defines which fields from the source file will be saved to the destination file.
Keys in this array are field names of destination CSV and values are the corresponding fields from the source SCV.

Instead of field name you cn use callback to perform any transformation on input field.

This is an example of simple mapper:
```php
$mapping = [
    'field two' => 'two',
    'field one' => 'one',
    'concat' => function ($fields, $source_field_index) {
        return $fields[$source_field_index['one']] . $fields[$source_field_index['two']];
    },
]
```

The mapper above will output 3 fields to the destination:
"field two","field one","concat"

the third field will be concatenated from fields one and two from the source file.

#### CSVConverter usage example
```php
        $mapping = [
            'field two' => 'two',
            'field one' => 'one',
            'concat' => function ($fields, $source_field_index) {
                return $fields[$source_field_index['one']] . $fields[$source_field_index['two']];
            },
        ];

        // Create an instance of class and set mapping
        $converter = new CsvConverter();
        $converter->setMapping($mapping);

        // whether source CSV has a header line
        $converter->setSourceHasHeader(true);
        
        // whether we want the destination CSV to have header line
        $converter->setTargetHasHeader(true);
        
        // Call this method if you want to be sure that source file has specific fields
        $converter->setValidSourceHeader(['one', 'two', 'three']);
        
        $reader = Reader::createFromPath('source.csv');
        $reader->setDelimiter(';');
        $reader->setEscape('\\');
        $reader->setEnclosure('"');

        $writer = Writer::createFromPath('target.csv');

        $writer->setDelimiter(';');
        $writer->setEscape('\\');
        $writer->setEnclosure('"');
        
        $converter->convert($reader, $writer);
        
        // league/csv Writer does not close stream, the only way to do this is to unset the object
        unset($writer);
```

#### Events

Convert emits 3 types of events

* BeforeConvert - after the line from source has been read and before it's converted to the destination
* AfterRowConverted - after the converted row has been output to the destination stream
* Complete - when everything is done

You can use events for example for logging or to show the progress of conversion.

BeforeConvert can be used also to filter rows. If event handler returned false the row will not be written to the destination stream.

Event handlers can be set using the following functions:

public function onBeforeConvert($callback)
public function onRowConverted($callback)
public function onCompleted($callback)

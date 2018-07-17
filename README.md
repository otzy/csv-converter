#CSV Converter
Transforms CSV file (or any plain text file with some delimiter) into a file with different set of fields.

Features
* Source and destination files may or may not have a header line
* Fields can be denoted by field name or by index
* Destination fields can be calculated using arbitrary callback functions
* Simple validity check of the source file header and data rows. CSV Converter checks the the header has exactly the same number and order of fields that you are expecting.

##Installation

```
composer require otzy/csv-converter
```


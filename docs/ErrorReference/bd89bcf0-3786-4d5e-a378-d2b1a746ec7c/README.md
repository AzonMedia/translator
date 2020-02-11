# Error bd89bcf0-3786-4d5e-a378-d2b1a746ec7c

It may occur in Translator::initialize() when the $packages_filter array argument is provided if:
- a non string element is provided in the array
- if an empty string is provided

The $package_filter argument should contain an indexed array of regexes against which the package names are to be matched. If they match they will be checked for translations.
An example $package_filter argument is:
```php
$package_filter = [
    '/azonmedia.*/i',
    '/guzaba-platform.*/i'
];
```
If not $package_filter is provided all packages will be parsed.

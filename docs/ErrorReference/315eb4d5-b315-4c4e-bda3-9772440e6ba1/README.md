# Error 315eb4d5-b315-4c4e-bda3-9772440e6ba1

This may occurs when additional paths to directories containing translations are provided to Azonmedia\Translator\Translator::initialize() as the $additional_paths argument and any of the following conditions occur:
- an element of the provided array is not a string
- an element is an empty string
- the string is not starting with / meaning it is not an absolute path

The $additional_paths array must be an indexed array containing absolute paths to existing and readable directories where the translation JSON files are stored.
Example array:
```php
$additional_paths = [
'/some/path/to/dir1',//no key is provided - '_' will be used as package name
'/some/path/to/dir2/',//trailing / is acceptable
'Application' => '/app/path'//if key is provided it will be used as package name
];
```


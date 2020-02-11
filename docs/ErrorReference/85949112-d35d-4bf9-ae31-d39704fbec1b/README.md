# Error 85949112-d35d-4bf9-ae31-d39704fbec1b

The error may occur in the initialization process in Azonmedia\Translator\Translator.
During the initialization process all the packages (filtered by $package_filter) under ./vendor and $additional_paths are checked for translations.
A package has translation if it has a "translations" directory under the directory holding the PHP source as defined in the composer.json file in the psr-4 section autoloading.
If no psr-4 section is found the "src" directory under the package root directory is checked for a subdirectory named "translations".
If such directory is found or there are directories provided in the $additional_paths argument to Translator::initialize() these are provided to Translator::process_translations_dir()
The $additional_paths argument is to be used when paths containing outside the ./vendor dir contain translations and need to be included.
Translator::process_translations_dir() can produce the error under the following conditions:
- empty string/path is provided (this is not expected to happen as the packages paths provided by [Composer](https://github.com/composer/composer) are absolute and the paths provided to $additional_paths are already checked in the initialize() method - see error [6f4460fe-f1dd-4bc8-a663-1f9caea319f4](./6f4460fe-f1dd-4bc8-a663-1f9caea319f4))
- the provided path is not an absolute path (does not start with "/") (not expected to happen - see above)
- the provided path does not exist (this is not expected to happen for the Packages - it may happen only if wrong path is provided to $additional_arguments)
- the provided path is not readable - this would mean that the file system permissions are wrong. The file system permissions must allow for the user running the application server to read/browse the directory.
- the provided path is not a directory - if this is path provided in $additional_paths it would mean a human error. If the path is part of a package under ./vendor this may mean a human error of the developer of the package or just that a package does not follow this convention (which is to be expected) and this package needs to be filtered out by using the $package_filter argument of the initialize() method. Please check error [bd89bcf0-3786-4d5e-a378-d2b1a746ec7c](./bd89bcf0-3786-4d5e-a378-d2b1a746ec7c) for more details about $packages_filter argument.

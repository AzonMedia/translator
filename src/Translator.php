<?php
declare(strict_types=1);

namespace Azonmedia\Translator;

use Azonmedia\Exceptions\InvalidArgumentException;
use Azonmedia\Exceptions\RunTimeException;
use Azonmedia\Packages\Packages;

/**
 * Class Translator
 * @package Azonmedia\Translator
 * A coroutine aware translator.
 */
abstract class Translator
{


    /**
     * The default target languge (to which the string will be translated).
     * Will be used outside coroutine context.
     * In coroutine context the target language will be set in the coroutine
     * @var string
     */
    private static string $target_language;

    /**
     * @var bool
     */
    private static bool $is_initialized_flag = FALSE;

    /**
     * Contains the translations in the form of:
     * $translations[source_lang_message][lang] = 'message in lang';
     * @var array
     */
    private static array $translations = [];

    /**
     * A list with the packages that have their translations loaded
     * $packages['vendor-name/package-name'] = ['translations_path' => '', 'loaded_files' => [], 'loaded_translations' => int]
     * @var array
     */
    private static array $packages = [];

    /**
     * The total number of loaded messages (with translations).
     * @var int
     */
    private static int $total_messages = 0;

    /**
     * A list of supported languages.
     * If this is provided to the constructor only these languages will be loaded from the translations.
     * Also when target language is provided it will be checked against these languages.
     * @var array
     */
    private static array $supported_languages = [];

    /**
     * Initializes the Translator by loading the translations from all packages found in the ./vendor path and any $additional_paths
     * @param string $target_language The target language for the translations (the strings are to be translated to this language). Can be changed at any time @see self::set_target_language()
     * @param string $composer_json_path
     * @param array $packages_filter An array for regexes against which the package names (with vendor name) will be matched. Non-matched packages will not be checked for translations.
     * @param array $additional_paths An array of directories where translations files should be loaded too. It can be an indexed array or associative array package-name => translations-dir
     * @param array $supported_languages An array of the languages that are to be loaded during the initialization.
     * @throws RunTimeException
     */
    public static function initialize(string $target_language, string $composer_json_path = '', array $packages_filter = [], array $additional_paths = [], array $supported_languages = []) : void
    {
        if (self::is_initialized()) {
            return;
        }
        self::set_target_language($target_language);
        if ($supported_languages) {
            self::$supported_languages = $supported_languages;
        }


        //get translations from packages
        if (!$composer_json_path) {
            $composer_json_path = Packages::get_application_composer_file_path();
        }
        $Packages = new Packages($composer_json_path);
        $installed_packages = $Packages->get_installed_packages();

        foreach ($installed_packages as $Package) {
            $process_package = FALSE;
            $package_name = $Package->getName();
            if ($packages_filter) {
                foreach ($packages_filter as $key=>$package_filter_regex) {
                    if (!is_string ($package_filter_regex)) {
                        $message = sprintf('The %s element of $packages_filter is not a string but a %s. The elements of $packages_filter must be strings containing valid regexes.', $key, gettype($package_filter_regex) );
                        throw new InvalidArgumentException($message, 0, NULL, 'bd89bcf0-3786-4d5e-a378-d2b1a746ec7c');
                    }
                    if (!$package_filter_regex) {
                        $message = sprintf('The %s element of $packages_filter is empty. The elements of $packages_filter must be strings containing valid regexes.', $key );
                        throw new InvalidArgumentException($message, 0, NULL, 'bd89bcf0-3786-4d5e-a378-d2b1a746ec7c');
                    }
                    if (preg_match($package_filter_regex, $package_name)) {
                        $process_package = TRUE;
                    }
                    //if a filter/regex does not match any packages it is OK
                }
            } else {
                $process_package = TRUE;//no filter provided => process all packages
            }

            if ($process_package) {
                $package_path = $Packages->get_package_installation_path($Package);
                $package_path .= '/';
                if ($package_path) {
                    $package_src_path = $Packages::get_package_src_path($Package);
                    if (!$package_src_path) {
                        $package_src_path = 'src/';//default to ./src/
                    }
                    $package_translations_path = $package_path.$package_src_path.'translations/';
                    if (file_exists($package_translations_path)) {
                        self::process_translations_dir($package_translations_path, $Package->getName(), $supported_languages);
                    }
                }
            }
        }

        //get the translations from $additional_paths
        foreach ($additional_paths as $key => $additional_path) {
            if (!is_string($additional_path)) {
                $message = sprintf('The %s element of the $additional_paths argument is not a string but a %s. Only strings (absolute filesystem paths) are accepted.', $key, gettype($additional_path) );
                throw new InvalidArgumentException($message, 0, NULL, '315eb4d5-b315-4c4e-bda3-9772440e6ba1');
            }
            if (!$additional_path) {
                $message = sprintf('The %s element of the $additional_paths argument is empty. Only strings (absolute filesystem paths) are accepted.', $key);
                throw new InvalidArgumentException($message, 0, NULL, '315eb4d5-b315-4c4e-bda3-9772440e6ba1');
            }
            if ($additional_path[0] !== '/') {
                $message = sprintf('The %s element of the $additional_paths argument is not an absolute path. Only absolute filesystem paths are accepted.', $key);
                throw new InvalidArgumentException($message, 0, NULL, '315eb4d5-b315-4c4e-bda3-9772440e6ba1');
            }
            if (!is_int($key)) {
                //not expected but it is not doing any harm either...
            }

            //self::process_translations_dir($additional_path);// '_' means additional files, not part of any package under ./vendor
            $package_name = is_string($key) ? $key : '_';// '_' means additional files, not part of any package under ./vendor
            //TODO - add check is the path already loaded as part of the packages
            self::process_translations_dir($additional_path, $package_name, $supported_languages);

        }

        self::$is_initialized_flag = TRUE;
    }

    /**
     * @param array $supported_languages
     * @throws InvalidArgumentException
     */
    public static function set_supported_languages(array $supported_languages) : void
    {
        if (!count($supported_languages)) {
            throw new InvalidArgumentException(sprintf('An empty array was provided for $supported_languages.'));
        }
        foreach ($supported_languages as $key => $supported_language) {
            if (!is_string($supported_language)) {
                throw new InvalidArgumentException(sprintf('The %s element of the $supported_languages array is not a string but a %s. The $supported_languages must contain an array of strings.', $key, gettype($supported_language) ));
            }
            if (!$supported_language) {
                throw new InvalidArgumentException(sprintf('The %s element of the $supported_languages array is an empty string. The $supported_languages must contain an array of strings.', $key ));
            }
        }
        self::$supported_languages = $supported_languages;
    }

    public static function get_supported_languages() : array
    {
        return self::$supported_languages;
    }

    /**
     * Loads all translation JSON files form the provided directory $translations_dir.
     * The $package_name is used for reporting, not for the actual translation process.
     * If the translation directory is not part of any package under ./vendor then this argument can be omitted.
     * @param string $translations_dir
     * @param string $package_name
     * @throws InvalidArgumentException
     * @throws RunTimeException
     */
    private static function process_translations_dir(string $translations_dir, string $package_name = '_') : void
    {
        if (!$translations_dir) {
            $message = sprintf('The provided $translations_dir argument is empty.');
            throw new InvalidArgumentException($message, 0, NULL, '85949112-d35d-4bf9-ae31-d39704fbec1b');
        }
        if ($translations_dir[0] !== '/') {
            $message = sprintf('The provided %s path in $translations_dir argument is not an absolute path (does not start with "/").', $translations_dir);
            throw new InvalidArgumentException($message, 0, NULL, '85949112-d35d-4bf9-ae31-d39704fbec1b');
        }
        if (!file_exists($translations_dir)) {
            $message = sprintf('The additionally provided translations directory %s does not exist. The additional paths must exist', $translations_dir);
            throw new RunTimeException($message, 0, NULL, '85949112-d35d-4bf9-ae31-d39704fbec1b');
        }
        if (!is_readable($translations_dir)) {
            $message = sprintf('The translations directory %s is not readable. Please check the filesystem permissions.', $translations_dir);
            throw new RunTimeException($message, 0, NULL, '85949112-d35d-4bf9-ae31-d39704fbec1b');
        }
        if (!is_dir($translations_dir)) {
            $message = sprintf('The translations directory %s is a file. If this is expected please set the $packages_filter argument to Translator::initialize() so that this package is excluded.', $translations_dir );
            throw new RunTimeException($message, 0, NULL, '85949112-d35d-4bf9-ae31-d39704fbec1b');
        }
        $translation_files = glob($translations_dir.'*.json');
        $cnt_translations = 0;//the count across all files
        foreach ($translation_files as $translation_file) {
            $cnt_translations += self::load_translations_from_file($translation_file);
        }
        self::$packages[$package_name] = [
            'translations_path'     => $translations_dir,
            'loaded_files'          => $translation_files,
            'loaded_translations'   => $cnt_translations,
        ];
        self::$total_messages += $cnt_translations;

    }

    /**
     * Loads all translations from the provided file and returns the number of loaded translations.
     * @param string $translation_file
     * @return int
     */
    private static function load_translations_from_file(string $translation_file) : int
    {
//        try {
//            $Json = json_decode(file_get_contents($translation_file), FALSE, 512, JSON_THROW_ON_ERROR);
//        } catch (\JsonException $Exception) {
//            $message = sprintf('An error %s ocurred while parsing file %s.', $Exception->getCode(), $translation_file);
//            throw new RunTimeException($message);
//        }

        $Json = json_decode(file_get_contents($translation_file));
        if ($Json === NULL) {
            $message = sprintf('An error "%s" ocurred while parsing file %s.', json_last_error_msg(), $translation_file);
            throw new RunTimeException($message);
        }

        $supported_languages = self::get_supported_languages();
        foreach ($Json->translations as $Translation) {
            foreach ($Translation as $language => $message) {
                if ($language === $Json->source_language) {
                    $message_key = $message;
                }
            }
            if (empty($message_key)) {
                $message = sprintf('The message %s in file %s does not contain translation for the source language "%s".', print_r($Translation, TRUE), $translation_file, $Json->source_language);
                throw new RunTimeException($message, 0, NULL, '30d75308-8a23-4a10-85ea-159f2cfaeef7');
            }
            foreach ($Translation as $language => $message) {
                if ($supported_languages && !in_array($language, $supported_languages, TRUE)) {
                    continue;//do not load this language as it will not be used
                }
                //keep the $message_key in the translation as well - this identifies which is the source language
                self::$translations[$message_key][$language] = $message;
            }
        }
        return count($Json->translations);
    }

    /**
     * Checks is the Translator initialized (@see self::initialize())
     * @return bool
     */
    public static function is_initialized() : bool
    {
        return self::$is_initialized_flag;
    }

    /**
     * Returns the loaded packages data
     * @return array
     */
    public static function get_loaded_packages() : array
    {
        return self::$packages;
    }

    /**
     * Returns the total number of individual translations found
     * @return int
     */
    public static function get_loaded_messages_count() : int
    {
        return self::$total_messages;
    }

    /**
     * Sets the current target language.
     * Coroutine aware.
     * @see self::get_supported_languages()
     * @param string $target_language
     * @return void
     */
    public static function set_target_language(string $target_language) : void
    {
        if (!$target_language) {
            throw new RunTimeException(sprintf('No target language language is provided.'));
        }
        $supported_languages = self::get_supported_languages();
        if ($supported_languages && !in_array($target_language, $supported_languages, TRUE)) {
            throw new InvalidArgumentException(sprintf('The provided $target_language "%s" is not in the supported languages "%s".', $target_language, implode(', ', $supported_languages) ));
        }
        if (class_exists(\Swoole\Coroutine::class) && \Swoole\Coroutine::getCid() > 0) {
            $Context = \Swoole\Coroutine::getContext();
            if (!isset($Context->{self::class})) {
                $Context->{self::class} = new \stdClass();
            }
            $Context->{self::class}->target_language = $target_language;
        } else {
            self::$target_language = $target_language;
        }
    }

    /**
     * Returns the current target language.
     * Coroutine aware.
     * @return string
     */
    public static function get_target_language() : string
    {
        $ret = NULL;
        if (class_exists(\Swoole\Coroutine::class) && \Swoole\Coroutine::getCid() > 0) {
            $Context = \Swoole\Coroutine::getContext();
            if (isset($Context->{self::class}->target_language)) {
                $ret = $Context->{self::class}->target_language;
            } else {
                $ret = self::$target_language;//fall back
            }
        } else {
            $ret = self::$target_language;
        }
        return $ret;
    }

    /**
     * Translates the provided $text to the $target_language.
     * If the $target_language is not provided self::get_target_language() is used.
     * If no translation is found the $text is returned unmodified.
     * @param string $text The text to be translated
     * @param null|string $target_language The language to whcih the text is to be translated
     * @return string The translated text
     */
    public static function _(string $text, ?string $target_language = NULL) : string
    {
        if (!self::is_initialized()) {
            return $text;
        }
        if (!$text) {
            return $text;
        }
        if ($target_language) {
            $supported_languages = self::get_supported_languages();
            if ($supported_languages && !in_array($target_language, $supported_languages, TRUE)) {
                throw new InvalidArgumentException(sprintf('The provided $target_language "%s" is not in the supported languages "%s".', $target_language, implode(', ', $supported_languages) ));
            }
        } else {
            $target_language = self::get_target_language();
        }
        if (!$target_language) {
            return $text;
        }
        return isset(self::$translations[$text][$target_language]) ? self::$translations[$text][$target_language] : $text;
    }
}
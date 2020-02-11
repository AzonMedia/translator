# Error 30d75308-8a23-4a10-85ea-159f2cfaeef7

This error may occur during the initialization process of the Translator in the load_translations_from_file() method if a given translation file contains a translation that is missing the message in the source language.
The source language is the language in which the messages are written in the source code.
A translation in this language must be present in the translation file.
An example translation.json file is:
```json
{
    "package": "azonmedia/filesystem",
    "source_language": "en",
    "translations": [
        {
            "en": "The file/dir %s does not exist.",
            "bg": "Файлът/директорията %s не съществува."
        },
        {
            "en": "The file/dir %s is not readable.",
            "bg": "Файлът/директорията %s не може да бъде прочетен."
        }
    ]

}
```
In the given example the source language is "en". The error 30d75308-8a23-4a10-85ea-159f2cfaeef7 will occur if there is no message in this language defined in one of the translations.
Consider the below example (in the second translation the message in "en" is missing):
```json
{
    "package": "azonmedia/filesystem",
    "source_language": "en",
    "translations": [
        {
            "en": "The file/dir %s does not exist.",
            "bg": "Файлът/директорията %s не съществува."
        },
        {
            "bg": "Файлът/директорията %s не може да бъде прочетен."
        }
    ]

}
```
# MediaWiki extension ExportForTranslation

This extension exports articles as text files to pass to external
translators. It uses an internal list of pre-configured header
translations as well as the table provided by the TranslationManager
extension to translate links and headers; this saves on repeat work
and helps maintain consistency.

## Permissions & User Groups
The extension defines a new user group, "translator", and the permission
that allows exporting an article for translation - "export-for-translation".

## Config
- Uses extension:TranslationManager's $wgTranslationManagerValidLanguages,
  an array of languages a user can export to (e.g. ['ar', 'en'] )

## Dependencies
This extension has a hard dependency on TranslationManager.

## Todo
- API module

## Changelog
### 0.3.0, 2018-08-10
- API module for export
### 0.2.0, 2018-08-08
- Multi-lingual support, matching extension:TranslationManager v0.4.0
### 0.1.0
- Initial release

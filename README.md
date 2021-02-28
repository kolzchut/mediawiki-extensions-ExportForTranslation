#  ExportForTranslation extension for MediaWiki

This extension exports articles as text files to pass to external
translators. It uses an internal list of pre-configured header
translations as well as the table provided by the TranslationManager
extension to translate links and headers; this saves on repeat work
and helps maintain consistency.

Both TranslationManager and ExportForTranslation are single-language
for now, intended for translating Kol-Zchut from Hebrew to Arabic.

## Configuration
- `$wgExportForTranslationNamespaces = [ NS_MAIN ]`
  Namespaces that show the "export to translation" menu option
- `$wgExportForTranslationDefaultLanguage = 'ar'`
  ISO 639-1 language code to use by default. Right now this is
  just in preparation for the future.  

## Dependencies
This extension has a hard dependency on TranslationManager.

## Changelog
### 0.2.2, 2021-02-28
- bugfix: the page's own translation was missing because of recent changes
### 0.2.1, 2021-02-25
- Do not fall back to title translation for the categories, as it introduces inconsistencies
### 0.2.0, 2021-02-24
- Refactor to only pull suggestions and language links for the
  actual links on page, not every page and suggestion in TranslationManager
- Ignore double spaces inside links, because apparently MediaWiki ignores them
- Handle links that include a fragment `[[Example#Heading2]]`
- If a category doesn't have a language link, fall back to title translations
  (after this refactor, it will only work if the same title is available on page)
### 0.1.0, unknown date

## Todo
- Multi-lingual support
- API module

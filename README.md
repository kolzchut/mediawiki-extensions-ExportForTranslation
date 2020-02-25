# MediaWiki extension ExportForTranslation

This extension exports articles as text files to pass to external
translators. It uses an internal list of pre-configured header
translations as well as the table provided by the TranslationManager
extension to translate links and headers; this saves on repeat work
and helps maintain consistency.

Both TranslationManager and ExportForTranslation are single-language
for now, intended for translating Kol-Zchut from Hebrew to Arabic.

## Configuration
`$wgExportForTranslationNamespaces = [ NS_MAIN ]`
  Namespaces that show the "export to translation" menu option


## Dependencies
This extension has a hard dependency on TranslationManager.

## Todo
- Support category names translation - if there's no interlanguage link
  for the category, try and use regular article titles
- Multi-lingual support
- API module

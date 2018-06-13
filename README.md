# MediaWiki extension ExportForTranslation

This extension exports articles as text files to pass to external
translators. It uses an internal list of pre-configured header
translations as well as the table provided by the TranslationManager
extension to translate links and headers; this saves on repeat work
and helps maintain consistency.

Both TranslationManager and ExportForTranslation are single-language
for now, intended for translating Kol-Zchut to Arabic.


# Dependencies
This extension has a hard dependency on TranslationManager.

# Todo
- Multi-lingual support
- API module

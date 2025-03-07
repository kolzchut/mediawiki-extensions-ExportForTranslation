#  ExportForTranslation extension for MediaWiki

This extension exports articles as text files to pass to external
translators. It uses an internal list of pre-configured header
translations as well as the table provided by the TranslationManager
extension to translate links and headers; this saves on repeat work
and helps maintain consistency.

Both TranslationManager and ExportForTranslation are single-language
for now, intended for translating Kol-Zchut from Hebrew to Arabic.

## Usage
The extension adds a user group, `translator`, with the user right `export-for-translation`.
Users in this group have an additional link in their user tools, which allows exporting an article.

The export results in a download of a plaintext file, where links are replaced with their translation
(if available through language links or TranslationManager). There is also an additional template added,
`{{נתוני תרגום}}`, with the following parameters:
- שם (name)
- שם מתורגם (translated name)
- תאריך עדכון מקור (source last updated)
- rev_id

On our system, this template creates and shows a diff link between the exported revision and the current
revision; you can adapt it to your needs, and use it to save semantic information, for example.

This template can be found in the `wikitemplates` subdirectory.

### Special:ExportForTranslation
It is also possible to export pages using `Special:ExportForTranslation`, either by selecting a page there,
or passing a page name as a subpage, e.g.: `Special:ExportForTranslation/My_Page_Name`.

## Configuration
The extension comes with these configuration options which can be set in `LocalSettings.php`:

```php
// Define which namespaces should include the "Export for translation" button
$wgExportForTranslationNamespaces = [ 0, 4 ]; // Main and Project namespaces

// Default target language for translation exports - ISO 639-1 language code
$wgExportForTranslationDefaultLanguage = 'ar'; // Arabic
```

### Translation Headers
The extension uses a JSON file at `MediaWiki:ExportForTranslationHeaders.json` to manage headers translation mappings.
The file follows this structure:

```json
{
  "Header1": {
    "he": "Original header in Hebrew",
    "ar": "Translated header in Arabic",
    "en": "Translated header in English"
  },
  "Header2": {
    "he": "Another header in Hebrew",
    "ar": "Another header in Arabic",
    "en": "Another header in English"
  }
}
```

## Dependencies
This extension has a hard dependency on TranslationManager.

## Changelog
### 0.5.0, 2025-02-25
- Change the storage for Header translations to a JSON file, `MediaWiki:ExportForTranslationHeaders.json`
### 0.4.0, 2023-01-12
- Multilingual support, matching extension:TranslationManager v0.8.0
### 0.3.0, 2021-06-08
- Breaking change: ExportForTranslation:export() now expects a Title.
- ExportForTranslation:export() can now also be passed a revision ID, to export a specific revision.
- The exported text now holds a template of metadata, which was previously encoded as
  an HTML comment.

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
- API module

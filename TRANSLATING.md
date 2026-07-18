# Translation guide

Potts Biography uses `I18N::translate()` and `I18N::plural()` for visitor-facing module text and implements webtrees custom translation loading.

Translation files belong in:

`resources/lang/`

Use a webtrees language code as the filename, for example `de.php`, `fr.php`, `en-GB.php` or `pt_BR.php`. The loader first reads the base-language file and then a more specific locale file where present, so locale translations can override shared language translations.

A PHP language file returns source-to-translation pairs:

```php
<?php

return [
    'Potts Biography' => 'Translated module name',
    'Biography chapters' => 'Translated text',
];
```

Only string keys and string translations are loaded. For plural strings, follow the webtrees localisation format used by the target language. Dynamic genealogy content such as names, places, event labels and user-written notes remains as recorded in the tree.

Before contributing a translation:

1. test the administration page and individual tab
2. test singular and plural event/media counts
3. test right-to-left layout when relevant
4. confirm long translated headings wrap on phone widths
5. submit the language file without private genealogy data

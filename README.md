Wikisource category browser
===========================

This script is running at http://tools.wmflabs.org/ws-cat-browser/
and is updated weekly.

## Missing languages?

If your Wikisource is missing, please [create a issue](https://github.com/wikisource/ws-cat-browser/issues/new)
and tell us the names of

1. the root category, and
2. the category for validated indices (e.g. `Index_Validated`, `Pagine_indice_SAL_100%`).

## Tracking
At some point, this will track changes (or at least additions) of validated works.
For now, the `data/works.json` is manually updated with a [formatted](http://jsonformatter.curiousconcept.com/)
version of the generated `works.json` file, so added and removed entries can be tracked.

## Development

To build it locally, download and import dumps of the the tables specified in `download_dumps.sh`

Then set `$dbs`, `$dbuser`, and `$dbpass` in `config.php` (copy it from `config_dist.php`)
and run `php build.php`.


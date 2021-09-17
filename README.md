Wikisource category browser
===========================

This script is running at https://ws-cat-browser.toolforge.org/
and is updated weekly.

## Missing languages?

If your Wikisource is missing, please add relevant sitelinks to the following items on Wikdiata:

1. the [root category (Q1281)](https://www.wikidata.org/wiki/Q1281), and
2. the category for [validated indices Q15634466](https://www.wikidata.org/wiki/Q15634466)
   (e.g. `Index_Validated`, `Pagine_indice_SAL_100%`).

Alternatively, you can [create a task on Phabricator](https://phabricator.wikimedia.org/maniphest/task/edit/form/1/?project=wikisource)
and just tell us the names of the above categories.

## Tracking
At some point, the git repository of this tool will track changes of (or at least additions to) validated works.
For now, the `data/works.json` is manually updated with a [formatted](http://jsonformatter.curiousconcept.com/)
version of the generated `works.json` file, so added and removed entries can be tracked.

## Development

To work with this tool locally, edit the list of languages specified in `download_dumps.sh`,
then run this script to download the relevant database dumps.
Import these with something like the following:

Then set `$dbs`, `$dbuser`, and `$dbpass` in `config.php` (copy it from `config_dist.php`)
and run `php build.php`.


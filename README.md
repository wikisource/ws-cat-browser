Wikisource category browser
===========================

This script is running at http://tools.wmflabs.org/ws-cat-browser/

To build it locally, download and import dumps of the the following tables
from https://dumps.wikimedia.org/enwikisource/

* [`categorylinks`](https://dumps.wikimedia.org/enwikisource/latest/enwikisource-latest-categorylinks.sql.gz)
* [`page`](https://dumps.wikimedia.org/enwikisource/latest/enwikisource-latest-page.sql.gz)
* [`pagelinks`](https://dumps.wikimedia.org/enwikisource/latest/enwikisource-latest-pagelinks.sql.gz)

Then set `$dsn`, `$dbuser`, and `$dbpass` in `config.php` (copy it from `config_dist.php`)
and run `php build.php`.

## Tracking
At some point, this will track changes (or at least additions) of validated works. For now, the `data/works.json` is manually updated with a [formatted](http://jsonformatter.curiousconcept.com/) version of the generated `works.json` file, so added and removed entries can be tracked.

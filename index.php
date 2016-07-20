<?php
require 'config.php';
$siteInfo = json_decode(file_get_contents(__DIR__.'/sites.json'), true);
ksort($siteInfo);
$lang = (isset($_GET['lang'])) ? htmlspecialchars($_GET['lang']) : 'en';
if ( !array_key_exists($lang, $siteInfo)) {
    $err = "The language '$lang' has not yet been included. Please lodge an issue.";
    $lang = 'en';
}
$suffix = ($lang=='en') ? '' : '_'.$lang;

?><!doctype html>
<html class="no-js" lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Wikisource (All Validated Works)</title>
        <link rel="stylesheet" href="//tools-static.wmflabs.org/cdnjs/ajax/libs/foundation/5.4.7/css/foundation.min.css" />
        <link rel="stylesheet" href="style.css" />
        <script src="//tools-static.wmflabs.org/cdnjs/ajax/libs/modernizr/2.8.3/modernizr.min.js"></script>
    </head>
    <body><div class="container">

        <div class="page-header row">
            <div class="large-8 columns">
                <h1>
                    <span tt="wikisource">Wikisource</span>
                    <small><span tt="all_validated_works">All validated works</span></small>
                </h1>
            </div>
            <div class="large-4 columns text-right">
                <p tt='interface_language'>Interface language</p>
                <p id='interface_language_wrapper'></p>
            </div>
        </div>

        <div class="row">
            <div class="large-12 columns">

                <ul class="inline-list">
                    <li>Languages:</li>
                    <?php foreach ($siteInfo as $l => $info): ?>
                    <li>
                    <?php if ($lang == $l): ?>
                        <strong><?php echo $l ?></strong>
                    <?php else: ?>
                        <a href="?lang=<?php echo $l ?>"><?php echo $l ?></a>
                    <?php endif ?>
                    </li>
                    <?php endforeach ?>
                </ul>

                <?php if (isset($err)): ?>
                <p class="alert-box alert"><?php echo $err ?></p>
                <?php endif ?>

                <p>
                    This page presents the categorisation of the <span id="total-works">x</span> works
                    on the <a href="https://<?php echo $lang ?>.wikisource.org/"><?php echo strtoupper($lang) ?> Wikisource</a>
                    that are categorised, backed by scans,
                    and have been validated (i.e. proofread by at least two contributors).
                </p>
                <p class="loading"><img src="img/loading.gif" /> Categories loading, please wait...</p>
                <ol class="c hide" id="catlist"></ol>
                <p>
                    This list was last updated at:
                    <span id="last-mod">y</span> <a href="http://time.is/UTC">UTC</a>.
                    The above data is available in
                    <a href="works<?php echo $suffix ?>.json"><tt>works<?php echo $suffix ?>.json</tt></a>
                    and <a href="categories<?php echo $suffix ?>.json"><tt>categories<?php echo $suffix ?>.json</tt></a>.
                </p>
                <p>
                    If you don't see your language's Wikisource listed above,
                    please make sure it is present as a sitelink on Wikidata for
                    the <a href="https://www.wikidata.org/wiki/Q1281">root category (Q1281)</a> and
                    the <a href="https://www.wikidata.org/wiki/Q15634466">category for validated indexes (Q15634466)</a>.
                </p>
                <p>
                    For more information please see
                    <a href="https://github.com/wikisource/ws-cat-browser">the code</a> on Github
                    or contact <a href="https://meta.wikimedia.org/wiki/User:Samwilson">User:Samwilson</a>.
                </p>
            </div>
        </div>
        </div><!-- .container -->

        <div class="hide">
            <img src='https://upload.wikimedia.org/wikipedia/commons/thumb/d/d5/EPUB_silk_icon.svg/15px-EPUB_silk_icon.svg.png' />
        </div>
        <script src="//tools-static.wmflabs.org/cdnjs/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
        <script src="//tools-static.wmflabs.org/cdnjs/ajax/libs/foundation/5.4.7/js/foundation.min.js"></script>
        <script src="//tools.wmflabs.org/tooltranslate/tt.js"></script>
        <script src="scripts.js"></script>
        <script>
            var lang = '<?php echo $lang ?>';
        </script>
    </body>
</html>

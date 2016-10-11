<?php
require 'vendor/autoload.php';
require 'config.php';

$i18n = new Intuition('ws-cat-browser');
$i18n->registerDomain('ws-cat-browser', __DIR__.'/i18n');

$siteInfo = json_decode(file_get_contents(__DIR__.'/sites.json'), true);
ksort($siteInfo);
$lang = (isset($_GET['lang'])) ? htmlspecialchars($_GET['lang']) : 'en';
if ( !array_key_exists($lang, $siteInfo)) {
    $err = $i18n->msg('language-not-found', ['variables'=>[$lang]]);
    $lang = 'en';
}
$suffix = ($lang=='en') ? '' : '_'.$lang;


?><!doctype html>
<html class="no-js" lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>
            <?php echo $i18n->msg('wikisource') ?>
            (<?php echo $i18n->msg('all-validated-works') ?>)
        </title>
        <link rel="stylesheet" href="//tools-static.wmflabs.org/cdnjs/ajax/libs/foundation/5.4.7/css/foundation.min.css" />
        <link rel="stylesheet" href="style.css" />
        <script src="//tools-static.wmflabs.org/cdnjs/ajax/libs/modernizr/2.8.3/modernizr.min.js"></script>
    </head>
    <body><div class="container">

        <div class="row">
            <div class="large-12 columns">
                <h1>
                    <?php echo $i18n->msg('wikisource') ?>
                    <small><?php echo $i18n->msg('all-validated-works') ?></small>
                </h1>
            </div>
        </div>

        <div class="row">
            <div class="large-12 columns">

                <ul class="inline-list">
                    <li><?php echo $i18n->msg('languages') ?></li>
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
                    <?php echo $i18n->msg('introduction', ['variables' => [
                        '<span id="total-works">x</span>',
                        '<a href="https://'.$lang.'.wikisource.org/">'
                        .strtoupper($lang).' Wikisource</a>'
                    ]]) ?>
                </p>
                <p class="loading">
                    <img src="img/loading.gif" /> <?php echo $i18n->msg('loading') ?>
                </p>
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
        <script src="scripts.js"></script>
        <script>
            var lang = '<?php echo $lang ?>';
        </script>
    </body>
</html>

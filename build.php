<?php

require 'vendor/autoload.php';
require 'config.php';
if (!isset($dsn) || !isset($dbuser) || !isset($dbpass)) {
    echo 'config.php must define $dsn, $dbuser, and $dbpass' . "\n";
    exit(1);
}

//getMostRecentDump();exit();

$pdo = new PDO($dsn, $dbuser, $dbpass);


echo "Getting list of validated works . . . ";
$validatedWorks = getValidatedWorks($pdo);
echo "done.\n";


echo "Getting category data . . . ";
$allCats = array();
foreach ($validatedWorks as $indexTitle => $workTitle) {
    $catTree = getAllCats($pdo, $workTitle, 0);
    $allCats = array_map('array_unique', array_merge_recursive($allCats, $catTree));
}
echo "done.\n";


echo "Sorting categories . . . ";
foreach ($allCats as $cat => $cats) {
    sort($allCats[$cat]);
}
echo "done.\n";


// Compile templates etc.
echo "Compiling HTML list . . . ";
$outDir = __DIR__ . '/out';
$templateDir = __DIR__ . '/templates';
recurse_copy(__DIR__ . '/templates', $outDir);
$loader = new Twig_Loader_Filesystem($templateDir);
$twig = new Twig_Environment($loader);
$included = array();
$list = printCatTree($allCats, $allCats['Category:Categories'], 0, $validatedWorks, $included);
$header = $twig->render('header.html', array('total' => number_format(count($included))));
$footer = $twig->render('footer.html', array('now' => date('j F, Y'),));
file_put_contents($outDir . '/index.html', $header . $list . $footer);
echo "done.\n";

// End

/**
 * Functions only beyond here.
 */

/**
 * Get a list of validated works.
 * @param \PDO $pdo
 * @return array
 */
function getValidatedWorks($pdo) {
//    $main_ns_id = 0;
//    $index_ns_id = 106;
//    $validatedCatName = 'Index_Validated';
    $sql = 'SELECT '
            . '   indexpage.page_title AS indextitle,'
            . '   workpage.page_title AS worktitle'
            . ' FROM page AS indexpage '
            . '   JOIN categorylinks ON cl_from=indexpage.page_id '
            . '   JOIN pagelinks ON pl_from=indexpage.page_id '
            . '   JOIN page AS workpage ON workpage.page_title=pl_title '
            . ' WHERE '
            . '   workpage.page_title NOT LIKE "%/%" '
            . '   AND pl_namespace = 0 '
            . '   AND workpage.page_namespace = 0'
            . '   AND indexpage.page_namespace = 106 '
            . '   AND cl_to = "Index_Validated" ';
    $stmt = $pdo->query($sql);
    //$stmt->execute();
    //var_dump($pdo->errorInfo());
//    $stmt->execute(array(
////        ':main_ns_id1' => $main_ns_id,
////        ':main_ns_id2' => $main_ns_id,
////        ':index_ns_id' => $index_ns_id,
////        ':validatedCatName' => $validatedCatName
//    ));
    $out = array();
    foreach ($stmt->fetchAll() as $res) {
        $out[$res['indextitle']] = $res['worktitle'];
    }
    //var_dump($out);
    return $out;
}

function getAllCats($pdo, $baseCat, $ns, $catList = array(), $tracker = array()) {
    //echo "Getting categories of $baseCat\n";

    $cats = getCats($pdo, $baseCat, $ns);

//    if (in_array('Categories', $cats)) {
//        var_dump($baseCat);
//        print_r($cats);
//        exit();
//    }

    if (empty($cats)) {
        return $catList; //array();
    }

    if ($ns == 0) {
        $tracker = array();
    }

    // For each cat, create an element in the output array.
    foreach ($cats as $cat) {
        //echo "Getting supercats of $baseCat via $cat.\n";
        $tracker_tag = $baseCat . ' - ' . $cat;
        if (in_array($tracker_tag, $tracker)) {
            echo "A category loop has been detected.";
            print_r($tracker);
            exit(1);
        }
        array_push($tracker, $tracker_tag);
        //echo memory_get_usage()."\n";
        // Add all of $cat's parents to the $catList.
        //if ($cat != 'Categories') {
        $superCats = getAllCats($pdo, $cat, 14, $catList, $tracker);
        $catList = array_merge_recursive($catList, $superCats);
        //}
        //var_dump($superCats);
        // Initialise $cat as a parent if it's not there yet.
        if (!isset($catList[$cat])) {
            $catList[$cat] = array();
        }
        // Then add the $baseCat as a child.
        if (!in_array($baseCat, $catList[$cat])) {
            array_push($catList[$cat], $baseCat);
        }

        $catList = array_map('array_unique', $catList);
        //sort($catList[$cat]);
    }
    return array_map('array_unique', $catList);
}

function getCats($pdo, $baseCat, $ns) {
    // Get the starting categories.
    $sql = 'SELECT cl_to AS catname FROM page '
            . '   JOIN categorylinks ON cl_from=page_id'
            . ' WHERE page_title = :page_title'
            . '   AND page_namespace = :page_namespace ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(
        ':page_title' => str_replace('Category:', '', $baseCat),
        ':page_namespace' => $ns,
    ));
    $cats = array();
    foreach ($stmt->fetchAll() as $cat) {
        $cats[] = 'Category:' . $cat['catname'];
    }
    return $cats;
}

function printCatTree($allcats, $cats, $depth, $validatedWorks, &$included) {
    $list = "<ol class='c'>\n";
    //print_r($cats);
    foreach ($cats as $cat) {
        //echo "Writing $cat to outfile.\n";
        //print_r($allcats[$cat]);
        //$ref = cleanName($cat);
        $title = str_replace('_', ' ', $cat);
        if (in_array($cat, $validatedWorks)) {
            //echo "is a work.\n";
            $title = "<a href='https://en.wikisource.org/wiki/$cat' title='View on Wikisource'>$title</a>"
                    . "<a href='http://wsexport.wmflabs.org/tool/book.php?lang=en&format=epub&page=$cat'>"
                    . " <img src='https://upload.wikimedia.org/wikipedia/commons/thumb/d/d5/EPUB_silk_icon.svg/15px-EPUB_silk_icon.svg.png'"
                    . "     title='Download EPUB' />"
                    . "</a>";
            $included[$cat] = $cat;
        }
        $list .= "<li><span>" . str_replace('Category:', '', $title) . "</span>\n";
        if (isset($allcats[$cat])) {
            $list .= printCatTree($allcats, $allcats[$cat], $depth + 1, $validatedWorks, $included);
        }
        $list .= "</li>\n";
    }
    $list .= "</ol>\n";
    return $list;
}

//function cleanName($name) {
//    return preg_filter('/[^a-zA-Z0-9_-]/', '', $name);
//}

/**
 * 
 * @link http://stackoverflow.com/a/2050909 Author of this function.
 * @param type $src
 * @param type $dst
 */
function recurse_copy($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ( $file = readdir($dir))) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if (is_dir($src . '/' . $file)) {
                recurse_copy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function getMostRecentDump() {
    $abstractUrl = 'https://dumps.wikimedia.org/enwikisource/latest/enwikisource-latest-abstract.xml-rss.xml';
    $feed = file_get_contents($abstractUrl);
    if ($feed === FALSE) {
        return FALSE;
    }
    $xml = simplexml_load_string($feed);
    $pubDate = $xml->channel->item->pubDate;
    $dateStr = date('Ymd', strtotime($pubDate));

    // Download table dumps.
    $baseUrl = 'https://dumps.wikimedia.org/enwikisource/';
    $tables = array('page', 'categorylinks', 'pagelinks');
    foreach ($tables as $table) {
        $url = "$baseUrl$dateStr/enwikisource-$dateStr-$table.sql.gz";
        echo "$url\n";
    }
}

<?php

require 'config.php';
if (!isset($dbs) || !isset($dbuser) || !isset($dbpass)) {
    echo 'config.php must define $dbs, $dbuser, and $dbpass' . "\n";
    exit(1);
}

if (php_sapi_name() != 'cli') {
    $lang = isset($_GET['lang']) ? $_GET['lang'] : 'en';
    header("Content-Type:application/json");
    $metadata = array(
        'works_count' => count((array)json_decode(file_get_contents(getWorksFile($lang)))),
        'last_modified' => date('Y-m-d H:i', filemtime(getCatFile($lang))),
        'category_label' => $dbs[$lang]['cat_label'],
        'category_root' => $dbs[$lang]['cat_root'],
    );
    echo json_encode($metadata);
    exit(0);
}

foreach ($dbs as $lang => $info) {
    $dsn = $info['dsn'];
    $indexNs = $info['index_ns'];
    $indexRoot = $info['index_root'];
    $catLabel = $info['cat_label'];
    $pdo = new PDO($dsn, $dbuser, $dbpass);
    buildOneLang( $pdo, $lang, $indexRoot, $catLabel, $indexNs );
}

/**
 * Functions only beyond here.
 */

/**
 * Get the name of the categories.json file.
 * @param string $lang
 * @return string
 */
function getCatFile($lang) {
    $suffix = ( $lang == 'en') ? '' : '_'.$lang;
    return __DIR__.'/categories'.$suffix.'.json';
}

/**
 * Get the name of the categories.json file.
 * @param string $lang
 * @return string
 */
function getWorksFile($lang) {
    $suffix = ( $lang == 'en') ? '' : '_'.$lang;
    return __DIR__.'/works'.$suffix.'.json';
}

/**
 * 
 * @param \PDO $pdo
 */
function buildOneLang( $pdo, $lang, $indexRoot, $catLabel, $indexNs ) {

    echo "Getting list of validated works for '$lang' . . . ";
    $validatedWorks = getValidatedWorks($pdo, $indexRoot, $indexNs);
    file_put_contents(getWorksFile($lang), json_encode($validatedWorks));
    echo "done\n";

    echo "Getting category data for '$lang' . . . ";
    $allCats = array();
    foreach ($validatedWorks as $indexTitle => $workTitle) {
        $catTree = getAllCats($pdo, $workTitle, 0, array(), array(), $catLabel);
        $allCats = array_map('array_unique', array_merge_recursive($allCats, $catTree));
    }
    echo "done\n";

    echo "Sorting categories for '$lang' . . . ";
    foreach ($allCats as $cat => $cats) {
        sort($allCats[$cat]);
    }
    echo "done\n";

    // Make sure the category list was successfully built before replacing the old JSON file.
    if (count($allCats) > 0) {
        $catFile = getCatFile($lang);
        echo "Writing $catFile\n";
        file_put_contents($catFile, json_encode($allCats));
        return true;
    } else {
        echo 'No category list built! $allCats was:';
        print_r($allCats);
        exit(1);
    }
}

/**
 * Get a list of validated works.
 * @param \PDO $pdo
 * @return array
 */
function getValidatedWorks($pdo, $indexRoot, $indexNs) {
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
            . '   AND indexpage.page_namespace = '.$indexNs.' '
            . '   AND cl_to = "'.$indexRoot.'"';
    $stmt = $pdo->query($sql);
    $out = array();
    foreach ($stmt->fetchAll() as $res) {
        $out[$res['indextitle']] = $res['worktitle'];
    }
    return $out;
}

function getAllCats($pdo, $baseCat, $ns, $catList = array(), $tracker = array(), $catLabel) {
    $cats = getCats($pdo, $baseCat, $ns, $catLabel);
    if (empty($cats)) {
        return $catList;
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
        // Add all of $cat's parents to the $catList.
        $superCats = getAllCats($pdo, $cat, 14, $catList, $tracker, $catLabel);
        $catList = array_merge_recursive($catList, $superCats);
        // Initialise $cat as a parent if it's not there yet.
        if (!isset($catList[$cat])) {
            $catList[$cat] = array();
        }
        // Then add the $baseCat as a child.
        if (!in_array($baseCat, $catList[$cat])) {
            array_push($catList[$cat], $baseCat);
        }
        $catList = array_map('array_unique', $catList);
    }
    return array_map('array_unique', $catList);
}

function getCats($pdo, $baseCat, $ns, $catLabel = 'Category') {
    // Get the starting categories.
    $sql = 'SELECT cl_to AS catname FROM page '
            . '   JOIN categorylinks ON cl_from=page_id'
            . ' WHERE page_title = :page_title'
            . '   AND page_namespace = :page_namespace ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(
        ':page_title' => str_replace($catLabel.':', '', $baseCat),
        ':page_namespace' => $ns,
    ));
    $cats = array();
    foreach ($stmt->fetchAll() as $cat) {
        $cats[] = $catLabel . ':' . $cat['catname'];
    }
    return $cats;
}

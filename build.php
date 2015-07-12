<?php

require 'config.php';
if (!isset($dbuser) || !isset($dbpass)) {
    echo 'config.php must define at least $dbuser and $dbpass' . "\n";
    exit(1);
}

/*
 * Return some metadata if this is a web request.
 */
if (php_sapi_name() != 'cli') {
    $lang = (isset($_GET['lang'])) ? htmlspecialchars($_GET['lang']) : 'en';
    if (!array_key_exists($lang, $siteInfo)) {
        $lang = 'en';
    }
    header("Content-Type:application/json");
    $metadata = array(
        'works_count' => count((array)json_decode(file_get_contents(getWorksFile($lang)))),
        'last_modified' => date('Y-m-d H:i', filemtime(getCatFile($lang))),
        'category_label' => $siteInfo[$lang]['cat_label'],
        'category_root' => $siteInfo[$lang]['cat_root'],
    );
    echo json_encode($metadata);
    exit(0);
}

/*
 * If no $siteInfo is provided in config.php, construct it here for all possible Wikisources.
 */
if (!isset($siteInfo)) {
    $siteInfo = getSiteInfo();
    echo "Writing sites.json\n";
    file_put_contents(__DIR__.'/sites.json', json_encode($siteInfo));
    // Add the DSN.
    foreach ($siteInfo as $lang => $info) {
        $siteInfo[$lang]['dsn'] = "mysql:dbname={$lang}wikisource_p;host={$lang}wikisource.labsdb";
    }
}

/*
 * For each site, build categories.json and works.json
 */
foreach ($siteInfo as $lang => $info) {
    $pdo = new PDO($info['dsn'], $dbuser, $dbpass);
    buildOneLang( $pdo, $lang, $info['index_cat'], $info['cat_label'], $info['index_ns'] );
}

/*
 * Functions only beyond here.
 */

function getSiteInfo() {
    echo "Getting site information . . . ";
    $rootCatItem = 'Q1281';
    $validatedCatItem = 'Q15634466';
    $rootCats = siteLinks($rootCatItem);
    $validatedCats = siteLinks($validatedCatItem);
    $out = array();
    foreach ($rootCats as $site => $rootCat) {
        // If both a root cat and an Index cat exist.
        if (isset($validatedCats[$site])) {
            $lang = substr($site, 0, -strlen('wikisource'));
            $nsInfo = getNamespaceInfo($lang);
            $catLabel = $nsInfo['Category']['*'];
            // Strip cat label from cats
            $rootCat = substr($rootCat, strlen($catLabel)+1);
            $indexCat = substr($validatedCats[$site], strlen($catLabel)+1);
            // Put it all together, replacing spaces with underscores.
            $out[$lang] = array(
                'cat_label' => $catLabel,
                'cat_root' => str_replace(' ', '_', $rootCat),
                'index_ns' => $nsInfo['Index']['id'],
                'index_cat' => str_replace(' ', '_', $indexCat),
            );
        }
    }
    echo "done.\n";
    return $out;
}

function getNamespaceInfo($lang) {
    $url = "https://$lang.wikisource.org/w/api.php?action=query&meta=siteinfo&siprop=namespaces&format=json";
    $data = json_decode(file_get_contents($url), true);
    if (!isset($data['query']['namespaces'])) {
        return false;
    }
    $desired = array('Index', 'Category');
    $out = array();
    foreach($data['query']['namespaces'] as $ns) {
        if (isset($ns['canonical']) && in_array($ns['canonical'], $desired)) {
            $out[$ns['canonical']] = $ns;
        }
    }
    return $out;
}

/**
 * 
 * @param type $item
 * @return type
 */
function siteLinks($item) {
    $params = array(
        'action' => 'wbgetentities',
        'format' => 'json',
        'ids' => $item,
        'props' => 'sitelinks',
    );
    $url = 'https://www.wikidata.org/w/api.php?'.http_build_query($params);
    $data = json_decode(file_get_contents($url), true);
    $cats = array();
    if (isset($data['entities'][$item]['sitelinks'])) {
        foreach ($data['entities'][$item]['sitelinks'] as $sitelink) {
            $cats[$sitelink['site']] = $sitelink['title'];
        }
    }
    return $cats;
}

/**
 * Get the name of a xxx_xx.json file.
 * @param string $lang
 * @return string
 */
function getDataFilename($name, $lang) {
    $suffix = ( $lang == 'en') ? '' : '_'.$lang;
    return __DIR__.'/'.$name.$suffix.'.json';
}

/**
 * 
 * @param \PDO $pdo
 */
function buildOneLang( $pdo, $lang, $indexRoot, $catLabel, $indexNs ) {

    echo "Getting list of validated works for '$lang' . . . ";
    $validatedWorks = getValidatedWorks($pdo, $indexRoot, $indexNs);
    file_put_contents(getDataFilename('works', $lang), json_encode($validatedWorks));
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
        $catFile = getDataFilename('categories', $lang);
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

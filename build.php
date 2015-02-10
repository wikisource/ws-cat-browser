<?php

if (php_sapi_name()!='cli') {
    echo date('Y-m-d H:i', filemtime('categories.json'));
    exit(0);
}

require 'config.php';
if (!isset($dsn) || !isset($dbuser) || !isset($dbpass)) {
    echo 'config.php must define $dsn, $dbuser, and $dbpass' . "\n";
    exit(1);
}
$pdo = new PDO($dsn, $dbuser, $dbpass);


echo "Getting list of validated works . . . ";
$validatedWorks = getValidatedWorks($pdo);
echo "done\n";


echo "Getting category data . . . ";
$allCats = array();
foreach ($validatedWorks as $indexTitle => $workTitle) {
    $catTree = getAllCats($pdo, $workTitle, 0);
    $allCats = array_map('array_unique', array_merge_recursive($allCats, $catTree));
}
echo "done\n";


echo "Sorting categories . . . ";
foreach ($allCats as $cat => $cats) {
    sort($allCats[$cat]);
}
echo "done\n";


echo "Writing categories.json\n";
file_put_contents('categories.json', json_encode($allCats));
exit(0);
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
    $out = array();
    foreach ($stmt->fetchAll() as $res) {
        $out[$res['indextitle']] = $res['worktitle'];
    }
    return $out;
}

function getAllCats($pdo, $baseCat, $ns, $catList = array(), $tracker = array()) {
    $cats = getCats($pdo, $baseCat, $ns);
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
        $superCats = getAllCats($pdo, $cat, 14, $catList, $tracker);
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

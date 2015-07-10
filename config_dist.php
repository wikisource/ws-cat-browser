<?php

$dbuser = '';

$dbpass = '';

$dbs = array(

    'en' => array(
        'pdo'        => 'mysql:dbname=enwikisource_p;host=enwikisource.labsdb',
        'cat_label'  => 'Category',
        'cat_root'   => 'Categories',
        'index_ns'   => 106,
        'index_root' => 'Index_Validated',
    ),

    'it' => array(
        'pdo'        => 'mysql:dbname=itwikisource_p;host=itwikisource.labsdb',
        'cat_label'  => 'Categoria',
        'cat_root'   => 'Categorie',
        'index_ns'   => 110,
        'index_root' => 'Pagine_indice_SAL_100%',
    ),

);

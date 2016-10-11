<?php

$dbuser = '';

$dbpass = '';

$siteInfo = array(

    'en' => array(
        'dsn'        => 'mysql:dbname=wikisource_en',
        'cat_label'  => 'Category',
        'cat_root'   => 'Categories',
        'index_ns'   => 106,
        'index_cat' => 'Index_Validated',
    ),

    'it' => array(
        'dsn'        => 'mysql:dbname=wikisource_it',
        'cat_label'  => 'Categoria',
        'cat_root'   => 'Categorie',
        'index_ns'   => 110,
        'index_cat' => 'Pagine_indice_SAL_100%',
    ),

);

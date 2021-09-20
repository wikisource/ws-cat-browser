<?php

namespace App\Command;

use App\WsCatBrowser;
use DateInterval;
use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Wikimedia\ToolforgeBundle\Service\ReplicasClient;

class BuildCommand extends Command {

	/** @var ReplicasClient */
	private $replicasClient;

	/** @var CacheInterface */
	private $cache;

	/** @var OutputInterface */
	private $out;

	/** @var WsCatBrowser */
	private $wsCatBrowser;

	/**
	 * @param ReplicasClient $replicasClient
	 * @param CacheInterface $cache
	 * @param WsCatBrowser $wsCatBrowser
	 */
	public function __construct( ReplicasClient $replicasClient, CacheInterface $cache, WsCatBrowser $wsCatBrowser ) {
		parent::__construct( 'app:build' );
		$this->replicasClient = $replicasClient;
		$this->cache = $cache;
		$this->wsCatBrowser = $wsCatBrowser;
	}

	public function configure() {
		$this->addOption(
			'lang', 'l', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
			'Wikisource language code.'
		);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	public function execute( InputInterface $input, OutputInterface $output ) {
		$timeStart = microtime( true );
		$this->out = $output;

		$siteInfo = $this->getSiteInfo();
		$this->out->writeln( 'Writing sites.json' );
		file_put_contents( $this->wsCatBrowser->getSitesFilename(), json_encode( $siteInfo ) );

		// For each site, build categories.json and works.json
		$langs = $input->getOption( 'lang' );
		foreach ( $siteInfo as $lang => $info ) {
			if ( $langs && !in_array( $lang, $langs ) ) {
				$this->out->writeln( 'Skipping ' . $lang );
				continue;
			}
			$db = $this->replicasClient->getConnection( $lang === 'www' ? 'sourceswiki' : $lang . 'wikisource' );
			$this->buildOneLang( $db, $lang, $info['index_cat'], $info['cat_label'], $info['index_ns'] );
		}

		// Report completion.
		$minutes = round( ( microtime( true ) - $timeStart ) / 60, 1 );
		$this->out->writeln( "Done. Total time: $minutes minutes." );
		return Command::SUCCESS;
	}

	/**
	 * @return array
	 */
	private function getSiteInfo() {
		$this->out->writeln( 'Getting site information . . . ' );
		$rootCatItem = 'Q1281';
		$validatedCatItem = 'Q15634466';
		$rootCats = $this->siteLinks( $rootCatItem );
		$validatedCats = $this->siteLinks( $validatedCatItem );
		$out = [];
		foreach ( $rootCats as $site => $rootCat ) {
			// If both a root cat and an Index cat exist.
			if ( isset( $validatedCats[$site] ) ) {
				$lang = $site === 'sourceswiki'
					? $lang = 'www'
					: substr( $site, 0, -strlen( 'wikisource' ) );
				$nsInfo = $this->getNamespaceInfo( $lang );
				$catLabel = $nsInfo['Category']['*'];
				// Strip cat label from cats
				$rootCat = substr( $rootCat, strlen( $catLabel ) + 1 );
				$indexCat = substr( $validatedCats[$site], strlen( $catLabel ) + 1 );
				// Put it all together, replacing spaces with underscores.
				$out[$lang] = [
					'cat_label' => $catLabel,
					'cat_root' => str_replace( ' ', '_', $rootCat ),
					'index_ns' => $nsInfo['Index']['id'],
					'index_cat' => str_replace( ' ', '_', $indexCat ),
				];
			}
		}
		$this->out->writeln( 'done' );
		return $out;
	}

	/**
	 * @param string $lang
	 * @return mixed
	 */
	private function getNamespaceInfo( string $lang ) {
		return $this->cache->get( 'namespaces_' . $lang, function ( CacheItemInterface $cacheItem ) use ( $lang ) {
			$cacheItem->expiresAfter( new DateInterval( 'P7D' ) );
			$this->out->writeln( "Getting namespaces for $lang" );
			$url = "https://$lang.wikisource.org/w/api.php?action=query&meta=siteinfo&siprop=namespaces&format=json";
			$data = json_decode( file_get_contents( $url ), true );
			if ( !isset( $data['query']['namespaces'] ) ) {
				return false;
			}
			$desired = [ 'Index', 'Category' ];
			$out = [];
			foreach ( $data['query']['namespaces'] as $ns ) {
				if ( isset( $ns['canonical'] ) && in_array( $ns['canonical'], $desired ) ) {
					$out[$ns['canonical']] = $ns;
				}
			}
			return $out;
		} );
	}

	/**
	 * Get sitelinks for the given item ID.
	 * @param string $item Q-number.
	 * @return string[] Page names, keyed by site name
	 */
	private function siteLinks( $item ) {
		return $this->cache->get( 'site_links_' . $item, function ( ItemInterface $cacheItem ) use ( $item ) {
			$cacheItem->expiresAfter( new DateInterval( 'P7D' ) );
			$params = [
				'action' => 'wbgetentities',
				'format' => 'json',
				'ids' => $item,
				'props' => 'sitelinks',
			];
			$url = 'https://www.wikidata.org/w/api.php?' . http_build_query( $params );
			$this->out->write( "Getting site links from Wikidata for $item . . . " );
			$data = json_decode( file_get_contents( $url ), true );
			$cats = [];
			if ( isset( $data['entities'][$item]['sitelinks'] ) ) {
				foreach ( $data['entities'][$item]['sitelinks'] as $sitelink ) {
					$cats[$sitelink['site']] = $sitelink['title'];
				}
			}
			$this->out->writeln( 'found ' . count( $cats ) );
			return $cats;
		} );
	}

	/**
	 * @param Connection $db
	 * @param string $lang
	 * @param string $indexRoot
	 * @param string $catLabel
	 * @param string $indexNs
	 * @return bool
	 */
	private function buildOneLang( Connection $db, $lang, $indexRoot, $catLabel, $indexNs ) {
		echo "Getting list of validated works for '$lang' . . . ";
		$validatedWorks = $this->getValidatedWorks( $db, $indexRoot, $indexNs );
		file_put_contents( $this->wsCatBrowser->getDataFilename( 'works', $lang ), json_encode( $validatedWorks ) );
		echo "done\n";

		echo "Getting category data for '$lang' . . . ";
		$allCats = [];
		foreach ( $validatedWorks as $indexTitle => $workTitle ) {
			$catTree = $this->getAllCats( $db, $lang, $workTitle, 0, [], [], $catLabel );
			$allCats = array_map( 'array_unique', array_merge_recursive( $allCats, $catTree ) );
		}
		echo "done\n";

		echo "Sorting categories for '$lang' . . . ";
		foreach ( $allCats as $cat => $cats ) {
			sort( $allCats[$cat] );
		}
		echo "done\n";

		// Make sure the category list was successfully built before replacing the old JSON file.
		if ( count( $allCats ) > 0 ) {
			$catFile = $this->wsCatBrowser->getDataFilename( 'categories', $lang );
			echo "Writing $catFile\n";
			file_put_contents( $catFile, json_encode( $allCats ) );
			return true;
		} else {
			echo "No validated works found for $lang!\n";
			return false;
		}
	}

	/**
	 * Get a list of validated works.
	 * @param Connection $db
	 * @param string $indexRoot
	 * @param string $indexNs
	 * @return array
	 */
	private function getValidatedWorks( Connection $db, string $indexRoot, string $indexNs ) {
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
			. '   AND indexpage.page_namespace = :index_ns'
			. '   AND cl_to = :index_root';
		$stmt = $db->executeQuery( $sql, [
			'index_ns' => $indexNs,
			'index_root' => $indexRoot,
		] );
		$out = [];
		foreach ( $stmt->fetchAllAssociative() as $res ) {
			$out[$res['indextitle']] = $res['worktitle'];
		}
		return $out;
	}

	/**
	 * @param Connection $db
	 * @param string $lang
	 * @param string $baseCat
	 * @param string $ns
	 * @param array $catList
	 * @param array $tracker
	 * @param string $catLabel
	 * @return array|mixed
	 */
	private function getAllCats( Connection $db, $lang, $baseCat, $ns, $catList, $tracker, $catLabel ) {
		$cats = $this->getCats( $db, $baseCat, $ns, $catLabel );
		if ( empty( $cats ) ) {
			return $catList;
		}
		if ( $ns == 0 ) {
			$tracker = [];
		}

		// For each cat, create an element in the output array.
		foreach ( $cats as $cat ) {
			// echo "Getting supercats of $baseCat via $cat.\n";
			$tracker_tag = [ $baseCat, $cat ];
			if ( in_array( $tracker_tag, $tracker ) ) {
				echo "A category loop has been detected in $lang:\n<graphviz>\ndigraph G {\n";
				foreach ( $tracker as $trackerItem ) {
					echo '"' . str_replace( '"', '\"', $trackerItem[0] )
						. '" -> "' . str_replace( '"', '\"', $trackerItem[1] ) . '"' . "\n";
				}
				echo "}\n</graphviz>";
				continue;
			}
			array_push( $tracker, $tracker_tag );
			// Add all of $cat's parents to the $catList.
			$superCats = $this->getAllCats( $db, $lang, $cat, 14, $catList, $tracker, $catLabel );
			$catList = array_merge_recursive( $catList, $superCats );
			// Initialise $cat as a parent if it's not there yet.
			if ( !isset( $catList[$cat] ) ) {
				$catList[$cat] = [];
			}
			// Then add the $baseCat as a child.
			if ( !in_array( $baseCat, $catList[$cat] ) ) {
				array_push( $catList[$cat], $baseCat );
			}
			$catList = array_map( 'array_unique', $catList );
		}
		return array_map( 'array_unique', $catList );
	}

	/**
	 * @param Connection $db
	 * @param string $baseCat
	 * @param string $ns
	 * @param string $catLabel
	 * @return array
	 */
	private function getCats( Connection $db, $baseCat, $ns, $catLabel = 'Category' ) {
		// Get the starting categories.
		$sql = 'SELECT cl_to AS catname FROM page '
			. '   JOIN categorylinks ON cl_from=page_id'
			. ' WHERE page_title = :page_title'
			. '   AND page_namespace = :page_namespace ';
		$result = $db->executeQuery( $sql, [
			'page_title' => str_replace( $catLabel . ':', '', $baseCat ),
			'page_namespace' => $ns,
		] );
		$cats = [];
		foreach ( $result->fetchAllAssociative() as $cat ) {
			$cats[] = $catLabel . ':' . $cat['catname'];
		}
		return $cats;
	}

}

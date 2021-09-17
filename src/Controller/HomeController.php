<?php

namespace App\Controller;

use App\WsCatBrowser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
// phpcs:ignore
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController {

	/** @var WsCatBrowser */
	private $wsCatBrowser;

	/**
	 * @param WsCatBrowser $wsCatBrowser
	 */
	public function __construct( WsCatBrowser $wsCatBrowser ) {
		$this->wsCatBrowser = $wsCatBrowser;
	}

	/**
	 * @Route("/", name="home")
	 * @param Request $request
	 * @return Response
	 */
	public function home( Request $request ) {
		$lang = $request->get( 'lang' );
		if ( !$lang ) {
			$lang = 'en';
		}

		$siteInfo = file_exists( $this->wsCatBrowser->getSitesFilename() )
			? json_decode( file_get_contents( $this->wsCatBrowser->getSitesFilename() ), true )
			: [];

		$err = null;
		if ( !array_key_exists( $lang, $siteInfo ) ) {
			$err = [ 'language-not-found', [ $lang ] ];
			$lang = 'en';
		}

		return $this->render( 'base.html.twig', [
			'site_info' => $siteInfo,
			'lang' => $lang,
			'suffix' => $lang === 'en' ? '' : '_' . $lang,
			'err' => $err,
		] );
	}

	/**
	 * @Route("/meta.json", name="meta");
	 * @param Request $request
	 * @return JsonResponse
	 */
	public function meta( Request $request ) {
		$siteInfo = json_decode( file_get_contents( $this->wsCatBrowser->getSitesFilename() ), true );
		$lang = $request->get( 'lang' );
		if ( !array_key_exists( $lang, $siteInfo ) ) {
			$lang = 'en';
		}

		$worksFile = $this->wsCatBrowser->getDataFilename( 'works', $lang );
		$categoriesFile = $this->wsCatBrowser->getDataFilename( 'categories', $lang );
		$metadata = [
			'works_count' => count( (array)json_decode( file_get_contents( $worksFile ) ) ),
			'last_modified' => date( 'Y-m-d H:i', filemtime( $categoriesFile ) ),
			'category_label' => $siteInfo[$lang]['cat_label'],
			'category_root' => $siteInfo[$lang]['cat_root'],
		];
		return new JsonResponse( $metadata );
	}
}

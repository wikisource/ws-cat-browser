<?php

namespace App;

class WsCatBrowser {

	/** @var string */
	private $publicDir;

	/**
	 * @param string $projectDir
	 */
	public function __construct( string $projectDir ) {
		$this->publicDir = $projectDir . '/public';
	}

	/**
	 * Get the name of a xxx_xx.json file.
	 * @param string $name
	 * @param string $lang
	 * @return string
	 */
	public function getDataFilename( $name, $lang ): string {
		$suffix = ( $lang == 'en' ) ? '' : '_' . $lang;
		return $this->publicDir . '/' . $name . $suffix . '.json';
	}

	/**
	 * Get the filesystem path to the sites.json file.
	 * @return string
	 */
	public function getSitesFilename(): string {
		return $this->publicDir . '/sites.json';
	}
}

<?php
namespace MediaWiki\Extension\ScratchOAuth2;

use DatabaseUpdater;

class SOA2Hooks {
	public static function schemaUpdates( DatabaseUpdater $updater ) {
		$sql_dir = dirname(dirname(__DIR__)) . '/sql';
		$updater->addExtensionTable(
			'soa2_scratchers',
			$sql_dir . '/scratchers.sql'
		);
		$updater->addExtensionTable(
			'soa2_applications',
			$sql_dir . '/applications.sql'
		);
		$updater->addExtensionTable(
			'soa2_redirect_uris',
			$sql_dir . '/redirect_uris.sql'
		);
		$updater->addExtensionTable(
			'soa2_authings',
			$sql_dir . '/authings.sql'
		);
		$updater->addExtensionTable(
			'soa2_refresh_tokens',
			$sql_dir . '/refresh_tokens.sql'
		);
		$updater->addExtensionTable(
			'soa2_access_tokens',
			$sql_dir . '/access_tokens.sql'
		);
		return true;
	}
}
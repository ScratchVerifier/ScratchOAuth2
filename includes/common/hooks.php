<?php
namespace MediaWiki\Extension\ScratchOAuth2;

use DatabaseUpdater;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Login;

require_once __DIR__ . "/consts.php";
require_once __DIR__ . "/login.php";

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
		$updater->addExtensionField(
			'soa2_applications',
			'created_at',
			$sql_dir . '/applications_created_at.sql'
		);
		$updater->modifyExtensionTable(
			'soa2_redirect_uris',
			$sql_dir . '/redirect_uris_key.sql'
		);
		$updater->addExtensionField(
			'soa2_scratchers',
			'user_name_cased',
			$sql_dir . '/cased_usernames.sql'
		);
		$updater->addExtensionUpdate( [
			'MediaWiki\Extension\ScratchOAuth2\SOA2Hooks::fetchCasedUsernames'
		] );
		return true;
	}

	public static function fetchCasedUsernames( DatabaseUpdater $updater ) {
		// add cased usernames for those who don't have them
		$dbw = $updater->getDB();
		$res = $dbw->select(
			'soa2_scratchers',
			['user_id', 'user_name'],
			['user_name_cased' => null]
		);
		foreach ($res as $row) {
			$username = $row->user_name;
			$updater->output( "Fetching cased username for $username\n" );
			SOA2Login::api( $username );
		}
	}
}
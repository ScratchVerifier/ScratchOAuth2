<?php

namespace MediaWiki\Extension\ScratchOAuth2\Common;

require_once __DIR__ . "/consts.php";
require_once __DIR__ . "/db.php";

class SOA2Apps {
	/**
	 * Get the logged in user ID
	 * @return int the ID
	 */
	public static function userID() {
		global $wgRequest;
		return $wgRequest->getSession()->get( 'soa2_user_id', null );
	}
	/**
	 * Check if an app name is valid
	 * @param mixed $app_name the name to check
	 * @return bool validity
	 */
	public static function appNameValid( $app_name ) {
		return is_string($app_name) || $app_name === null;
	}
	/**
	 * Check if redirect URIs input are valid
	 * @param mixed $redirect_uris the URIs to check
	 * @return bool validity
	 */
	public static function redirectURIsValid( $redirect_uris ) {
		if ($redirect_uris === null) return true;
		if (!is_array($redirect_uris)) return false;
		if (in_array(false, array_map('is_string', $redirect_uris))) return false;
		return true;
	}
	/**
	 * Get an array of partial application objects
	 * @param int $owner_id the owner of the apps
	 */
	public static function partial( int $owner_id ) {
		$arr = [];
		$rows = SOA2DB::getPartialApplications( $owner_id );
		while ($row = $rows->fetchObject()) $arr[] = [
			'client_id' => intval($row->client_id),
			'app_name' => $row->app_name
		];
		return $arr;
	}
	/**
	 * Get a full application object
	 * @param int $client_id the ID of the app
	 * @param int|null $owner_id the owner of the app, to guard perms
	 */
	public static function application( int $client_id, ?int $owner_id ) {
		$app = SOA2DB::getApplication( $client_id, $owner_id );
		if (!$app) return null;
		$res = [
			'client_id' => intval($app->client_id),
			'client_secret' => $app->client_secret,
			'app_name' => $app->app_name,
			'flags' => intval($app->flags),
			'redirect_uris' => $app->redirect_uris
		];
		if (!$owner_id) $res['owner_id'] = intval($app->owner_id);
		return $res;
	}
	/**
	 * Register a new application
	 * @param int $owner_id the user registering the app
	 * @param string|null $app_name the name of the app
	 * @param array|null $redirect_uris array of redirect URIs
	 */
	public static function create(
		int $owner_id, ?string $app_name, ?array $redirect_uris
	) {
		// Step 2
		$client_id = random_int(0, 1 << 31 - 1);
		$client_secret = bin2hex(random_bytes(64));
		$flags = 0;
		if ($app_name === null) $flags |= AppFlags::NAME_APPROVED;
		// Step 3
		SOA2DB::createApplication(
			$client_id, $client_secret, $app_name,
			$owner_id, $flags
		);
		$redirect_uris = $redirect_uris ? array_filter($redirect_uris) : null;
		if ($redirect_uris) {
			// Step 4
			SOA2DB::storeRedirectURIs( $client_id, $redirect_uris );
		}
		return self::application( $client_id, $owner_id );
	}
	/**
	 * Update an existing application
	 * @param int $client_id the ID of the app to update
	 * @param int $owner_id the owner of the app, to guard perms
	 * @param bool $args['reset_secret'] if set and true, resets the secret
	 * @param string|null $args['app_name'] if set, sets the name and resets name approval
	 * @param array|null $args['redirect_uris'] if set, replaces the original list
	 */
	public static function update( int $client_id, int $owner_id, array $args ) {
		$app = SOA2DB::getApplication( $client_id, $owner_id, false );
		if (!$app) return null;
		$set = [];
		if (array_key_exists('reset_secret', $args) && $args['reset_secret']) {
			$client_secret = bin2hex(random_bytes(64));
			$set['client_secret'] = $client_secret;
		}
		if (
			array_key_exists('flags', $args)
			&& intval($app->flags) != $args['flags']
		) {
			$set['flags'] = $args['flags'];
		} else if (
			array_key_exists('app_name', $args)
			&& $app->app_name != $args['app_name']
		) {
			$app_name = $args['app_name'];
			$set['app_name'] = $app_name;
			$flags = intval($app->flags);
			$flags &= ~AppFlags::NAME_APPROVED;
			if ($app_name === null) $flags |= AppFlags::NAME_APPROVED;
			$set['flags'] = $flags;
		}
		if (!empty($set)) SOA2DB::updateApplication( $client_id, $set );
		if (array_key_exists('redirect_uris', $args)) {
			SOA2DB::deleteRedirectURIs( $client_id );
			$redirect_uris = $args['redirect_uris']
				? array_filter($args['redirect_uris']) : null;
			if ($redirect_uris)
				SOA2DB::storeRedirectURIs( $client_id, $redirect_uris );
		}
		return self::application( $client_id, $owner_id );
	}
	/**
	 * Update an application as an admin.
	 * @param int $client_id the application ID to update
	 * @param bool $args['reset_secret'] if set and true, resets the secret
	 * @param int $args['flags'] if set, directly updates flags
	 * @param array|null $args['redirect_uris'] if set, replaces the original list
	 */
	public static function adminUpdate( int $client_id, array $args ) {
		$app = SOA2DB::getApplication( $client_id, null, false );
		if (!$app) return null;
		$res = self::update(
			$client_id,
			intval($app->owner_id),
			array_filter($args, function ($k) {
				return (
					$k == 'reset_secret'
					|| $k == 'redirect_uris'
					|| $k == 'flags'
				);
			}, ARRAY_FILTER_USE_KEY)
		);
		$res['owner_id'] = intval($app->owner_id);
		return $res;
	}
	/**
	 * Delete an application
	 * @param int $client_id the ID of the app to delete
	 * @param int $owner_id the owner of the app, to guard perms
	 * @return bool whether the deletion was successful
	 */
	public static function delete( int $client_id, int $owner_id ) {
		$app = SOA2DB::getApplication( $client_id, $owner_id, false );
		if (!$app) return false;
		SOA2DB::deleteApplication( $client_id );
		return true;
	}
}
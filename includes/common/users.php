<?php
namespace MediaWiki\Extension\ScratchOAuth2\Common;

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/tokens.php";

use Html;

class SOA2Users {
	/**
	 * Make a profile <a> from a username.
	 * @param string $username the username
	 * @return string the link
	 */
	public static function makeProfileLink($username) {
		return Html::element(
			'a',
			[
				'href' => sprintf(SOA2_PROFILE_URL, $username),
				'target' => '_new'
			],
			$username
		);
	}
	/**
	 * Get a username by ID
	 * @param int $user_id the user ID
	 * @return string|null the username, or null if not found
	 */
	public static function getName( int $user_id ) {
		$user = SOA2DB::getUserById( $user_id );
		return $user ? $user->user_name : null;
	}
	public static function getID( string $user_name ) {
		$user = SOA2DB::getUserByName( $user_name );
		return $user ? intval($user->user_id) : null;
	}
	/**
	 * Get a user by their access token
	 * @param string $access_token the access token
	 * @return int HTTP status to return/that would be returned
	 * @return array the user info
	 */
	public static function getByToken( string $access_token ) {
		$token = SOA2DB::getAccessToken( $access_token );
		if (!$token) return 401;
		$refresh = SOA2Tokens::getRefreshToken( $token->refresh_token );
		if (!$refresh) return 401;
		if (!in_array('identify', $refresh['scopes'])) return 403;
		// Step 57
		if (!($username = self::getName( $refresh['user_id'] ))) return 404;
		$url = sprintf(SOA2_COMMENTS_API, $username, rand());
		$page = file_get_contents($url);
		if (strpos($page, '<h3 class="status-code">404</h3>') !== false) {
			$status = 203;
		} else {
			$status = 200;
		}
		return [
			'status' => $status,
			'user' => [
				'user_id' => $refresh['user_id'],
				'user_name' => $username,
			]
		];
	}
}
<?php
namespace MediaWiki\Extension\ScratchOAuth2\Common;

require_once __DIR__ . "/consts.php";
require_once __DIR__ . "/db.php";

use WebRequest;

class SOA2Auth {
	/**
	 * Get the data for this request.
	 * @param WebRequest $request the request
	 * @return mixed data, or false if invalid
	 */
	public static function requestData( WebRequest $request ) {
		if (!($client_id = $request->getInt('client_id'))) return false;
		if (!($state = $request->getVal('state'))) return false;
		$scopes = preg_split(
			SOA2_SCOPES_SPLIT_REGEX,
			$request->getVal('scopes', ''),
			PREG_SPLIT_NO_EMPTY
		);
		if (count($scopes) < 1) return false;
		foreach ($scopes as $scope) {
			if (!in_array($scope, SOA2_SCOPES)) return false;
		}
		$redirect_uri = $request->getVal('redirect_uri');
		$app = SOA2DB::getApplication( $client_id );
		if (!$app) return false;
		if ($redirect_uri && !in_array(
			$redirect_uri, $app->redirect_uris
		)) return false;
		return [
			'client_id' => $client_id,
			'state' => $state,
			'redirect_uri' => $redirect_uri,
			'scopes' => $scopes,
			'owner_id' => $app->owner_id
		];
	}
	/**
	 * Start authorization.
	 * @param array $data Data returned by requestData()
	 * @param int $user_id ID of user authorizing the app
	 * @return string authing code to be set in session
	 */
	public static function start( array $data, int $user_id ) {
		$code = bin2hex(random_bytes(32)); // Step 26
		SOA2DB::startAuth( // Step 27
			$code, $data['client_id'], $user_id, $data['state'],
			$data['redirect_uri'], $data['scopes'], time() + SOA2_AUTH_EXPIRY
		);
		return $code;
	}
	/**
	 * Get auth by code.
	 * @param string $code authing code
	 */
	public static function get( string $code ) {
		$authing = SOA2DB::getAuth( $code );
		if (!$authing) return null;
		return [
			'code' => $authing->code,
			'client_id' => intval($authing->client_id),
			'user_id' => intval($authing->user_id),
			'state' => $authing->state,
			'redirect_uri' => $authing->redirect_uri,
			'scopes' => explode(' ', $authing->scopes),
			'expiry' => intval($authing->expiry),
		];
	}
	/**
	 * Cancel authorization.
	 * @param int $user_id ID of user cancelling auth
	 */
	public static function cancel( int $user_id ) {
		SOA2DB::cancelAuth( $user_id );
	}
}
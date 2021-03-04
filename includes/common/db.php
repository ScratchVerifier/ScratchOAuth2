<?php

namespace MediaWiki\Extension\ScratchOAuth2\Common;

class SOA2DB {
	public static function dbw() {
		return wfGetDB( DB_MASTER );
	}
	public static function dbr() {
		return wfGetDB( DB_REPLICA );
	}
	// login methods
	public static function saveUser( int $user_id, string $username ) {
		$values = [
			'user_id' => $user_id,
			'user_name' => strtolower($username)
		];
		self::dbw()->upsert(
			'soa2_scratchers', $values,
			array_keys($values), $values
		);
	}
	public static function getUserById( int $user_id ) {
		return self::dbr()->selectRow(
			'soa2_scratchers',
			['user_id', 'user_name'],
			['user_id' => $user_id]
		);
	}
	public static function getUserByName( string $username ) {
		return self::dbr()->selectRow(
			'soa2_scratchers',
			['user_id', 'user_name'],
			['user_name' => strtolower($username)]
		);
	}
	// application methods
	public static function getPartialApplications( int $owner_id ) {
		return self::dbr()->select(
			'soa2_applications',
			['client_id', 'app_name'],
			['owner_id' => $owner_id]
		);
	}
	public static function getApplication(
		int $client_id, ?int $owner_id = null, bool $fetch_redirect_uris = true
	) {
		$dbr = self::dbr();
		$conds = ['client_id' => $client_id];
		if ($owner_id) $conds['owner_id'] = $owner_id;
		$obj = $dbr->selectRow('soa2_applications', '*', $conds);
		if (!$obj) return null;
		if ($fetch_redirect_uris) {
			$redirect_uris = [];
			$rows = $dbr->select(
				'soa2_redirect_uris',
				['redirect_uri'],
				['client_id' => $client_id]
			);
			while ($row = $rows->fetchObject()) $redirect_uris[] = $row->redirect_uri;
			$obj->redirect_uris = $redirect_uris;
		}
		return $obj;
	}
	public static function createApplication(
		int $client_id, string $client_secret, ?string $app_name,
		int $owner_id, int $flags
	) {
		self::dbw()->insert(
			'soa2_applications',
			[
				'client_id' => $client_id,
				'client_secret' => $client_secret,
				'app_name' => $app_name,
				'owner_id' => $owner_id,
				'flags' => $flags
			]
		);
	}
	public static function storeRedirectURIs( int $client_id, array $redirect_uris ) {
		$uris = [];
		foreach ($redirect_uris as $uri) {
			$uris[] = ['redirect_uri' => $uri, 'client_id' => $client_id];
		}
		self::dbw()->insert('soa2_redirect_uris', $uris);
	}
	public static function updateApplication( int $client_id, array $set ) {
		self::dbw()->update(
			'soa2_applications', $set,
			['client_id' => $client_id]
		);
	}
	public static function deleteRedirectURIs( int $client_id ) {
		self::dbw()->delete('soa2_redirect_uris', ['client_id' => $client_id]);
	}
	public static function deleteApplication( int $client_id ) {
		$dbw = self::dbw();
		$dbw->delete('soa2_redirect_uris', ['client_id' => $client_id]);
		$dbw->delete('soa2_access_tokens', ['client_id' => $client_id]);
		$dbw->delete('soa2_refresh_tokens', ['client_id' => $client_id]);
		$dbw->delete('soa2_authings', ['client_id' => $client_id]);
		$dbw->delete('soa2_applications', ['client_id' => $client_id]);
	}
	// auth methods
	public static function startAuth(
		string $code, int $client_id, int $user_id, string $state,
		?string $redirect_uri, array $scopes, int $expiry
	) {
		self::dbw()->insert(
			'soa2_authings',
			[
				'code' => $code,
				'client_id' => $client_id,
				'user_id' => $user_id,
				'state' => $state,
				'redirect_uri' => $redirect_uri,
				'scopes' => implode(' ', $scopes),
				'expiry' => $expiry
			]
		);
	}
	public static function getAuth( string $code ) {
		self::expireAuth();
		return self::dbr()->selectRow('soa2_authings', '*', ['code' => $code]);
	}
	public static function expireAuth() {
		self::dbw()->delete('soa2_authings', 'expiry<' . time());
	}
	public static function cancelAuth( int $user_id ) {
		self::dbw()->delete('soa2_authings', ['user_id' => $user_id]);
	}
	public static function useAuth( string $code ) {
		self::dbw()->delete('soa2_authings', ['code' => $code]);
	}
	// token methods
	public static function saveRefreshToken(
		string $token, int $client_id, int $user_id, array $scopes, int $expiry
	) {
		$dbw = self::dbw();
		$dbw->delete(
			'soa2_refresh_tokens',
			['client_id' => $client_id, 'user_id' => $user_id]
		);
		$dbw->insert(
			'soa2_refresh_tokens',
			[
				'token' => $token,
				'client_id' => $client_id,
				'user_id' => $user_id,
				'scopes' => implode(' ', $scopes),
				'expiry' => $expiry
			]
		);
	}
	public static function saveAccessToken(
		string $token, string $refresh_token, int $client_id, int $user_id, int $expiry
	) {
		$dbw = self::dbw();
		$dbw->delete(
			'soa2_access_tokens',
			['client_id' => $client_id, 'refresh_token' => $refresh_token]
		);
		$dbw->insert(
			'soa2_access_tokens',
			[
				'token' => $token,
				'refresh_token' => $refresh_token,
				'client_id' => $client_id,
				'user_id' => $user_id,
				'expiry' => $expiry
			]
		);
	}
	public static function getRefreshToken( string $token ) {
		// Unlike authings, we don't expire refresh tokens automatically.
		// This is to let the API give the client a 410 Gone on expired tokens.
		// Instead, any previous refresh tokens are deleted before making new ones.
		return self::dbr()->selectRow('soa2_refresh_tokens', '*', ['token' => $token]);
	}
	public static function getAccessToken( string $token ) {
		// Unlike refresh tokens, we do expire these automatically
		self::expireAccessTokens();
		return self::dbr()->selectRow('soa2_access_tokens', '*', ['token' => $token]);
	}
	public static function expireAccessTokens() {
		self::dbw()->delete('soa2_access_tokens', 'expiry<' . time());
	}
}
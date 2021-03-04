<?php
namespace MediaWiki\Extension\ScratchOAuth2\Common;

require_once __DIR__ . "/db.php";

class SOA2Tokens {
	/**
	 * Generate a new refresh token.
	 * @param int $authing['client_id'] the client ID that owns this token
	 * @param int $authing['user_id'] the user ID that this token accesses
	 * @param array<string> $authing['scopes'] the scopes allowed by this token
	 * @param bool $access whether to generate an access token too, default true
	 */
	public static function newRefreshToken( array $authing, bool $access = true ) {
		$refresh_token = bin2hex(random_bytes(64)); // Step 42
		$refresh_expiry = time() + SOA2_REFRESH_TOKEN_EXPIRY;
		SOA2DB::saveRefreshToken( // Step 44
			$refresh_token, $authing['client_id'], $authing['user_id'],
			$authing['scopes'], $refresh_expiry
		);
		SOA2DB::useAuth( $authing['code'] ); // Step 46
		$res = [
			'refresh_token' => $refresh_token,
			'refresh_expiry' => $refresh_expiry,
			'scopes' => $authing['scopes'],
		];
		if ($access) {
			$res = array_merge($res, self::newAccessToken( $refresh_token, $authing ));
		}
		return $res;
	}
	/**
	 * Generate a new access token from a refresh token
	 * @param string $refresh_token the refresh token (must already exist)
	 * @param int $authing['client_id'] the client ID that owns this token
	 * @param int $authing['user_id'] the user ID that this token accesses
	 */
	public static function newAccessToken( string $refresh_token, array $authing ) {
		$access_token = bin2hex(random_bytes(64)); // Step 40 or 52
		$access_expiry = time() + SOA2_ACCESS_TOKEN_EXPIRY;
		SOA2DB::saveAccessToken( // Step 41
			$access_token, $refresh_token, $authing['client_id'],
			$authing['user_id'], $access_expiry
		);
		return [
			'access_token' => $access_token,
			'access_expiry' => $access_expiry,
		];
	}
	/**
	 * Get data for a refresh token
	 * @param string $token the token
	 * @param bool $null_on_expiry if true and the token is expired, returns null
	 */
	public static function getRefreshToken( string $token, bool $null_on_expiry = true ) {
		$token = SOA2DB::getRefreshToken( $token );
		if (!$token) return null;
		if ($null_on_expiry && intval($token->expiry) < time()) return null;
		return [
			'token' => $token->token,
			'client_id' => intval($token->client_id),
			'user_id' => intval($token->user_id),
			'scopes' => explode(' ', $token->scopes),
			'expiry' => intval($token->expiry)
		];
	}
}
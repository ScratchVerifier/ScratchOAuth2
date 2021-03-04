<?php
namespace MediaWiki\Extension\ScratchOAuth2\Api;

require_once dirname(__DIR__) . "/common/consts.php";
require_once dirname(__DIR__) . "/common/apps.php";
require_once dirname(__DIR__) . "/common/auth.php";
require_once dirname(__DIR__) . "/common/tokens.php";

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Apps;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Auth;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Tokens;

/**
 * Handle token requests
 * POST/PATCH /soa2/v0/tokens
 */
class Tokens extends SimpleHandler {
	public function run() {
		$request = $this->getRequest();
		switch ( $request->getMethod() ) {
			case 'POST': // Step 36
				return $this->post();
			case 'PATCH': // Step 48
				return $this->patch();
			default:
				return $this->http(405);
		}
	}
	private function post() {
		$data = $this->getRequest()->getBody()->getContents();
		$data = json_decode($data, true);
		if (!$data) return $this->http400();
		if (
			!array_key_exists('client_id', $data)
			|| !is_int($data['client_id'])
		) return $this->http400();
		$client_id = $data['client_id'];
		if (
			!array_key_exists('client_secret', $data)
			|| !is_string($data['client_secret'])
		) return $this->http400();
		$client_secret = $data['client_secret'];
		if (
			!array_key_exists('code', $data)
			|| !is_string($data['code'])
		) return $this->http400();
		$code = $data['code'];
		if (
			!array_key_exists('scopes', $data)
			|| !(is_string($data['scopes']) || is_array($data['scopes']))
		) return $this->http400();
		$scopes = $data['scopes'];
		if (is_string($scopes)) {
			$scopes = preg_split(SOA2_SCOPES_SPLIT_REGEX, $scopes);
		}
		foreach ($scopes as $scope) {
			if (!in_array($scope, SOA2_SCOPES)) return $this->http400();
		}
		// static checking done, time to check against DB
		$app = SOA2Apps::application( $client_id, null ); // Step 37
		if (!$app || !hash_equals($app['client_secret'], $client_secret)) {
			return $this->http(401); // Step 38
		}
		$authing = SOA2Auth::get( $code ); // Step 39
		if (!$authing) return $this->http(404);
		if ($authing['scopes'] != $scopes) return $this->http(417);
		$res = SOA2Tokens::newRefreshToken( $authing );
		return $this->getResponseFactory()->createJson($res); // Step 47
	}
	private function patch() {
		$data = $this->getRequest()->getBody()->getContents();
		$data = json_decode($data, true);
		if (!$data) return $this->http400();
		if (
			!array_key_exists('client_id', $data)
			|| !is_int($data['client_id'])
		) return $this->http400();
		$client_id = $data['client_id'];
		if (
			!array_key_exists('client_secret', $data)
			|| !is_string($data['client_secret'])
		) return $this->http400();
		$client_secret = $data['client_secret'];
		if (
			!array_key_exists('refresh_token', $data)
			|| !is_string($data['refresh_token'])
		) return $this->http400();
		$refresh_token = $data['refresh_token'];
		// static checking done, time to check against DB
		$app = SOA2Apps::application( $client_id, null ); // Step 49
		if (!$app || !hash_equals($app['client_secret'], $client_secret)) {
			return $this->http(401);
		}
		$token = SOA2Tokens::getRefreshToken( $refresh_token, false ); // Step 51
		if (!$token) return $this->http(404);
		if ($token['client_id'] != $client_id) return $this->http(404);
		if ($token['expiry'] < time()) return $this->http(410);
		$res = [
			'refresh_token' => $token['token'],
			'refresh_expiry' => $token['expiry'],
			'scopes' => $token['scopes']
		];
		$at = SOA2Tokens::newAccessToken( $token['token'], $token );
		$res = array_merge($res, $at);
		return $this->getResponseFactory()->createJson($res); // Step 54
	}
	private function http400() {
		return $this->http(400);
	}
	private function http( int $status ) {
		return $this->getResponseFactory()->createHttpError($status);
	}
	public function needsWriteAccess() {
		return false;
	}
	public function getParamSettings() {
		return [];
	}
}
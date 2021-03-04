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
			default:
				return $this->getResponseFactory()->createHttpError(405);
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
		$app = SOA2Apps::application( $client_id, null ); // Step 37
		if (!$app || !hash_equals($app['client_secret'], $client_secret)) {
			return $this->getResponseFactory()->createHttpError(401); // Step 38
		}
		$authing = SOA2Auth::get( $code ); // Step 39
		if (!$authing) return $this->getResponseFactory()->createHttpError(404);
		if ($authing['scopes'] != $scopes) {
			return $this->getResponseFactory()->createHttpError(417);
		}
		$res = SOA2Tokens::newRefreshToken( $authing );
		return $this->getResponseFactory()->createJson($res); // Step 47
	}
	private function http400() {
		return $this->getResponseFactory()->createHttpError(400);
	}
	public function needsWriteAccess() {
		return false;
	}
	public function getParamSettings() {
		return [];
	}
}
<?php
namespace MediaWiki\Extension\ScratchOAuth2\Api;

require_once dirname(__DIR__) . "/common/consts.php";
require_once dirname(__DIR__) . "/common/login.php";

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Login;

/**
 * Handle logins
 * PUT/POST /soa2/v0/login/{username}
 */
class Login extends SimpleHandler {
	public function run( $username ) {
		$request = $this->getRequest();
		if (!preg_match(SOA2_USERNAME_REGEX, $username)) {
			return $this->getResponseFactory()->createHttpError(400);
		}
		switch ( $request->getMethod() ) {
			case 'PUT': // Step 13, ish
				return $this->put( $username );
			case 'POST': // Step 18, ish
				return $this->post( $username );
			default:
				return $this->getResponseFactory()->createHttpError(405);
		}
	}
	private function put( $username ) {
		$resp = SOA2Login::codes( $username ); // Step 16
		if (!$resp) return $this->getResponseFactory()->createHttpError(404);
		return $this->getResponseFactory()->createJson($resp);
	}
	private function post( $username ) {
		$data = $this->getRequest()->getBody()->getContents();
		$data = json_decode($data, true);
		if (!$data || !array_key_exists('csrf', $data) || !is_string($data['csrf'])) {
			return $this->getResponseFactory()->createHttpError(400);
		}
		$success = SOA2Login::login( $username, (string)$data['csrf'] );
		if (!$success) return $this->getResponseFactory()->createHttpError(403);
		return $this->getResponseFactory()->createNoContent();
	}
	public function needsWriteAccess() {
		return false;
	}
	public function getParamSettings() {
		return [
			'username' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
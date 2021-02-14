<?php
namespace MediaWiki\Extension\ScratchOAuth2\Api;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

require_once dirname(__DIR__) . "/common/consts.php";
require_once dirname(__DIR__) . "/common/login.php";

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
			default:
				return $this->getResponseFactory()->createHttpError(405);
		}
	}
	private function put( $username ) {
		$resp = SOA2Login::codes( $username ); // Step 16
		if (!$resp) return $this->getResponseFactory()->createHttpError(404);
		return $this->getResponseFactory()->createJson($resp);
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
<?php
namespace MediaWiki\Extension\ScratchOAuth2\Api;

require_once dirname(__DIR__) . "/common/users.php";

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Users;

/**
 * Get user by access token
 * GET /soa2/v0/user
 */
class User extends SimpleHandler {
	public function run() {
		$request = $this->getRequest();
		$authHeader = $request->getHeaderLine('Authorization');
		if (!$authHeader) return $this->http(401);
		$auth = explode(' ', $authHeader);
		if (
			count($auth) != 2 || strtolower($auth[0]) != 'bearer'
		) return $this->http(401);
		$token = base64_decode($auth[1], true);
		if (!$token) return $this->http(401);
		$data = SOA2Users::getByToken( $token );
		if (is_int($data)) return $this->http($data);
		$res = $this->getResponseFactory()->createJson($data['user']);
		$res->setStatus($data['status']);
		return $res; // Step 58
	}
	private function http( int $status ) {
		return $this->getResponseFactory()->createHttpError($status);
	}
}
<?php
namespace MediaWiki\Extension\ScratchOAuth2\Api;

require_once dirname(__DIR__) . "/common/consts.php";
require_once dirname(__DIR__) . "/common/apps.php";

use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Apps;

/**
 * Handle non-specific routes
 * GET/PUT /soa2/v0/applications
 */
class GenericApps extends SimpleHandler {
	public function run() {
		$owner_id = SOA2Apps::userID();
		if (!$owner_id) return $this->getResponseFactory()->createHttpError(401);
		$request = $this->getRequest();
		switch ( $request->getMethod() ) {
			case 'GET':
				return $this->get( $owner_id );
			case 'PUT':
				return $this->put( $owner_id );
			default:
				return $this->getResponseFactory()->createHttpError(405);
		}
	}
	private function http400() {
		return $this->getResponseFactory()->createHttpError(400);
	}
	private function get( int $owner_id ) {
		$arr = SOA2Apps::partial( $owner_id );
		return $this->getResponseFactory()->createJson($arr);
	}
	private function put( int $owner_id ) {
		$data = $this->getRequest()->getBody()->getContents();
		$data = json_decode($data, true);
		if (!$data) return $this->http400();
		if (
			!array_key_exists('app_name', $data)
			|| !SOA2Apps::appNameValid($data['app_name'])
		) return $this->http400();
		$app_name = $data['app_name'];
		if (
			!array_key_exists('redirect_uris', $data)
			|| !SOA2Apps::redirectURIsValid($data['redirect_uris'])
		) return $this->http400();
		$redirect_uris = $data['redirect_uris'];
		return $this->getResponseFactory()->createJson(
			SOA2Apps::create( $owner_id, $app_name, $redirect_uris )
		);
	}
	public function getParamSettings() {
		return [];
	}
}
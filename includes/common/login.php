<?php

class SOA2Login {
	/**
	 * Get the data needed to complete a login.
	 * @param string $username the username to get the codes for
	 */
	public static function codes( string $username ) {
		global $wgRequest;
		// get user data from API
		$user = json_decode(file_get_contents(sprintf(
			SOA2_USERS_API, urlencode($username))), true);
		if (!$user) return null;
		// save user data
		$username = $user['username'];
		$db = wfGetDB( DB_MASTER );
		$values = [
			'user_id' => $user['id'],
			'user_name' => $username
		];
		$db->upsert('soa2_scratchers', $values, array_keys($values), $values);
		// actually do the code generation
		$session = $wgRequest->getSession();
		$session->persist();
		$csrf = $session->getToken()->toString();
		// Step 15
		$code = hash(
			'sha256',
			hash('sha256', $csrf)
			. hash('sha256', $username)
			. hash('sha256', '' . floor(time() / SOA2_CODE_EXPIRY))
		);
		return [ 'username' => $username, 'csrf' => $csrf, 'code' => $code ];
	}
}
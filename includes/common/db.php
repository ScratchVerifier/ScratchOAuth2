<?php

namespace MediaWiki\Extension\ScratchOAuth2\Common;

class SOA2DB {
	public static function dbw() {
		return wfGetDB( DB_MASTER );
	}
	public static function dbr() {
		return wfGetDB( DB_REPLICA );
	}
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
}
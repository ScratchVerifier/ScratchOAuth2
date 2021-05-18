<?php
namespace MediaWiki\Extension\ScratchOAuth2\Common;

require_once dirname(__DIR__) . "/common/db.php";
require_once dirname(__DIR__) . "/common/users.php";

use ReverseChronologicalPager;
use Html;
use SpecialPage;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2DB;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Users;

class AppPager extends ReverseChronologicalPager {
	private $check;

	function __construct($check = false) {
		$this->check = $check;
		$this->mDb = SOA2DB::dbr();
		parent::__construct();
	}

	function getQueryInfo() {
		$info = [
			'tables' => [
				'soa2_applications',
				'soa2_scratchers'
			],
			'fields' => [
				'client_id',
				'created_at',
				'app_name',
				'user_name AS owner_name'
			],
			'join_conds' => [
				'soa2_scratchers' => ['LEFT JOIN', 'user_id=owner_id']
			]
		];
		if ($this->check) $info['conds'] = ['flags&1=0'];
		return $info;
	}

	function getIndexField() {
		return 'created_at';
	}

	function formatRow($row) {
		$out = Html::openElement('tr');
		$app_name = $row->app_name ?: wfMessage('soa2-unnamed-app');
		$client_id = intval($row->client_id);
		$href = SpecialPage::getTitleFor(
			'SOA2Admin', 'app/' . $client_id )->getLinkURL();
		$out .= Html::rawElement('td', [], Html::element(
			'a',
			['href' => $href, 'target' => '_new'],
			$app_name
		));
		$out .= Html::rawElement(
			'td', [], SOA2Users::makeProfileLink( $row->owner_name )
		);
		if ($this->check) {
			$out .= Html::rawElement('td', [], Html::check(
				'client_ids[]', false,
				[ 'value' => $client_id, 'id' => "soa2-app-$client_id-approval-input" ]
			));
		}
		$out .= Html::closeElement('tr');

		return $out;
	}
}
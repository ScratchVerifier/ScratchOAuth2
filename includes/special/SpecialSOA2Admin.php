<?php
namespace MediaWiki\Extension\ScratchOAuth2\Special;

require_once dirname(__DIR__) . "/common/consts.php";
require_once dirname(__DIR__) . "/common/apps.php";
require_once dirname(__DIR__) . "/common/users.php";

use SpecialPage;
use Html;
use ReflectionClass;
use MediaWiki\Extension\ScratchOAuth2\Common\AppFlags;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Apps;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Users;

function makeProfileLink($username) {
	return Html::element(
		'a',
		[
			'href' => sprintf(SOA2_PROFILE_URL, $username),
			'target' => '_new'
		],
		$username
	);
}

class SpecialSOA2Admin extends SpecialPage {
	public function __construct() {
		parent::__construct( 'SOA2Admin' );
	}

	public function execute( $par ) {
		$this->checkReadOnly();
		$out = $this->getOutput();
		$out->setIndexPolicy( 'noindex' );
		$user_id = intval(SOA2Apps::userID());
		if (!$user_id) {
			$out->setPageTitle( wfMessage('notloggedin')->escaped() );
			$out->redirect(
				$this->getTitleFor( 'ScratchOAuth2', 'login' )->getFullURL([
					'returnto' => $this->getPageTitle( $par )->getLinkURL()
				]), 303
			);
			return;
		}
		// temp until config
		if ($user_id != 10114764 && $user_id != 35751470 && $user_id != 62962013) {
			$out->showErrorPage('soa2-not-admin-title', 'soa2-not-admin');
			return;
		}
		$path = explode('/', $par);
		$par = $path[0];
		$path = array_slice($path, 1);
		switch ( $par ) {
			case 'approvals':
				$this->approvals( $path );
				break;
			case 'app':
				$this->app( $path );
				break;
			default:
				$out->setPageTitle( 'SOA2Admin' );
				$out->addHTML(Html::element(
					'pre', [],
					var_export($this->getRequest()->getIntArray('test'), true)
				));
				$out->addHTML(Html::element('pre', [], var_export($user_id, true)));
		}
	}
	public function app( array $path ) {
		$out = $this->getOutput();
		if (
			count($path) < 1
			|| !ctype_digit($path[0])
			|| !($app = SOA2Apps::application( intval($path[0]), null ))
		) {
			$out->showErrorPage('soa2-no-app-title', 'soa2-no-app', $path);
			return;
		}
		if ($this->getRequest()->wasPosted()) {
			$app = $this->saveApp( $path, $app );
		}
		$out->setPageTitle(
			htmlspecialchars($app['app_name'])
			?: wfMessage('soa2-unnamed-app')->escaped()
		);
		$out->addReturnTo($this->getPageTitle());
		$out->addHTML(Html::openElement('form', [ 'method' => 'POST' ]));
		$out->addHTML(Html::hidden('token',
			$this->getRequest()->getSession()->getToken()->toString()));
		$out->addHTML(Html::openElement('table', [ 'class' => 'wikitable' ]));
		$out->addHTML(Html::openElement('tr'));
			$out->addHTML(Html::element('th', [], wfMessage('soa2-app-id')->text()));
			$out->addHTML(Html::rawElement('td', [], Html::element(
				'code', [], (string)$app['client_id']
			)));
		$out->addHTML(Html::closeElement('tr'));
		$out->addHTML(Html::openElement('tr'));
			$out->addHTML(Html::element(
				'th', [],
				wfMessage('soa2-app-name')->text(),
			));
			$out->addHTML(Html::element(
				'td', [],
				$app['app_name']
			));
		$out->addHTML(Html::closeElement('tr'));
		$out->addHTML(Html::openElement('tr'));
			$out->addHTML(Html::element(
				'th', [],
				wfMessage('soa2-app-owner')->text()
			));
			$out->addHTML(Html::rawElement(
				'td', [], makeProfileLink(SOA2Users::getName($app['owner_id']))));
		$out->addHTML(Html::closeElement('tr'));
		$out->addHTML(Html::closeElement('table'));
		$out->addHTML(Html::rawElement('p', [], Html::check(
			'reset_secret', false, [ 'id' => 'soa2-reset-secret-input' ]
		) . Html::label(
			wfMessage('soa2-app-reset')->escaped(),
			'soa2-reset-secret-input'
		)));
		// flags
		$out->addHTML(Html::openElement('table', [ 'class' => 'wikitable' ]));
		$out->addHTML(Html::element('caption', [], wfMessage('soa2-flags')->text()));
		$flagsClass = new ReflectionClass(AppFlags::class);
		foreach ($flagsClass->getConstants() as $name => $value) {
			$out->addHTML(Html::openElement('tr'));
			$out->addHTML(Html::rawElement('th', [], Html::label(
				wfMessage("soa2-flags-$name")->text(),
				"soa2-flag-$name-input",
				[ 'title' => $name ],
			)));
			$out->addHTML(Html::rawElement('td', [], Html::check(
				'flags[]', (bool)($app['flags'] & $value),
				[ 'value' => $value, 'id' => "soa2-flag-$name-input" ]
			)));
		}
		$out->addHTML(Html::closeElement('table'));
		$out->addHTML(Html::textarea(
			'redirect_uris',
			implode("\n", $app['redirect_uris']),
			[ 'id' => 'soa2-redirect-uris-input']
		));
		$out->addHTML(Html::rawElement('p', [], Html::input(
			'save',
			wfMessage('soa2-app-save')->text(),
			'submit'
		)));
		$out->addHTML(Html::closeElement('form'));
	}
	public function saveApp( array $path, array $app ) {
		$request = $this->getRequest();
		if (!$request->getSession()->getToken()->match($request->getVal('token'))) {
			$out->addWikiMsg( 'sessionfailure' );
			return;
		}
		$args = [];
		if ($request->getCheck('reset_secret')) {
			$args['reset_secret'] = true;
		}
		$flags = 0;
		foreach ($request->getIntArray('flags', []) as $flag) {
			$flags |= $flag;
		}
		if ($app['flags'] != $flags) {
			$args['flags'] = $flags;
		}
		$uris = array_map('trim', explode(
			"\n", $request->getText('redirect_uris')
		));
		if ($app['redirect_uris'] != $uris) {
			$args['redirect_uris'] = $uris;
		}
		return SOA2Apps::adminUpdate( $app['client_id'], $args );
	}
	public function approvals( array $path ) {
		$request = $this->getRequest();
		if ($request->wasPosted()) {
			SOA2Apps::approveNames($request->getIntArray('client_ids', []));
		}
		$apps = SOA2Apps::needsNameApproval(20);
		$out = $this->getOutput();
		$out->setPageTitle( wfMessage('soa2-admin-approvals')->escaped() );
		$out->addHTML(Html::openElement('form', [ 'method' => 'POST' ]));
		$out->addHTML(Html::openElement('table', [ 'class' => 'wikitable mw-sortable' ]));
		$out->addHTML(Html::openElement('tr'));
		$out->addHTML(Html::element('th', [], wfMessage('soa2-admin-approvals-name')->text()));
		$out->addHTML(Html::element('th', [], wfMessage('soa2-app-owner')->text()));
		$out->addHTML(Html::element('th', [], wfMessage('soa2-admin-approvals-check')->text()));
		$out->addHTML(Html::closeElement('tr'));
		foreach ($apps as $app) {
			$out->addHTML(Html::openElement('tr'));
			$app_name = $app['app_name'];
			$client_id = $app['client_id'];
			$out->addHTML(Html::rawElement('td', [], Html::element(
				'a',
				[
					'href' => $this->getPageTitle( 'app/' . $client_id )->getLinkURL(),
					'target' => '_new',
				],
				htmlspecialchars($app['app_name'])
			)));
			$out->addHTML(Html::rawElement('td', [], makeProfileLink($app['owner_name'])));
			$out->addHTML(Html::rawElement('td', [], Html::check(
				'client_ids[]', false,
				[ 'value' => $client_id, 'id' => "soa2-app-$client_id-approval-input" ]
			)));
			$out->addHTML(Html::closeElement('tr'));
		}
		$out->addHTML(Html::closeElement('table'));
		$out->addHTML(Html::rawElement('p', [], Html::input(
			'save',
			wfMessage('soa2-admin-approvals-submit')->text(),
			'submit'
		)));
		$out->addHTML(Html::closeElement('form'));
	}
}
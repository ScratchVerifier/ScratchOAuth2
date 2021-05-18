<?php
namespace MediaWiki\Extension\ScratchOAuth2\Special;

require_once dirname(__DIR__) . "/common/consts.php";
require_once dirname(__DIR__) . "/common/apps.php";
require_once dirname(__DIR__) . "/common/users.php";
require_once dirname(__DIR__) . "/common/pager.php";

use SpecialPage;
use Html;
use ReflectionClass;
use MediaWiki\Extension\ScratchOAuth2\Common\AppFlags;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Apps;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Users;
use MediaWiki\Extension\ScratchOAuth2\Common\AppPager;

class SpecialSOA2Admin extends SpecialPage {
	public function __construct() {
		parent::__construct( 'SOA2Admin' );
	}

	public function execute( $par ) {
		global $wgSOA2AdminUsers;
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
		if (!in_array($user_id, $wgSOA2AdminUsers)) {
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
			case 'apps':
				$this->apps( $path );
				break;
			case 'app':
				$this->app( $path );
				break;
			default:
				$out->setPageTitle( 'SOA2Admin' );
				$out->addHTML(Html::rawElement('p', [], Html::element(
					'a', [ 'href' => $this->getPageTitle( 'apps' )->getLinkURL() ],
					wfMessage('soa2-admin-apps-title')->text()
				)));
				$out->addHTML(Html::rawElement('p', [], Html::element(
					'a', [ 'href' => $this->getPageTitle( 'approvals' )->getLinkURL() ],
					wfMessage('soa2-admin-approvals')->text()
				)));
		}
	}
	public function apps( array $path ) {
		$out = $this->getOutput();
		if ($username = $this->getRequest()->getVal('username')) {
			$out->redirect(
				$this->getPageTitle( 'apps/' . $username )->getLinkURL(), 303);
				return;
		}
		$username = $path[0];
		$user_id = SOA2Users::getID( $username ?: '' );
		if ($user_id) {
			$out->addReturnTo($this->getPageTitle( 'apps' ));
			$out->setPageTitle(
				wfMessage('soa2-admin-apps-namedtitle', $username)->escaped() );
			$out->addHTML(Html::openElement('ul'));
			foreach (SOA2Apps::partial( $user_id ) as $app) {
				$link = Html::element('a', [
					'href' => $this->getPageTitle( 'app/' . $app['client_id'] )->getLinkURL()
				], $app['app_name'] ?: wfMessage('soa2-unnamed-app')->text());
				$out->addHTML(Html::rawElement('li', [], $link));
			}
			$out->addHTML(Html::closeElement('ul'));
		} else {
			if (count($path) > 0) {
				$out->redirect(
					$this->getPageTitle( 'apps' )->getLinkURL(), 303);
				return;
			}
			$out->addReturnTo($this->getPageTitle());
			$out->setPageTitle( wfMessage('soa2-admin-apps-title')->escaped() );
			$out->addHTML(Html::openElement('form', [ 'method' => 'GET' ]));
			$out->addHTML(Html::rawElement('p', [], Html::input(
				'username', '', 'text', [ 'id' => 'soa2-username-input' ]
			)));
			$out->addHTML(Html::rawElement('p', [], Html::submitButton(
				wfMessage('soa2-admin-apps-submit')->escaped(), []
			)));
			$out->addHTML(Html::closeElement('form'));
			// table of all apps
			$this->appListTable(
				false, wfMessage('soa2-admin-all-apps-title')->text()
			);
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
		$owner = SOA2Users::getName($app['owner_id']);
		$out->addReturnTo($this->getPageTitle( 'apps/' . $owner ));
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
				'td', [], SOA2Users::makeProfileLink( $owner )));
		$out->addHTML(Html::closeElement('tr'));
		$out->addHTML(Html::openElement('tr'));
			$out->addHTML(Html::element(
				'th', [],
				wfMessage('soa2-app-created-at')->text()
			));
			$out->addHTML(Html::rawElement(
				'td', [],
				wfTimestamp( TS_ISO_8601, $app['created_at'] )
			));
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
		$out = $this->getOutput();
		if ($request->wasPosted()) {
			if (!$request->getSession()->getToken()->match($request->getVal('token'))) {
				$out->addWikiMsg( 'sessionfailure' );
			} else {
				SOA2Apps::approveNames($request->getIntArray('client_ids', []));
			}
		}
		$out->setPageTitle( wfMessage('soa2-admin-approvals')->escaped() );
		$out->addReturnTo($this->getPageTitle());
		$out->addHTML(Html::openElement('form', [ 'method' => 'POST' ]));
		$out->addHTML(Html::hidden('token',
			$request->getSession()->getToken()->toString()));
		$this->appListTable( true );
		$out->addHTML(Html::rawElement('p', [], Html::input(
			'save',
			wfMessage('soa2-admin-approvals-submit')->text(),
			'submit'
		)));
		$out->addHTML(Html::closeElement('form'));
	}

	public function appListTable( bool $check = false, ?string $caption = null ) {
		$pager = new AppPager($check);
		$out = $this->getOutput();
		$out->addHTML(Html::openElement('table', [ 'class' => 'wikitable mw-sortable' ]));
		if ($caption)
			$out->addHTML(Html::element('caption', [], $caption));
		$out->addHTML(Html::openElement('tr'));
		$out->addHTML(Html::element('th', [], wfMessage('soa2-admin-approvals-name')->text()));
		$out->addHTML(Html::element('th', [], wfMessage('soa2-app-owner')->text()));
		if ($check) $out->addHTML(Html::element(
			'th', [], wfMessage('soa2-admin-approvals-check')->text()
		));
		$out->addHTML(Html::closeElement('tr'));
		$out->addHTML($pager->getBody());
		$out->addHTML(Html::closeElement('table'));
		$out->addHTML(Html::rawElement('p', [], $pager->getNavigationBar()));
	}
}
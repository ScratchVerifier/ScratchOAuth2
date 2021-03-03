<?php
namespace MediaWiki\Extension\ScratchOAuth2\Special;

require_once dirname(__DIR__) . "/common/consts.php";
require_once dirname(__DIR__) . "/common/login.php";
require_once dirname(__DIR__) . "/common/apps.php";
require_once dirname(__DIR__) . "/common/auth.php";

use SpecialPage;
use WebRequest;
use Html;
use Title;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Login;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Apps;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Auth;
use MediaWiki\Extension\ScratchOAuth2\Common\AppFlags;

class SpecialScratchOAuth2 extends SpecialPage {
	public function __construct() {
		parent::__construct( 'ScratchOAuth2' );
	}

	public function execute( $par ) {
		$this->checkReadOnly();
		$out = $this->getOutput();
		$out->setIndexPolicy( 'noindex' );
		switch ( $par ) {
			case 'login':
				$this->specialLogin();
				break;
			case 'authorize':
				$this->specialAuth();
				break;
			default:
				$out->setPageTitle( 'ScratchOAuth2' );
				$user_id = SOA2Apps::userID();
				$out->addHTML(
					"<p>Your Scratch user ID is "
					. ($user_id ?: 'not set')
					. "</p>"
				);
				if ($this->getRequest()->getSession()->exists('soa2_authing')) {
					$out->addHTML(Html::element('pre', [], var_export(
						SOA2Auth::get( $this->getRequest()->getSessionData('soa2_authing') ), true
					)));
				}
		}
	}

	public function specialLogin( $error = null ) {
		$request = $this->getRequest();
		$out = $this->getOutput();
		$out->setPageTitle( wfMessage('soa2-login-title')->escaped() );
		if ($error) {
			$this->error($error);
		} else if (
			$request->wasPosted() && $request->getCheck( 'username' )
		) {
			$username = $request->getVal( 'username', '', );
			if (!preg_match(SOA2_USERNAME_REGEX, $username)) {
				$this->specialLogin(
					wfMessage('soa2-invalid-username', $username)->plain()
				);
				return;
			}
			if ($request->getCheck( 'token' )) {
				$this->doLogin( $request );
				return;
			}
			$this->loginForm( $request );
			return;
		}
		// Step 11
		$out->addHTML(Html::openElement('form', [ 'method' => 'POST' ]));
		$out->addHTML(Html::rawElement('p', [], Html::label(
			wfMessage('soa2-scratch-username')->escaped(),
			'soa2-username-input',
		)));
		// Step 12
		$out->addHTML(Html::rawElement('p', [], Html::input(
			'username',
			$request->getVal('username', ''),
			'text',
			[ 'id' => 'soa2-username-input' ]
		)));
		$out->addHTML(Html::rawElement('p', [], Html::submitButton(
			wfMessage('soa2-next')->escaped(), []
		)));
		$out->addHTML(Html::closeElement('form'));
	}

	public function loginForm( WebRequest $request ) { // Step 13
		$out = $this->getOutput();
		$username = $request->getVal('username');
		$codes = SOA2Login::codes( $username );
		$out->addHTML(Html::openElement('form', [ 'method' => 'POST' ]));
		$out->addHTML(Html::hidden('username', $username));
		$out->addHTML(Html::hidden('token', $codes['csrf'])); // Step 14
		$profile = Html::element(
			'a',
			[
				'href' => sprintf(SOA2_PROFILE_URL, urlencode($username)),
				'target' => '_new'
			],
			wfMessage('soa2-your-profile')->plain()
		);
		// Step 16
		$out->addHTML(Html::rawElement(
			'p', [],
			wfMessage('soa2-vercode-explanation', $profile)->plain()
		));
		$out->addHTML(Html::rawElement('p', [], Html::element(
			'code', [], $codes['code']
		)));
		$out->addHTML(Html::rawElement('p', [], Html::submitButton(
			wfMessage('soa2-login')->plain(), []
		)));
		$out->addHTML(Html::closeElement('form'));
	}

	public function doLogin( WebRequest $request ) { // Step 18
		$success = SOA2Login::login(
			$request->getVal('username'),
			$request->getVal('token')
		);
		if (!$success) {
			$this->specialLogin(
				wfMessage('soa2-login-failed')->plain()
			);
			return;
		}
		// Step 24
		$link = $request->getVal(
			'returnto',
			$this->getPageTitle()->getFullURL()
		);
		$this->getOutput()->redirect( $link, 303 );
	}

	public function error( string $error ) {
		$this->getOutput()->addHTML(Html::rawElement(
			'p', [],
			Html::element('span', [ 'class' => 'error' ], $error)
		));
	}

	public function specialAuth( ?string $error = null ) { // Step 8 or 25
		$user_id = SOA2Apps::userID();
		$request = $this->getRequest();
		$session = $request->getSession();
		$session->persist();
		$out = $this->getOutput();
		if (!$user_id) { // Step 9
			// Step 10
			$out->redirect( $this->getPageTitle( 'login' )->getLinkURL([
				'returnto' => $request->getRequestURL()
			]), 303 );
			return;
		}
		if ($error) {
			$this->error($error);
		} else if ($request->wasPosted()) {
			$this->doAuth( $user_id );
			return;
		}
		if (!($data = SOA2Auth::requestData( $request ))) { // Step 25
			$out->setPageTitle( wfMessage('soa2-auth-invalid-title')->escaped() );
			$out->addHTML(wfMessage('soa2-auth-invalid')->parse());
			$out->returnToMain();
			return;
		}
		$app = SOA2Apps::application( $data['client_id'], $data['owner_id'] );
		if (!$session->exists('soa2_authing')) {
			// Step 28
			$session->set('soa2_authing', SOA2Auth::start( $data, $user_id ));
		}
		if (!$app['app_name']) {
			$name = wfMessage('soa2-unnamed-app')->text();
		} else if (!($app['flags'] & AppFlags::NAME_APPROVED)) {
			$name = wfMessage('soa2-unmoderated-app')->text();
		} else {
			$name = $app['app_name'];
		}
		$out->setPageTitle( wfMessage('soa2-auth-title', $name)->escaped() );
		$out->addHTML(wfMessage('soa2-auth-desc', htmlspecialchars($name))->parse());
		$out->addHTML(Html::openElement('ul'));
		foreach ($data['scopes'] as $scope) {
			$out->addHTML(Html::element(
				'li', [], wfMessage('soa2-scope-' . $scope)->text()));
		}
		$out->addHTML(Html::closeElement('ul'));
		$out->addHTML(Html::openElement('form', [ 'method' => 'POST' ]));
		$out->addHTML(Html::hidden('token', $session->getToken()->toString()));
		$out->addHTML(Html::rawElement('p', [], Html::input(
			'confirm',
			wfMessage('confirm')->text(),
			'submit'
		) . Html::input(
			'cancel',
			wfMessage('cancel')->text(),
			'submit'
		)));
		$out->addHTML(Html::closeElement('form'));
	}

	public function doAuth( int $user_id ) {
		$request = $this->getRequest();
		if (!$request->getSession()->getToken()->match($request->getVal('token'))) {
			$this->specialAuth( wfMessage('sessionfailure')->text() );
			return;
		}
		$out = $this->getOutput();
		if ($request->getCheck('cancel')) {
			SOA2Auth::cancel( $user_id );
			$request->getSession()->remove('soa2_authing');
			$out->setPageTitle( wfMessage('soa2-auth-cancelled-title')->escaped() );
			$out->addHTML(wfMessage('soa2-auth-cancelled')->parse());
			$out->returnToMain();
			return;
		}
	}
}
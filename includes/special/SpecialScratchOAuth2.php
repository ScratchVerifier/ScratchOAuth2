<?php
namespace MediaWiki\Extension\ScratchOAuth2\Special;

require_once dirname(__DIR__) . "/common/consts.php";
require_once dirname(__DIR__) . "/common/login.php";

use SpecialPage;
use WebRequest;
use Html;
use SOA2Login;

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
			default:
				$out->setPageTitle( 'ScratchOAuth2' );
				$out->addHTML("<p>Nothing to see here.</p>");
		}
	}

	public function specialLogin( $error = null ) {
		$request = $this->getRequest();
		$out = $this->getOutput();
		$out->setPageTitle( wfMessage('soa2-login-title')->escaped() );
		if (!$error && $request->wasPosted() && $request->getText('username', null)) {
			if ($request->getText('token', null)) {
				$this->doLogin( $request );
				return;
			}
			$this->loginForm( $request );
			return;
		}
		if ($error) {
			$out->addHTML(Html::rawElement(
				'p', [],
				Html::element('span', [ 'class' => 'error' ], $error)
			));
		}
		// Step 11
		$out->addHTML(Html::openElement('form', [ 'method' => 'POST' ]));
		$out->addHTML(Html::rawElement('p', [], Html::label(
			wfMessage('soa2-scratch-username')->escaped(),
			'soa2-username-input',
		)));
		// Step 12
		$out->addHTML(Html::rawElement('p', [], Html::input(
			'username', '', 'text',
			[ 'id' => 'soa2-username-input' ]
		)));
		$out->addHTML(Html::rawElement('p', [], Html::submitButton(
			wfMessage('soa2-next')->escaped(), []
		)));
		$out->addHTML(Html::closeElement('form'));
	}

	public function loginForm( WebRequest $request ) { // Step 13
		$out = $this->getOutput();
		$username = $request->getText('username');
		if (!preg_match(SOA2_USERNAME_REGEX, $username)) {
			$this->specialLogin(
				wfMessage('soa2-invalid-username', $username)->plain()
			);
			return;
		}
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
}
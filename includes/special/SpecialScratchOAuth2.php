<?php
namespace MediaWiki\Extension\ScratchOAuth2\Special;

require_once dirname(__DIR__) . "/common/consts.php";
require_once dirname(__DIR__) . "/common/login.php";

use SpecialPage;
use WebRequest;
use Html;
use Title;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Login;

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
				$user_id = SOA2Apps::userID();
				$out->addHTML(
					"<p>Your Scratch user ID is "
					. ($user_id ?: 'not set')
					. "</p>"
				);
		}
	}

	public function specialLogin( $error = null ) {
		$request = $this->getRequest();
		$out = $this->getOutput();
		$out->setPageTitle( wfMessage('soa2-login-title')->escaped() );
		if ($error) {
			$out->addHTML(Html::rawElement(
				'p', [],
				Html::element('span', [ 'class' => 'error' ], $error)
			));
		} else if (
			$request->wasPosted() && $request->getCheck( 'username' )
		) {
			$username = $request->getText( 'username', '', );
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
			$request->getText('username', ''),
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
		$username = $request->getText('username');
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
			$request->getText('username'),
			$request->getText('token')
		);
		if (!$success) {
			$this->specialLogin(
				wfMessage('soa2-login-failed')->plain()
			);
			return;
		}
		$out = $this->getOutput();
		$out->addHTML(Html::element('h2', [], wfMessage('soa2-login-success')->plain()));
		// Step 24
		$link = $request->getText(
			'returnto',
			Title::newFromText('Special:ScratchOAuth2')->getFullURL()
		);
		$link = Html::element(
			'a',
			[ 'href' => $link ],
			$link
		);
		$out->addHTML(Html::rawElement('p', [], wfMessage('returnto', $link)->text()));
	}
}
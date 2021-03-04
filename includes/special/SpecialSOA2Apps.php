<?php
namespace MediaWiki\Extension\ScratchOAuth2\Special;

require_once dirname(__DIR__) . "/common/consts.php";
require_once dirname(__DIR__) . "/common/apps.php";

use ReflectionClass;
use SpecialPage;
use WebRequest;
use Html;
use MediaWiki\Extension\ScratchOAuth2\Common\AppFlags;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Apps;

class SpecialSOA2Apps extends SpecialPage {
	public function __construct() {
		parent::__construct( 'SOA2Apps' );
	}

	public function execute( $par ) {
		$this->checkReadOnly();
		$out = $this->getOutput();
		$out->setIndexPolicy( 'noindex' );
		$owner_id = SOA2Apps::userID();
		if (!$owner_id) {
			$out->setPageTitle( wfMessage('notloggedin')->escaped() );
			$out->addWikiMsg('soa2-notloggedin');
			$out->addReturnTo( $this->getTitleFor( 'ScratchOAuth2', 'login' ), [
				'returnto' => $this->getPageTitle( $par )->getFullURL()
			] );
			return;
		}
		$this->getRequest()->getSession()->persist();
		if (ctype_digit($par)) {
			$this->app( $owner_id, intval($par) );
		} else if ($par == 'new') {
			$this->newApp( $owner_id );
		} else {
			$this->apps( $owner_id );
		}
	}

	public function apps( int $owner_id ) {
		$out = $this->getOutput();
		$out->setPageTitle( wfMessage('soa2-apps-title')->escaped() );
		$out->addWikiMsg('soa2-apps');
		$out->addHTML(Html::rawElement('p', [], Html::element(
			'a', [ 'href' => $this->getPageTitle( 'new' )->getLinkURL() ],
			wfMessage('soa2-new-app')->text()
		)));
		$out->addHTML(Html::openElement('ul'));
		foreach (SOA2Apps::partial( $owner_id ) as $app) {
			$link = Html::element('a', [
				'href' => $this->getPageTitle( (string)$app['client_id'] )->getLinkURL()
			], $app['app_name'] ?: wfMessage('soa2-unnamed-app')->text());
			$out->addHTML(Html::rawElement('li', [], $link));
		}
		$out->addHTML(Html::closeElement('ul'));
	}

	public function app( int $owner_id, int $client_id ) {
		$out = $this->getOutput();
		$app = SOA2Apps::application( $client_id, $owner_id );
		if (!$app) {
			$out->showErrorPage('soa2-no-app-title', 'soa2-no-app', [$client_id]);
			return;
		}
		if ($this->getRequest()->wasPosted()) {
			if ($this->getRequest()->getCheck('delete')) {
				$this->deleteApp( $owner_id, $client_id );
				return;
			} else {
				$app = $this->saveApp( $owner_id, $client_id );
			}
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
		{
			$secret_text = wfMessage('soa2-app-secret')->escaped();
			$secret = htmlspecialchars($app['client_secret']);
			$show = wfMessage('show')->escaped();
			$hide = wfMessage('hide')->escaped();
			$out->addHTML(<<<EOS
<th>$secret_text</th>
<td class="mw-collapsible mw-collapsed"
	data-expandtext="$show" data-collapsetext="$hide">
<code style="word-break: break-all" class="mw-collapsible-content">
$secret
</code>
</td>
EOS);
		}
		$out->addHTML(Html::closeElement('tr'));
		$out->addHTML(Html::openElement('tr'));
			$out->addHTML(Html::rawElement('th', [], Html::label(
				wfMessage('soa2-app-name')->escaped(),
				'soa2-app-name-input'
			)));
			$out->addHTML(Html::rawElement('td', [], Html::input(
				'app_name',
				$app['app_name'],
				'text',
				[ 'id' => 'soa2-app-name-input' ]
			)));
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
			$msg = wfMessage("soa2-flags-$value")->escaped();
			$msgValue = wfMessage(
				($app['flags'] & $value)
				? 'soa2-flags-yes'
				: 'soa2-flags-no'
			)->escaped();
			$out->addHTML("<tr><th title=\"$name\">$msg</th><td>$msgValue</td></tr>");
		}
		$out->addHTML(Html::closeElement('table'));
		$out->addHTML(Html::rawElement('p', [], Html::label(
			wfMessage('soa2-app-uris')->escaped(),
			'soa2-redirect-uris-input'
		)));
		$out->addHTML(Html::textarea(
			'redirect_uris',
			implode("\n", $app['redirect_uris']),
			[ 'id' => 'soa2-redirect-uris-input']
		));
		$confirm = json_encode(htmlspecialchars(wfMessage(
			'soa2-app-deletion-confirm', $app['app_name']
		)->text(), ENT_NOQUOTES));
		$out->addHTML(Html::rawElement('p', [], Html::input(
			'save',
			wfMessage('soa2-app-save')->text(),
			'submit'
		) . Html::input(
			'delete',
			wfMessage('soa2-app-delete', $app['app_name'])->text(),
			'submit',
			[ 'onclick' => "return confirm($confirm);" ]
		)));
		$out->addHTML(Html::closeElement('form'));
	}

	public function saveApp( int $owner_id, int $client_id ) {
		$request = $this->getRequest();
		if (!$request->getSession()->getToken()->match($request->getVal('token'))) {
			$out->addWikiMsg( 'sessionfailure' );
			return;
		}
		$args = [];
		if ($request->getCheck('reset_secret')) {
			$args['reset_secret'] = true;
		}
		$args['app_name'] = trim($request->getVal('app_name')) ?: null;
		$args['redirect_uris'] = array_map('trim', explode(
			"\n", $request->getText('redirect_uris')
		));
		return SOA2Apps::update( $client_id, $owner_id, $args);
	}

	public function deleteApp( int $owner_id, int $client_id ) {
		$request = $this->getRequest();
		if (!$request->getSession()->getToken()->match($request->getVal('token'))) {
			$out->addWikiMsg( 'sessionfailure' );
			return;
		}
		SOA2Apps::delete( $client_id, $owner_id );
		$out = $this->getOutput();
		$out->setPageTitle(wfMessage('soa2-app-deleted-title')->escaped());
		$out->addWikiMsg( 'soa2-app-deleted' );
		$out->addReturnTo($this->getPageTitle());
	}

	public function newApp( int $owner_id, ?string $error = null ) { // Step 1
		$out = $this->getOutput();
		$out->setPageTitle(wfMessage('soa2-app-new-title')->escaped());
		if (!$error && $this->getRequest()->wasPosted()) {
			$this->createApp( $owner_id );
			return;
		}
		$out->addReturnTo($this->getPageTitle());
		if ($error) {
			$out->addHTML(Html::rawElement('p', [], Html::element(
				'span', [ 'class' => 'error' ], $error
			)));
		}
		$out->addHTML(Html::openElement('form', [ 'method' => 'POST' ]));
		$out->addHTML(Html::hidden('token',
			$this->getRequest()->getSession()->getToken()->toString()));
		$out->addHTML(Html::openElement('table', [ 'class' => 'wikitable' ]));
		$out->addHTML(Html::openElement('tr'));
			$out->addHTML(Html::rawElement('th', [], Html::label(
				wfMessage('soa2-app-name')->escaped(),
				'soa2-app-name-input'
			)));
			$out->addHTML(Html::rawElement('td', [], Html::input(
				'app_name', '', 'text',
				[ 'id' => 'soa2-app-name-input' ]
			)));
		$out->addHTML(Html::closeElement('tr'));
		$out->addHTML(Html::closeElement('table'));
		$out->addHTML(Html::rawElement('p', [], Html::label(
			wfMessage('soa2-app-uris')->escaped(),
			'soa2-redirect-uris-input'
		)));
		$out->addHTML(Html::textarea(
			'redirect_uris', '',
			[ 'id' => 'soa2-redirect-uris-input']
		));
		$out->addHTML(Html::rawElement('p', [], Html::input(
			'create',
			wfMessage('soa2-app-create')->text(),
			'submit'
		)));
		$out->addHTML(Html::closeElement('form'));
	}

	public function createApp( int $owner_id ) {
		$request = $this->getRequest();
		if (!$request->getSession()->getToken()->match($request->getVal('token'))) {
			$this->newApp( $owner_id, wfMessage('sessionfailure')->text());
			return;
		}
		$app_name = trim($request->getVal('app_name')) ?: null;
		$redirect_uris = array_map('trim', explode(
			"\n", $request->getText('redirect_uris')
		));
		$app = SOA2Apps::create( $owner_id, $app_name, $redirect_uris );
		$out = $this->getOutput();
		$out->addWikiMsg( 'soa2-app-created' );
		$out->addReturnTo($this->getPageTitle( (string)$app['client_id'] )); // Step 5
	}
}
<?php
namespace MediaWiki\Extension\ScratchOAuth2\Special;

require_once dirname(__DIR__) . "/common/apps.php";

use SpecialPage;
use WebRequest;
use Html;
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
			$out->addHTML(Html::element(
				'p', [], wfMessage('soa2-notloggedin')->text()
			));
			$out->addReturnTo( $this->getTitleFor( 'ScratchOAuth2', 'login' ), [
				'returnto' => $this->getPageTitle( $par )->getFullURL()
			] );
			return;
		}
		$pieces = explode('/', $par);
		if (ctype_digit($pieces[0])) {
			$this->app( $owner_id, intval($pieces), $pieces );
		} else if ($par == 'new') {
			$this->newApp( $owner_id );
		} else {
			$this->apps( $owner_id );
		}
	}

	public function apps( int $owner_id ) {
		$out = $this->getOutput();
		$out->setPageTitle( wfMessage('soa2-apps-title')->escaped() );
		$out->addHTML(Html::element('p', [], wfMessage('soa2-apps')->text()));
		$out->addHTML(Html::rawElement('p', [], Html::element(
			'a', [ 'href' => $this->getPageTitle( 'new' )->getLinkURL() ],
			wfMessage('soa2-new-app')->text()
		)));
		$out->addHTML(Html::openElement('ul', []));
		foreach (SOA2Apps::partial( $owner_id ) as $app) {
			$link = Html::element('a', [
				'href' => $this->getPageTitle( (string)$app['client_id'] )->getLinkURL()
			], $app['app_name']);
			$out->addHTML(Html::rawElement('li', [], $link));
		}
		$out->addHTML(Html::closeElement('ul'));
	}
}
<?php
namespace MediaWiki\Extension\ScratchOAuth2\Special;

use SpecialPage;
use Html;

class SpecialSOA2Code extends SpecialPage {
	public function __construct() {
		parent::__construct( 'SOA2Code' );
	}

	public function execute( $par ) {
		$out = $this->getOutput();
		$out->setIndexPolicy( 'noindex' );
		$out->setPageTitle( wfMessage('soa2-code-title')->escaped() );
		$out->addWikiMsg('soa2-code');
		$out->addHTML(Html::element('pre', [], $this->getRequest()->getVal('code')));
	}
}
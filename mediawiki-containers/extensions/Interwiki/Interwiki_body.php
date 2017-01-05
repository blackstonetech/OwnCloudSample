<?php
/**
 * Implements Special:Interwiki
 * @ingroup SpecialPage
 */
class SpecialInterwiki extends SpecialPage {
	/**
	 * Constructor - sets up the new special page
	 */
	public function __construct() {
		parent::__construct( 'Interwiki' );
	}

	/**
	 * Different description will be shown on Special:SpecialPage depending on
	 * whether the user can modify the data.
	 * @return String
	 */
	function getDescription() {
		return $this->msg( $this->canModify() ?
			'interwiki' : 'interwiki-title-norights' )->plain();
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();

		$out = $this->getOutput();
		$request = $this->getRequest();

		$out->addModules( 'ext.interwiki.specialpage' );

		$action = $par ? $par : $request->getVal( 'action', $par );
		$return = $this->getPageTitle();

		switch( $action ) {
		case 'delete':
		case 'edit':
		case 'add':
			if ( $this->canModify( $out ) ) {
				$this->showForm( $action );
			}
			$out->returnToMain( false, $return );
			break;
		case 'submit':
			if ( !$this->canModify( $out ) ) {
				// Error msg added by canModify()
			} elseif ( !$request->wasPosted() ||
				!$this->getUser()->matchEditToken( $request->getVal( 'wpEditToken' ) )
			) {
				// Prevent cross-site request forgeries
				$out->addWikiMsg( 'sessionfailure' );
			} else {
				$this->doSubmit();
			}
			$out->returnToMain( false, $return );
			break;
		default:
			$this->showList();
			break;
		}
	}

	/**
	 * Returns boolean whether the user can modify the data.
	 * @param $out OutputPage|bool If $wgOut object given, it adds the respective error message.
	 * @throws PermissionsError
	 * @return bool
	 */
	public function canModify( $out = false ) {
		global $wgInterwikiCache;
		if ( !$this->getUser()->isAllowed( 'interwiki' ) ) {
			// Check permissions
			if ( $out ) {
				throw new PermissionsError( 'interwiki' );
			}

			return false;
		} elseif ( $wgInterwikiCache ) {
			// Editing the interwiki cache is not supported
			if ( $out ) {
				$out->addWikiMsg( 'interwiki-cached' );
			}

			return false;
		} elseif ( wfReadOnly() ) {
			// Is the database in read-only mode?
			if ( $out ) {
				$out->readOnlyPage();
			}
			return false;
		}
		return true;
	}

	/**
	 * @param $action string
	 */
	function showForm( $action ) {
		$request = $this->getRequest();

		$prefix = $request->getVal( 'prefix' );
		$wpPrefix = '';
		$label = array( 'class' => 'mw-label' );
		$input = array( 'class' => 'mw-input' );

		if ( $action === 'delete' ) {
			$topmessage = $this->msg( 'interwiki_delquestion', $prefix )->text();
			$intromessage = $this->msg( 'interwiki_deleting', $prefix )->escaped();
			$wpPrefix = Html::hidden( 'wpInterwikiPrefix', $prefix );
			$button = 'delete';
			$formContent = '';
		} elseif ( $action === 'edit' ) {
			$dbr = wfGetDB( DB_SLAVE );
			$row = $dbr->selectRow( 'interwiki', '*', array( 'iw_prefix' => $prefix ), __METHOD__ );

			if ( !$row ) {
				$this->error( 'interwiki_editerror', $prefix );
				return;
			}

			$prefix = $prefixElement = $row->iw_prefix;
			$defaulturl = $row->iw_url;
			$trans = $row->iw_trans;
			$local = $row->iw_local;
			$wpPrefix = Html::hidden( 'wpInterwikiPrefix', $row->iw_prefix );
			$topmessage = $this->msg( 'interwiki_edittext' )->text();
			$intromessage = $this->msg( 'interwiki_editintro' )->escaped();
			$button = 'edit';
		} elseif ( $action === 'add' ) {
			$prefix = $request->getVal( 'wpInterwikiPrefix', $request->getVal( 'prefix' ) );
			$prefixElement = Xml::input( 'wpInterwikiPrefix', 20, $prefix,
				array( 'tabindex' => 1, 'id' => 'mw-interwiki-prefix', 'maxlength' => 20 ) );
			$local = $request->getCheck( 'wpInterwikiLocal' );
			$trans = $request->getCheck( 'wpInterwikiTrans' );
			$defaulturl = $request->getVal( 'wpInterwikiURL', $this->msg( 'interwiki-defaulturl' )->text() );
			$topmessage = $this->msg( 'interwiki_addtext' )->text();
			$intromessage = $this->msg( 'interwiki_addintro' )->escaped();
			$button = 'interwiki_addbutton';
		}

		if ( $action === 'add' || $action === 'edit' ) {
			$formContent = Html::rawElement( 'tr', null,
				Html::element( 'td', $label, $this->msg( 'interwiki-prefix-label' )->text() ) .
				Html::rawElement( 'td', null, '<tt>' . $prefixElement . '</tt>' )
			) . Html::rawElement(
				'tr',
				null,
				Html::rawElement(
					'td',
					$label,
					Xml::label( $this->msg( 'interwiki-local-label' )->text(), 'mw-interwiki-local' )
				) .
				Html::rawElement(
					'td',
					$input,
					Xml::check( 'wpInterwikiLocal', $local, array( 'id' => 'mw-interwiki-local' ) )
				)
			) . Html::rawElement( 'tr', null,
				Html::rawElement(
					'td',
					$label,
					Xml::label( $this->msg( 'interwiki-trans-label' )->text(), 'mw-interwiki-trans' )
				) .
				Html::rawElement(
					'td',
					$input,  Xml::check( 'wpInterwikiTrans', $trans, array( 'id' => 'mw-interwiki-trans' ) ) )
			) . Html::rawElement( 'tr', null,
				Html::rawElement(
					'td',
					$label,
					Xml::label( $this->msg( 'interwiki-url-label' )->text(), 'mw-interwiki-url' )
				) .
				Html::rawElement( 'td', $input, Xml::input( 'wpInterwikiURL', 60, $defaulturl,
					array( 'tabindex' => 1, 'maxlength' => 200, 'id' => 'mw-interwiki-url' ) ) )
			);
		}

		$form = Xml::fieldset( $topmessage, Html::rawElement(
			'form',
			array(
				'id' => "mw-interwiki-{$action}form",
				'method' => 'post',
				'action' => $this->getPageTitle()->getLocalUrl( array(
					'action' => 'submit',
					'prefix' => $prefix
				) )
			),
			Html::rawElement( 'p', null, $intromessage ) .
			Html::rawElement( 'table', array( 'id' => "mw-interwiki-{$action}" ),
				$formContent . Html::rawElement( 'tr', null,
					Html::rawElement( 'td', $label, Xml::label( $this->msg( 'interwiki_reasonfield' )->text(),
						"mw-interwiki-{$action}reason" ) ) .
					Html::rawElement( 'td', $input, Xml::input( 'wpInterwikiReason', 60, '',
						array( 'tabindex' => 1, 'id' => "mw-interwiki-{$action}reason", 'maxlength' => 200 ) ) )
				) .	Html::rawElement( 'tr', null,
					Html::rawElement( 'td', null, '' ) .
					Html::rawElement( 'td', array( 'class' => 'mw-submit' ),
						Xml::submitButton( $this->msg( $button )->text(), array( 'id' => 'mw-interwiki-submit' ) ) )
				) . $wpPrefix .
				Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() ) .
				Html::hidden( 'wpInterwikiAction', $action )
			)
		) );
		$this->getOutput()->addHTML( $form );
		return;
	}

	function doSubmit() {
		global $wgMemc, $wgContLang;

		$request = $this->getRequest();
		$prefix = $request->getVal( 'wpInterwikiPrefix' );
		$do = $request->getVal( 'wpInterwikiAction' );
		// Show an error if the prefix is invalid (only when adding one).
		// Invalid characters for a title should also be invalid for a prefix.
		// Whitespace, ':', '&' and '=' are invalid, too.
		// (Bug 30599).
		global $wgLegalTitleChars;
		$validPrefixChars = preg_replace( '/[ :&=]/', '', $wgLegalTitleChars );
		if ( preg_match( "/\s|[^$validPrefixChars]/", $prefix ) && $do === 'add' ) {
			$this->error( 'interwiki-badprefix', htmlspecialchars( $prefix ) );
			$this->showForm( $do );
			return;
		}
		$reason = $request->getText( 'wpInterwikiReason' );
		$selfTitle = $this->getPageTitle();
		$dbw = wfGetDB( DB_MASTER );
		switch( $do ) {
		case 'delete':
			$dbw->delete( 'interwiki', array( 'iw_prefix' => $prefix ), __METHOD__ );

			if ( $dbw->affectedRows() === 0 ) {
				$this->error( 'interwiki_delfailed', $prefix );
				$this->showForm( $do );
			} else {
				$this->getOutput()->addWikiMsg( 'interwiki_deleted', $prefix );
				$log = new LogPage( 'interwiki' );
				$log->addEntry( 'iw_delete', $selfTitle, $reason, array( $prefix ) );
				$wgMemc->delete( wfMemcKey( 'interwiki', $prefix ) );
			}
			break;
		case 'add':
			$prefix = $wgContLang->lc( $prefix );
			// N.B.: no break!
		case 'edit':
			$theurl = $request->getVal( 'wpInterwikiURL' );
			$local = $request->getCheck( 'wpInterwikiLocal' ) ? 1 : 0;
			$trans = $request->getCheck( 'wpInterwikiTrans' ) ? 1 : 0;
			$data = array(
				'iw_prefix' => $prefix,
				'iw_url' => $theurl,
				'iw_local' => $local,
				'iw_trans' => $trans
			);

			if ( $prefix === '' || $theurl === '' ) {
				$this->error( 'interwiki-submit-empty' );
				$this->showForm( $do );
				return;
			}

			// Simple URL validation: check that the protocol is one of
			// the supported protocols for this wiki.
			// (bug 30600)
			if ( !wfParseUrl( $theurl ) ) {
				$this->error( 'interwiki-submit-invalidurl' );
				$this->showForm( $do );
				return;
			}

			if ( $do === 'add' ) {
				$dbw->insert( 'interwiki', $data, __METHOD__, 'IGNORE' );
			} else { // $do === 'edit'
				$dbw->update( 'interwiki', $data, array( 'iw_prefix' => $prefix ), __METHOD__, 'IGNORE' );
			}

			// used here: interwiki_addfailed, interwiki_added, interwiki_edited
			if ( $dbw->affectedRows() === 0 ) {
				$this->error( "interwiki_{$do}failed", $prefix );
				$this->showForm( $do );
			} else {
				$this->getOutput()->addWikiMsg( "interwiki_{$do}ed", $prefix );
				$log = new LogPage( 'interwiki' );
				$log->addEntry( 'iw_' . $do, $selfTitle, $reason, array( $prefix, $theurl, $trans, $local ) );
				$wgMemc->delete( wfMemcKey( 'interwiki', $prefix ) );
			}
			break;
		}
	}

	function showList() {
		global $wgInterwikiCentralDB;
		$canModify = $this->canModify();

		// Build lists
		if ( !method_exists( 'Interwiki', 'getAllPrefixes' ) ) {
			// version 2.0 is not backwards compatible (but will still display a nice error)
			$this->error( 'interwiki_error' );
			return;
		}
		$iwPrefixes = Interwiki::getAllPrefixes( null );
		$iwGlobalPrefixes = array();
		if ( $wgInterwikiCentralDB !== null && $wgInterwikiCentralDB !== wfWikiId() ) {
			// Fetch list from global table
			$dbrCentralDB = wfGetDB( DB_SLAVE, array(), $wgInterwikiCentralDB );
			$res = $dbrCentralDB->select( 'interwiki', '*', false, __METHOD__ );
			$retval = array();
			foreach ( $res as $row ) {
				$row = (array)$row;
				if ( !Language::fetchLanguageName( $row['iw_prefix'] ) ) {
					$retval[] = $row;
				}
			}
			$iwGlobalPrefixes = $retval;
		}

		// Split out language links
		$iwLocalPrefixes = array();
		$iwLanguagePrefixes = array();
		foreach ( $iwPrefixes as $iwPrefix ) {
			if ( Language::fetchLanguageName( $iwPrefix['iw_prefix'] ) ) {
				$iwLanguagePrefixes[] = $iwPrefix;
			} else {
				$iwLocalPrefixes[] = $iwPrefix;
			}
		}

		// Page intro content
		$this->getOutput()->addWikiMsg( 'interwiki_intro' );
		$logLink = Linker::link(
			SpecialPage::getTitleFor( 'log', 'interwiki' ),
			$this->msg( 'interwiki-logtext' )->escaped()
		);
		$this->getOutput()->addHTML( '<p class="mw-interwiki-log">' . $logLink . '</p>' );

		// Add 'add' link
		if ( $canModify ) {
			if ( count( $iwGlobalPrefixes ) !== 0 ) {
				$addtext = $this->msg( 'interwiki-addtext-local' )->escaped();
			} else {
				$addtext = $this->msg( 'interwiki_addtext' )->escaped();
			}
			$addlink = Linker::linkKnown( $this->getPageTitle( 'add' ), $addtext );
			$this->getOutput()->addHTML( '<p class="mw-interwiki-addlink">' . $addlink . '</p>' );
		}

		$this->getOutput()->addWikiMsg( 'interwiki-legend' );

		if ( !is_array( $iwPrefixes ) || count( $iwPrefixes ) === 0 ) {
			if (  !is_array( $iwGlobalPrefixes ) || count( $iwGlobalPrefixes ) === 0 ) {
				// If the interwiki table(s) are empty, display an error message
				$this->error( 'interwiki_error' );
				return;
			}
		}

		// Add the global table
		if ( count( $iwGlobalPrefixes ) !== 0 ) {
			$this->getOutput()->addHTML(
				'<h2 id="interwikitable-global">' .
				$this->msg( 'interwiki-global-links' )->parse() .
				'</h2>'
			);
			$this->getOutput()->addWikiMsg( 'interwiki-global-description' );

			// $canModify is false here because this is just a display of remote data
			$this->makeTable( false, $iwGlobalPrefixes );
		}

		// Add the local table
		if ( count( $iwLocalPrefixes ) !== 0 ) {
			if ( count( $iwGlobalPrefixes ) !== 0 ) {
				$this->getOutput()->addHTML(
					'<h2 id="interwikitable-local">' .
					$this->msg( 'interwiki-local-links' )->parse() .
					'</h2>'
				);
				$this->getOutput()->addWikiMsg( 'interwiki-local-description' );
			} else {
				$this->getOutput()->addHTML(
					'<h2 id="interwikitable-local">' .
					$this->msg( 'interwiki-links' )->parse() .
					'</h2>'
				);
				$this->getOutput()->addWikiMsg( 'interwiki-description' );
			}
			$this->makeTable( $canModify, $iwLocalPrefixes );
		}

		// Add the language table
		if ( count( $iwLanguagePrefixes ) !== 0 ) {
			$this->getOutput()->addHTML(
				'<h2 id="interwikitable-language">' .
				$this->msg( 'interwiki-language-links' )->parse() .
				'</h2>'
			);
			$this->getOutput()->addWikiMsg( 'interwiki-language-description' );

			$this->makeTable( $canModify, $iwLanguagePrefixes );
		}
	}

	function makeTable( $canModify, $iwPrefixes ) {
		// Output the existing Interwiki prefixes table header
		$out = '';
		$out .=	Html::openElement(
			'table',
			array( 'class' => 'mw-interwikitable wikitable sortable body' )
		) . "\n";
		$out .= Html::openElement( 'tr', array( 'class' => 'interwikitable-header' ) ) .
			Html::element( 'th', null, $this->msg( 'interwiki_prefix' )->text() ) .
			Html::element( 'th', null, $this->msg( 'interwiki_url' )->text() ) .
			Html::element( 'th', null, $this->msg( 'interwiki_local' )->text() ) .
			Html::element( 'th', null, $this->msg( 'interwiki_trans' )->text() ) .
			( $canModify ?
				Html::element(
					'th',
					array( 'class' => 'unsortable' ),
					$this->msg( 'interwiki_edit' )->text()
				) :
				''
			);
		$out .= Html::closeElement( 'tr' ) . "\n";

		$selfTitle = $this->getPageTitle();

		// Output the existing Interwiki prefixes table rows
		foreach ( $iwPrefixes as $iwPrefix ) {
			$out .= Html::openElement( 'tr', array( 'class' => 'mw-interwikitable-row' ) );
			$out .= Html::element( 'td', array( 'class' => 'mw-interwikitable-prefix' ),
				$iwPrefix['iw_prefix'] );
			$out .= Html::element(
				'td',
				array( 'class' => 'mw-interwikitable-url' ),
				$iwPrefix['iw_url']
			);
			$attribs = array( 'class' => 'mw-interwikitable-local' );
			// Green background for cells with "yes".
			if( $iwPrefix['iw_local'] ) {
				$attribs['class'] .= ' mw-interwikitable-local-yes';
			}
			// The messages interwiki_0 and interwiki_1 are used here.
			$contents = isset( $iwPrefix['iw_local'] ) ?
				$this->msg( 'interwiki_' . $iwPrefix['iw_local'] )->text() :
				'-';
			$out .= Html::element( 'td', $attribs, $contents );
			$attribs = array( 'class' => 'mw-interwikitable-trans' );
			// Green background for cells with "yes".
			if( $iwPrefix['iw_trans'] ) {
				$attribs['class'] .= ' mw-interwikitable-trans-yes';
			}
			// The messages interwiki_0 and interwiki_1 are used here.
			$contents = isset( $iwPrefix['iw_trans'] ) ?
				$this->msg( 'interwiki_' . $iwPrefix['iw_trans'] )->text() :
				'-';
			$out .= Html::element( 'td', $attribs, $contents );

			// Additional column when the interwiki table can be modified.
			if ( $canModify ) {
				$out .= Html::rawElement( 'td', array( 'class' => 'mw-interwikitable-modify' ),
					Linker::linkKnown( $selfTitle, $this->msg( 'edit' )->escaped(), array(),
						array( 'action' => 'edit', 'prefix' => $iwPrefix['iw_prefix'] ) ) .
					$this->msg( 'comma-separator' ) .
					Linker::linkKnown( $selfTitle, $this->msg( 'delete' )->escaped(), array(),
						array( 'action' => 'delete', 'prefix' => $iwPrefix['iw_prefix'] ) )
				);
			}
			$out .= Html::closeElement( 'tr' ) . "\n";
		}
		$out .= Html::closeElement( 'table' );

		$this->getOutput()->addHTML( $out );
	}

	function error() {
		$args = func_get_args();
		$this->getOutput()->wrapWikiMsg( "<p class='error'>$1</p>", $args );
	}
}

/**
 * Needed to pass the URL as a raw parameter, because it contains $1
 */
class InterwikiLogFormatter extends LogFormatter {
	/**
	 * @return array
	 */
	protected function getMessageParameters() {
		$params = parent::getMessageParameters();
		if ( isset( $params[4] ) ) {
			$params[4] = Message::rawParam( htmlspecialchars( $params[4] ) );
		}
		return $params;
	}
}

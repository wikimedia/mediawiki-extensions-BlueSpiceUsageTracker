<?php

namespace BlueSpice\UsageTracker\Hook;

use BlueSpice\UsageTracker\Process\UpdateData;
use MediaWiki\Hook\SetupAfterCacheHook;
use MediaWiki\MediaWikiServices;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use MWStake\MediaWiki\Component\WikiCron\WikiCronManager;

class RegisterUpdateCron implements SetupAfterCacheHook {

	/**
	 * @return void
	 */
	public function onSetupAfterCache() {
		if ( defined( 'MW_PHPUNIT_TEST' ) || defined( 'MW_QUIBBLE_CI' ) ) {
			return;
		}
		/** @var WikiCronManager $cronManager */
		$cronManager = MediaWikiServices::getInstance()->getService( 'MWStake.WikiCronManager' );
		$cronManager->registerCron( 'bs-usagetracker-update', '0 1 * * *', new ManagedProcess( [
			'export' => [
				'class' => UpdateData::class,
				'services' => [ 'BSExtensionFactory' ]
			]
		] ) );
	}
}

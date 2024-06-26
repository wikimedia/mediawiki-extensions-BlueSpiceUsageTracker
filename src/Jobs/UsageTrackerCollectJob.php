<?php
/**
 * This job is created when a usage tracker requests the usage data to be
 * collected. This may be very resource intense, so the collection of data
 * itself is deferred to a job.
 */
namespace BS\UsageTracker\Jobs;

use Job;
use MediaWiki\MediaWikiServices;

class UsageTrackerCollectJob extends Job {

	/**
	 * Configuration of the job
	 * @var array
	 */
	protected $config = [];

	/**
	 * @param array $params definition array for specific collector
	 */
	public function __construct( $params ) {
		parent::__construct( 'usageTrackerCollectJob', $params );
		$this->config = $params;
	}

	/**
	 * Run the job of collecting usage data for a given collector
	 * @return true
	 */
	public function run() {
		$em = MediaWikiServices::getInstance()->getService( 'BSExtensionFactory' );
		$em->getExtension( 'BlueSpiceUsageTracker' )->getUsageData( $this->config );
		return true;
	}

}

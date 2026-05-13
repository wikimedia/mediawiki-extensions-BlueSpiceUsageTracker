<?php
namespace BS\UsageTracker\Collectors;

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\ILoadBalancer;

abstract class Base {

	protected $identifier = '';
	protected $description = 'bs-usagetracker-base-collector-desc';

	/**
	 * Initial configuration. Needed to register as job
	 * @var array
	 */
	protected $config = [];

	/** @var MediaWikiServices */
	protected $services;

	/** @var ILoadBalancer */
	protected $loadBalancer;

	/**
	 *
	 * @param array $config
	 */
	public function __construct( $config = [] ) {
		if ( isset( $config['config'] ) && is_array( $config['config'] ) ) {
			if ( isset( $config['config']['identifier'] ) ) {
				$this->identifier = $config['config']['identifier'];
			}
			if ( isset( $config['config']['internalDesc'] ) ) {
				$this->description = $config['config']['internalDesc'];
			}
		}
		$this->config = $config;
		$this->services = MediaWikiServices::getInstance();
		$this->loadBalancer = $this->services->getDBLoadBalancer();
	}

	/**
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 *
	 * @return string
	 */
	public function getIdentifier() {
		return $this->identifier;
	}

	/**
	 *
	 * @return \BS\UsageTracker\CollectorResult
	 */
	abstract public function getUsageData();
}

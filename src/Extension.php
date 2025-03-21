<?php

/**
 * BlueSpice MediaWiki
 * Extension: UsageTracker
 * Description:
 * Authors: Markus Glaser
 *
 * Copyright (C) 2016 Hallo Welt! GmbH, All rights reserved.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * For further information visit https://bluespice.com
 * @author     Your Name <glaser@hallowelt.com>
 * @package    BlueSpice_Extensions
 * @subpackage Usage Tracker
 * @copyright  Copyright (C) 2016 Hallo Welt! GmbH, All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GPL-3.0-only
 * @filesource
 */
namespace BS\UsageTracker;

use BlueSpice\Extension as BaseExtension;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use Wikimedia\Rdbms\ILoadBalancer;

class Extension extends BaseExtension {

	/**
	 * Contains the configuration for collectors
	 * @var array Config array
	 */
	public $aCollectorsConfig = [];

	/**
	 * Contains all potential collectors
	 * @var array Object array of BS\\UsageTracker\\Collectors\\Base
	 */
	protected $aCollectors = [];

	/** @var ILoadBalancer */
	protected $loadBalancer;

	/**
	 * @inheritDoc
	 */
	public function __construct( array $definition, IContextSource $context, Config $config ) {
		parent::__construct( $definition, $context, $config );
		$this->loadBalancer = $this->services->getDBLoadBalancer();
	}

	/**
	 * Collects usage data from one or several collectors. If $aConfig is not set
	 * it fetches all collectors and adds them to job queue. If $aConfig is set,
	 * it actually collects from the collectors set in config (typically invoked
	 * from job queue and only one collector)
	 * @param array|null $aConfig
	 * @return BS\UsageTracker\CollectorResult[]
	 */
	public function getUsageData( $aConfig = null ) {
		$this->initializeCollectors( $aConfig );

		// If there is no specific collector, register all known collectors and
		// add them to job queue for deferred collecting
		// if ( $aConfig === null ) {
		// 	foreach ( $this->aCollectors as $oCollector ) {
		// 		$oCollector->registerJob();
		// 	}
		// 	return $this->aCollectors;
		// }

		foreach ( $this->aCollectors as $oCollector ) {
			$aData[] = $oCollector->getUsageData( $aConfig );
		}

		// Store collected data in DB for future access
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		foreach ( $aData as $oData ) {
			if ( is_array( $oData ) ) {
				foreach ( $oData as $cData ) {
					$dbw->delete(
						'bs_usagetracker',
						[ 'ut_identifier' => $cData['identifier'] ],
						__METHOD__
					);
					// Update the count
					$dbw->insert(
						'bs_usagetracker',
						[
							'ut_identifier' => $cData['identifier'],
							'ut_count' => $cData['count'],
							'ut_type' => $cData['type'],
							'ut_timestamp' => wfTimestampNow()
						],
						__METHOD__
					);
				}

			} else {
				// Each usage number is only stored once. So delete any old values first.
				$dbw->delete(
					'bs_usagetracker',
					[ 'ut_identifier' => $oData->identifier ],
					__METHOD__
				);
				// Update the count
				$dbw->insert(
					'bs_usagetracker',
					[
						'ut_identifier' => $oData->identifier,
						'ut_count' => $oData->count,
						'ut_type' => $oData->type,
						'ut_timestamp' => wfTimestampNow()
					],
					__METHOD__
				);
			}

		}

		return $aData;
	}

	/**
	 * Load existing data from the database instead of collecting it on the fly,
	 * as collecting data might be very ressource intense.
	 * @param array|null $aConfig
	 * @return BS\UsageTracker\CollectorResult[]
	 */
	public function getUsageDataFromDB( $aConfig = null ) {
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );
		$res = $dbr->select(
			'bs_usagetracker',
			[
				'ut_identifier',
				'ut_count',
				'ut_type',
				'ut_timestamp'
			],
			[],
			__METHOD__,
			[ 'ORDER BY' => 'ut_identifier' ]
		);
		$aData = [];
		foreach ( $res as $oRow ) {
			$aData[] = \BS\UsageTracker\CollectorResult::newFromDBRow( $oRow );
		}
		return $aData;
	}

	/**
	 * Gets all available collector if $aConfig is null, otherwise uses collectors
	 * as given in config
	 * @param array|null $aConfig
	 * @return bool
	 */
	protected function initializeCollectors( $aConfig = null ) {
		if ( $aConfig === null ) {
			// Get all the collectors definitions
			$this->services->getHookContainer()->run(
				'BSUsageTrackerRegisterCollectors',
				[
					&$this->aCollectorsConfig
				]
			);
		} else {
			$this->aCollectorsConfig = [];
			$this->aCollectorsConfig[] = $aConfig;
		}

		// Instantiate all collectors from definitions
		// Check if class exists and inherits from Base as configs may
		// contain typos and deprecated declarations.
		foreach ( $this->aCollectorsConfig as $aCollectorConfig ) {
			if ( strpos( $aCollectorConfig['class'], "\\" ) === false ) {
				$classname = "BS\\UsageTracker\\Collectors\\" . $aCollectorConfig['class'];
			} else {
				$classname = $aCollectorConfig['class'];
			}
			if ( class_exists( $classname ) ) {
				$oCollector = new $classname( $aCollectorConfig['config'] );
				if ( $oCollector instanceof \BS\UsageTracker\Collectors\Base ) {
					$this->aCollectors[] = new $classname( $aCollectorConfig );
				} else {
					wfDebugLog( "BSUsageTracker", "Class $classname must be inherited from Base" );
				}
			} else {
				wfDebugLog( "BSUsageTracker", "Class $classname must does not exist" );
			}
		}

		return true;
	}

}

<?php

/**
 * Show the cached statistics.
 * Give out the same output as [[Special:Statistics]]
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
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
 * @file
 * @ingroup Maintenance
 * @author Antoine Musso <hashar at free dot fr>
 * Based on initSiteStats.php by:
 * @author Brion Vibber
 * @author Rob Church <robchur@gmail.com>
 *
 * @license GPL-2.0-or-later
 */

use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;

$IP = realpath( dirname( dirname( __DIR__ ) ) );

require_once $IP . '/BlueSpiceFoundation/maintenance/BSMaintenance.php';

/**
 * Maintenance script to show the cached statistics.
 *
 * @ingroup Maintenance
 */
class ShowInstanceStats extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Show the cached statistics' );
	}

	public function execute() {
		$fields = [
			'ss_total_edits' => 'Total edits',
			'ss_good_articles' => 'Number of articles',
			'ss_total_pages' => 'Total pages',
			'ss_users' => 'Number of users',
			'ss_active_users' => 'Active users',
			'ss_images' => 'Number of images',
		];

		// Get cached stats from a replica DB
		$dbr = $this->getReplicaDB();
		$stats = $dbr->selectRow( 'site_stats', '*', '', __METHOD__ );

		// Get maximum size for each column
		$maxlengthvalue = $maxlengthdesc = 0;
		foreach ( $fields as $field => $desc ) {
			$maxlengthvalue = max( $maxlengthvalue, strlen( $stats->$field ) );
			$maxlengthdesc = max( $maxlengthdesc, strlen( $desc ) );
		}
		$sitestats = [];
		foreach ( $fields as $field => $desc ) {
			array_push( $sitestats, [ $desc => $stats->$field ]
			);
		}
		$numberOfEnabledUsers = $this->countEnabledUsers();
		array_push( $sitestats, [ "Enabled users" => $numberOfEnabledUsers ] );

		$em = MediaWikiServices::getInstance()->getService( 'BSExtensionFactory' );
		$usagetrackerdata = $em->getExtension( 'BlueSpiceUsageTracker' )->getUsageDataFromDB();
		$bsextensioninfo = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'bsg' )
			->get( 'BlueSpiceExtInfo' );
		$usagetracker = [];

		foreach ( $usagetrackerdata as $data ) {

			if ( is_array( $data ) ) {
				foreach ( $data as $dataval ) {
					array_push( $usagetracker, [ $dataval['identifier'] => $dataval['count'] ] );
				}
			} else {
				array_push( $usagetracker, [ $data->identifier => $data->count ] );
			}
		}
		$instanceStats = [
			"instance" => sha1( WikiMap::getCurrentWikiId() ),
			"timestamp" => date( DATE_ISO8601 ),
			"bluespice-version" => $bsextensioninfo['version'],
			"bluespice-edition" => $bsextensioninfo['package'],
			"mediawiki-version" => MW_VERSION,
			"sitestats" => call_user_func_array( 'array_merge', $sitestats ),
			"usagetracker" => call_user_func_array( 'array_merge', $usagetracker )
		];
		$this->output( json_encode( $instanceStats, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Get the number of enabled (not blocked) users.
	 *
	 * @return int
	 */
	private function countEnabledUsers(): int {
		$total = $this->countTotalUsers();
		$blocked = $this->countBlockedUsers();

		return $total - $blocked;
	}

	/**
	 * Count all users.
	 *
	 * @return int
	 */
	private function countTotalUsers(): int {
		$dbr = $this->getReplicaDB();
		$count = $dbr->newSelectQueryBuilder()
			->select( 'user_id' )
			->from( 'user' )
			->caller( __METHOD__ )
			->fetchRowCount();

		return $count;
	}

	/**
	 * Count all currently sitewide blocked users.
	 *
	 * @return int
	 */
	private function countBlockedUsers(): int {
		$dbr = $this->getReplicaDB();
		$count = $dbr->newSelectQueryBuilder()
			->table( 'block_target' )
			->join( 'block', null, 'bt_id = bl_target' )
			->field( 'bt_user' )
			->where( [
				'bt_user IS NOT NULL',
				'bl_sitewide' => 1,
				$dbr->makeList(
					[
						$dbr->expr( 'bl_expiry', '=', 'infinity' ),
						$dbr->expr( 'bl_expiry', '>=', $dbr->timestamp() )
					],
					LIST_OR
				)
			] )
			->caller( __METHOD__ )
			->fetchRowCount();

		return $count;
	}
}

$maintClass = ShowInstanceStats::class;
require_once RUN_MAINTENANCE_IF_MAIN;

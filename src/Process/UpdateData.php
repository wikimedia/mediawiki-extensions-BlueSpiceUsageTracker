<?php

namespace BlueSpice\UsageTracker\Process;

use BlueSpice\ExtensionFactory;
use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;

class UpdateData implements IProcessStep {

	/**
	 * @param ExtensionFactory $extensionFactory
	 */
	public function __construct(
		private readonly ExtensionFactory $extensionFactory
	) {
	}

	/**
	 * @param array $data
	 * @return array
	 */
	public function execute( $data = [] ): array {
		return $this->extensionFactory->getExtension( 'BlueSpiceUsageTracker' )?->getUsageData();
	}
}

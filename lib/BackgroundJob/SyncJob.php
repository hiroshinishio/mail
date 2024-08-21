<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\BackgroundJob;

use OCA\Mail\Service\SyncJobService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use function max;

class SyncJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		IConfig $config,
		private LoggerInterface $logger,
		private IJobList $jobList,
		private SyncJobService $syncJobService,
	) {
		parent::__construct($time);

		$this->setInterval(
			max(
				5 * 60,
				$config->getSystemValueInt('app.mail.background-sync-interval', 3600)
			),
		);
		$this->setTimeSensitivity(self::TIME_SENSITIVE);
	}

	/**
	 * @return void
	 */
	protected function run($argument) {
		$accountId = (int)$argument['accountId'];
		if (!$this->syncJobService->doRegularBackgroundSync($accountId)) {
			$this->logger->debug('Could not find account <' . $accountId . '> removing from jobs');
			$this->jobList->remove(self::class, $argument);
		}
	}
}

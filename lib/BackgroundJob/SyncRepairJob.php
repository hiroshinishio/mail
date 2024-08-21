<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\BackgroundJob;

use OCA\Mail\Service\SyncJobService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class SyncRepairJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private SyncJobService $syncJobService,
		private IJobList $jobList,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);

		$this->setInterval(3600 * 24 * 7);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		$accountId = (int)$argument['accountId'];
		if (!$this->syncJobService->doRepairBackgroundSync($accountId)) {
			$this->logger->debug('Could not find account <' . $accountId . '> removing from jobs');
			$this->jobList->remove(self::class, $argument);
		}
	}
}

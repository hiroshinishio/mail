<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Service;

use Horde_Imap_Client_Exception;
use OCA\Mail\Exception\IncompleteSyncException;
use OCA\Mail\Exception\ServiceException;
use OCA\Mail\IMAP\MailboxSync;
use OCA\Mail\Service\Sync\ImapToDbSynchronizer;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Throwable;

class SyncJobService {
	public function __construct(
		private AccountService $accountService,
		private LoggerInterface $logger,
		private IUserManager $userManager,
		private ImapToDbSynchronizer $syncService,
		private MailboxSync $mailboxSync,
	) {
	}

	/**
	 * Do a regular, incremental background sync.
	 *
	 * @return bool False if the job should be removed from the list
	 */
	public function doRegularBackgroundSync(int $accountId): bool {
		return $this->doSync($accountId, true, true);
	}

	/**
	 * Do a full sync to repair eventual inconsistencies.
	 * Should be used sparingly as it casus a lot of IMAP traffic.
	 *
	 * @return bool False if the job should be removed from the list
	 */
	public function doRepairBackgroundSync(int $accountId): bool {
		return $this->doSync($accountId, false, false);
	}

	/**
	 * @return bool False if the job should be removed from the list
	 */
	private function doSync(int $accountId, bool $syncMailboxes, bool $useClientCache): bool {
		try {
			$account = $this->accountService->findById($accountId);
		} catch (DoesNotExistException $e) {
			return false;
		}

		if(!$account->getMailAccount()->canAuthenticateImap()) {
			$this->logger->debug('No authentication on IMAP possible, skipping background sync job');
			return true;
		}

		$user = $this->userManager->get($account->getUserId());
		if ($user === null || !$user->isEnabled()) {
			$this->logger->debug(sprintf(
				'Account %d of user %s could not be found or was disabled, skipping background sync',
				$account->getId(),
				$account->getUserId()
			));
			return true;
		}

		try {
			if ($syncMailboxes) {
				$this->mailboxSync->sync($account, $this->logger, true);
			}
			$this->syncService->syncAccount($account, $this->logger, !$useClientCache, $useClientCache);
		} catch (IncompleteSyncException $e) {
			$this->logger->warning($e->getMessage(), [
				'exception' => $e,
			]);
		} catch (Throwable $e) {
			if ($e instanceof ServiceException
				&& $e->getPrevious() instanceof Horde_Imap_Client_Exception
				&& $e->getPrevious()->getCode() === Horde_Imap_Client_Exception::LOGIN_AUTHENTICATIONFAILED) {
				$this->logger->info('Cron mail sync authentication failed for account {accountId}', [
					'accountId' => $accountId,
					'exception' => $e,
				]);
			} else {
				$this->logger->error('Cron mail sync failed for account {accountId}', [
					'accountId' => $accountId,
					'exception' => $e,
				]);
			}
		}

		return true;
	}
}

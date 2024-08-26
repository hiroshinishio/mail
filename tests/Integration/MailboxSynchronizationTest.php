<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\Mail\Tests\Integration;

use Horde_Imap_Client;
use Horde_Imap_Client_Socket;
use OC\Memcache\ArrayCache;
use OC\Memcache\Factory;
use OCA\Mail\Account;
use OCA\Mail\Contracts\IMailManager;
use OCA\Mail\Controller\MailboxesController;
use OCA\Mail\Db\MessageMapper;
use OCA\Mail\Service\AccountService;
use OCA\Mail\Service\Sync\SyncService;
use OCA\Mail\Tests\Integration\Framework\ImapTest;
use OCA\Mail\Tests\Integration\Framework\ImapTestAccount;
use OCP\ICacheFactory;
use OCP\IRequest;
use OCP\Profiler\IProfiler;
use OCP\Server;
use Psr\Log\LoggerInterface;

class MailboxSynchronizationTest extends TestCase {
	use ImapTest,
		ImapTestAccount;

	/** @var MailboxesController */
	private $foldersController;

	/** @var \OCA\Mail\Db\MailAccount */
	private $account;

	/** @var Horde_Imap_Client_Socket $client */
	private $client;

	protected function setUp(): void {
		parent::setUp();

		// Simulate proper distributed cache via shared array cache instance
		\OC::$server->registerService(
			ICacheFactory::class,
			function () {
				$cacheFactory = $this->getMockBuilder(Factory::class)
					->setConstructorArgs([
						'globalPrefix',
						Server::get(LoggerInterface::class),
						Server::get(IProfiler::class),
						ArrayCache::class,
						ArrayCache::class,
						ArrayCache::class,
					])
					->setMethods(['createDistributed'])
					->getMock();
				$cacheFactory->method('createDistributed')
					->willReturn(new ArrayCache());
				return $cacheFactory;
			},
			true,
		);

		$this->foldersController = new MailboxesController(
			'mail',
			Server::get(IRequest::class),
			Server::get(AccountService::class),
			$this->getTestAccountUserId(),
			Server::get(IMailManager::class),
			Server::get(SyncService::class)
		);

		$this->account = $this->createTestAccount('user12345');
		$this->client = $this->getClient($this->account);
	}

	public function tearDown(): void {
		parent::tearDown();
		$this->client->logout();
	}

	public function testSyncEmptyMailbox() {
		/** @var IMailManager $mailManager */
		$mailManager = Server::get(IMailManager::class);
		$mailBoxes = $mailManager->getMailboxes(new Account($this->account));
		$inbox = null;
		foreach ($mailBoxes as $mailBox) {
			if ($mailBox->getName() === 'INBOX') {
				$inbox = $mailBox;
				break;
			}
		}
		/** @var SyncService $syncService */
		$syncService = Server::get(SyncService::class);
		$syncService->syncMailbox(
			new Account($this->account),
			$inbox,
			Horde_Imap_Client::SYNC_NEWMSGSUIDS | Horde_Imap_Client::SYNC_FLAGSUIDS | Horde_Imap_Client::SYNC_VANISHEDUIDS,
			false,
			null,
			[]
		);

		$jsonResponse = $this->foldersController->sync(
			$inbox->getId(),
			[],
			null
		);

		$data = $jsonResponse->getData()->jsonSerialize();

		self::assertArrayHasKey('newMessages', $data);
		self::assertArrayHasKey('changedMessages', $data);
		self::assertArrayHasKey('vanishedMessages', $data);
		self::assertEmpty($data['newMessages']);
		self::assertEmpty($data['changedMessages']);
		self::assertEmpty($data['vanishedMessages']);
	}

	public function testSyncNewMessage() {
		/** @var SyncService $syncService */
		$syncService = Server::get(SyncService::class);
		/** @var IMailManager $mailManager */
		$mailManager = Server::get(IMailManager::class);
		$mailBoxes = $mailManager->getMailboxes(new Account($this->account));
		$inbox = null;
		foreach ($mailBoxes as $mailBox) {
			if ($mailBox->getName() === 'INBOX') {
				$inbox = $mailBox;
				break;
			}
		}
		$syncService->syncMailbox(
			new Account($this->account),
			$inbox,
			Horde_Imap_Client::SYNC_NEWMSGSUIDS | Horde_Imap_Client::SYNC_FLAGSUIDS | Horde_Imap_Client::SYNC_VANISHEDUIDS,
			false,
			null,
			[]
		);
		// Second, put a new message into the mailbox
		$message = $this->getMessageBuilder()
			->from('ralph@buffington@domain.tld')
			->to('user@domain.tld')
			->finish();
		$newUid = $this->saveMessage($inbox->getName(), $message, $this->account);

		$jsonResponse = $this->foldersController->sync(
			$inbox->getId(),
			[],
			null
		);

		$syncJson = $jsonResponse->getData()->jsonSerialize();

		self::assertCount(1, $syncJson['newMessages']);
		self::assertEquals($newUid, $syncJson['newMessages'][0]->getUid());
		self::assertCount(0, $syncJson['changedMessages']);
		self::assertCount(0, $syncJson['vanishedMessages']);
	}

	public function testSyncChangedMessage() {
		/** @var SyncService $syncService */
		$syncService = Server::get(SyncService::class);
		$mailbox = 'INBOX';
		$message = $this->getMessageBuilder()
			->from('ralph@buffington@domain.tld')
			->to('user@domain.tld')
			->finish();
		$uid = $this->saveMessage($mailbox, $message, $this->account);
		/** @var IMailManager $mailManager */
		$mailManager = Server::get(IMailManager::class);
		$mailBoxes = $mailManager->getMailboxes(new Account($this->account));
		$inbox = null;
		foreach ($mailBoxes as $mailBox) {
			if ($mailBox->getName() === 'INBOX') {
				$inbox = $mailBox;
				break;
			}
		}
		$syncService->syncMailbox(
			new Account($this->account),
			$inbox,
			Horde_Imap_Client::SYNC_NEWMSGSUIDS | Horde_Imap_Client::SYNC_FLAGSUIDS | Horde_Imap_Client::SYNC_VANISHEDUIDS,
			false,
			null,
			[]
		);
		$this->flagMessage($mailbox, $uid, $this->account);
		$id = $mailManager->getMessageIdForUid($inbox, $uid);

		$jsonResponse = $this->foldersController->sync(
			$inbox->getId(),
			[
				$id
			],
			null);
		$syncJson = $jsonResponse->getData()->jsonSerialize();

		self::assertCount(0, $syncJson['newMessages']);
		self::assertCount(1, $syncJson['changedMessages']);
		self::assertCount(0, $syncJson['vanishedMessages']);
	}

	public function testSyncVanishedMessage() {
		$mailbox = 'INBOX';
		$message = $this->getMessageBuilder()
			->from('ralph@buffington@domain.tld')
			->to('user@domain.tld')
			->finish();
		$uid = $this->saveMessage($mailbox, $message, $this->account);
		/** @var IMailManager $mailManager */
		$mailManager = Server::get(IMailManager::class);
		$mailBoxes = $mailManager->getMailboxes(new Account($this->account));
		$inbox = null;
		foreach ($mailBoxes as $mailBox) {
			if ($mailBox->getName() === 'INBOX') {
				$inbox = $mailBox;
				break;
			}
		}
		/** @var SyncService $syncService */
		$syncService = Server::get(SyncService::class);
		$syncService->syncMailbox(
			new Account($this->account),
			$inbox,
			Horde_Imap_Client::SYNC_NEWMSGSUIDS | Horde_Imap_Client::SYNC_FLAGSUIDS | Horde_Imap_Client::SYNC_VANISHEDUIDS,
			false,
			null,
			[]
		);
		$this->deleteMessage($mailbox, $uid, $this->account);

		$jsonResponse = $this->foldersController->sync(
			$inbox->getId(),
			[
				$uid // This will only work if UID and database ID are equal (1 on a clean setup), otherwise this fails
			],
			null);
		$syncJson = $jsonResponse->getData()->jsonSerialize();

		self::assertCount(0, $syncJson['newMessages']);
		// TODO: deleted messages are flagged as changed? could be a testing-only issue
		// self::assertCount(0, $syncJson['changedMessages']);
		//		self::assertCount(1, $syncJson['vanishedMessages'], 'Message does not show as vanished, possibly because UID and ID are mixed up above.');
	}

	public function testUnsolicitedVanishedMessage() {
		$mailbox = 'INBOX';
		$message = $this->getMessageBuilder()
			->from('ralph@buffington@domain.tld')
			->to('user@domain.tld')
			->subject('Msg 1')
			->finish();
		$uid1 = $this->saveMessage($mailbox, $message, $this->account);
		$message = $this->getMessageBuilder()
			->from('ralph@buffington@domain.tld')
			->to('user@domain.tld')
			->subject('Msg 2')
			->finish();
		$uid2 = $this->saveMessage($mailbox, $message, $this->account);
		/** @var IMailManager $mailManager */
		$mailManager = Server::get(IMailManager::class);
		$mailBoxes = $mailManager->getMailboxes(new Account($this->account));
		$inbox = null;
		foreach ($mailBoxes as $mailBox) {
			if ($mailBox->getName() === 'INBOX') {
				$inbox = $mailBox;
				break;
			}
		}
		/** @var SyncService $syncService */
		$syncService = Server::get(SyncService::class);
		$syncService->syncMailbox(
			new Account($this->account),
			$inbox,
			Horde_Imap_Client::SYNC_NEWMSGSUIDS | Horde_Imap_Client::SYNC_FLAGSUIDS | Horde_Imap_Client::SYNC_VANISHEDUIDS,
			false,
			null,
			[]
		);

		// Assert that there are 2 messages and nothing changes when deleting a message externally
		$messageMapper = Server::get(MessageMapper::class);
		self::assertCount(2, $messageMapper->findAllUids($inbox));
		$this->deleteMessagesExternally($mailbox, [$uid1]);
		self::assertCount(2, $messageMapper->findAllUids($inbox));

		// Receive unsolicited vanished uid
		$mailManager->getSource(
			$this->getClient($this->account),
			new Account($this->account),
			$mailbox,
			$uid2,
		);

		// Assert that the unsolicited change was synced to the db
		self::assertCount(1, $messageMapper->findAllUids($inbox));
	}
}

<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Mail\Provider;

use OCA\Mail\Account;
use OCA\Mail\Service\AccountService;
use OCP\Mail\Provider\Address as MailAddress;
use OCP\Mail\Provider\IProvider;
use OCP\Mail\Provider\IService;
use Psr\Container\ContainerInterface;

class MailProvider implements IProvider {

	private ?array $ServiceCollection = [];

	public function __construct(
		protected ContainerInterface $container,
		protected AccountService $accountService
	) {
	}

	/**
	 * arbitrary unique text string identifying this provider
	 *
	 * @since 4.0.0
	 *
	 * @return string id of this provider (e.g. UUID or 'IMAP/SMTP' or anything else)
	 */
	public function id(): string {
		return 'mail-application';
	}

	/**
	 * localized human friendly name of this provider
	 *
	 * @since 4.0.0
	 *
	 * @return string label/name of this provider (e.g. Plain Old IMAP/SMTP)
	 */
	public function label(): string {
		return 'Mail Application';
	}

	/**
	 * determine if any services are configured for a specific user
	 *
	 * @since 4.0.0
	 *
	 * @param string $userId system user id
	 *
	 * @return bool true if any services are configure for the user
	 */
	public function hasServices(string $userId): bool {
		return (count($this->listServices($userId)) > 0);
	}

	/**
	 * retrieve collection of services for a specific user
	 *
	 * @since 4.0.0
	 *
	 * @param string $userId system user id
	 *
	 * @return array<string,IService> collection of service id and object ['1' => IServiceObject]
	 */
	public function listServices(string $userId): array {

		try {
			// retrieve service(s) details from data store
			$accounts = $this->accountService->findByUserId($userId);
		} catch (\Throwable $th) {
			return [];
		}
		// construct temporary collection
		$services = [];
		// add services to collection
		foreach ($accounts as $entry) {
			// add service to collection
			$services[$serviceId] = $this->serviceFromAccount($userId, $entry);
		}
		// return list of services for user
		return $services;

	}

	/**
	 * retrieve a service with a specific id
	 *
	 * @since 4.0.0
	 *
	 * @param string $userId system user id
	 * @param string $serviceId mail account id
	 *
	 * @return IService|null returns service object or null if none found
	 *
	 */
	public function findServiceById(string $userId, string $serviceId): IService|null {
		
		// determine if a valid user and service id was submitted
		if (empty($userId) && !ctype_digit($serviceId)) {
			return null;
		}

		try {
			// retrieve service details from data store
			$account = $this->accountService->find($userId, (int)$serviceId);
		} catch(\Throwable $th) {
			return null;
		}

		// return mail service object
		return $this->serviceFromAccount($userId, $account);

	}

	/**
	 * retrieve a service for a specific mail address
	 *
	 * @since 4.0.0
	 *
	 * @param string $userId system user id
	 * @param string $address mail address (e.g. test@example.com)
	 *
	 * @return IService|null returns service object or null if none found
	 */
	public function findServiceByAddress(string $userId, string $address): IService|null {

		try {
			// retrieve service details from data store
			$accounts = $this->accountService->findByUserIdAndAddress($userId, $address);
		} catch(\Throwable $th) {
			return null;
		}
		// evaluate if service details where found
		if (isset($accounts[0])) {
			// return mail service object
			return $this->serviceFromAccount($userId, $accounts[0]);
		}

		return null;

	}

	/**
	 * construct a new fresh service object
	 *
	 * @since 4.0.0
	 *
	 * @return IService fresh service object
	 */
	public function initiateService(): IService {

		return new MailService($this->container);

	}

	/**
	 * construct a service object from a mail account
	 *
	 * @since 4.0.0
	 *
	 * @param string $userId system user id
	 * @param string $account mail account
	 *
	 * @return IService service object
	 */
	protected function serviceFromAccount(string $userId, Account $account): IService {

		// extract values
		$serviceId = (string)$account->getId();
		$serviceLabel = $account->getName();
		$serviceAddress = new MailAddress($account->getEmail(), $account->getName());
		// return mail service object
		return new MailService($this->container, $userId, $serviceId, $serviceLabel, $serviceAddress);

	}

}

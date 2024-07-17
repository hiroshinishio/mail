<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Controller;

use Horde\ManageSieve\Exception as ManagesieveException;
use OCA\Mail\AppInfo\Application;
use OCA\Mail\Db\MailAccountMapper;
use OCA\Mail\Db\MailboxMapper;
use OCA\Mail\Exception\ClientException;
use OCA\Mail\Exception\CouldNotConnectException;
use OCA\Mail\Http\JsonResponse as MailJsonResponse;
use OCA\Mail\Http\TrapError;
use OCA\Mail\Service\MailFilter\Definition;
use OCA\Mail\Service\MailFilter\FilterBuilder;
use OCA\Mail\Service\MailFilter\FilterParser;
use OCA\Mail\Service\OutOfOffice\OutOfOfficeParser;
use OCA\Mail\Service\SieveService;
use OCA\Mail\Sieve\SieveClientFactory;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\Route;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class MailfilterController extends OCSController {
	private string $currentUserId;

	public function __construct(IRequest $request,
		string $UserId,
		private MailAccountMapper $mailAccountMapper,
		private SieveClientFactory $sieveClientFactory,
		private LoggerInterface $logger,
		private SieveService $sieveService,
		private MailboxMapper $mailboxMapper,
		private FilterParser $filterParser,
		private OutOfOfficeParser $outOfOfficeParser,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->currentUserId = $UserId;
	}

	#[Route(Route::TYPE_FRONTPAGE, verb: 'GET', url: '/api/mailfilter/{accountId}', requirements: ['accountId' => '[\d]+'])]
	public function getFilters(int $accountId) {
		$script = $this->sieveService->getActiveScript($this->currentUserId, $accountId);

		$result = $this->filterParser->parseSieve($script->getScript());

		return new JSONResponse($result->getFilters());
	}

	#[Route(Route::TYPE_FRONTPAGE, verb: 'PUT', url: '/api/mailfilter/{accountId}', requirements: ['accountId' => '[\d]+'])]
	public function updateFilters(int $accountId, array $filters) {
		$script = $this->sieveService->getActiveScript($this->currentUserId, $accountId);



		$oldState = $this->filterParser->parseSieve($script->getScript());

		//		$newScript = $this->outOfOfficeParser->buildSieveScript(
		//			$state,
		//			$oldState->getUntouchedSieveScript(),
		//			$this->buildAllowedRecipients($account),
		//		);
		//		try {
		//			$this->sieveService->updateActiveScript($account->getUserId(), $account->getId(), $newScript);
		//		} catch (ManageSieveException $e) {
		//			$this->logger->error('Failed to save sieve script: ' . $e->getMessage(), [
		//				'exception' => $e,
		//				'script' => $newScript,
		//			]);
		//			throw $e;
		//		}

		//		$definition = new Definition($filters);

		$sieve = new FilterBuilder();
		$newScript = $sieve->buildSieve($filters, $oldState->getUntouchedSieveScript());

		try {
			$this->sieveService->updateActiveScript($this->currentUserId, $accountId, $newScript);
		} catch (ManageSieveException $e) {
			$this->logger->error('Failed to save sieve script: ' . $e->getMessage(), [
				'exception' => $e,
				'script' => $newScript,
			]);
			throw $e;
		}

		return new JSONResponse([]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id account id
	 *
	 * @return JSONResponse
	 *
	 * @throws CouldNotConnectException
	 * @throws ClientException
	 * @throws ManagesieveException
	 */
	#[TrapError]
	public function getActiveScript(int $id): JSONResponse {
		$activeScript = $this->sieveService->getActiveScript($this->currentUserId, $id);
		return new JSONResponse([
			'scriptName' => $activeScript->getName(),
			'script' => $activeScript->getScript(),
		]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id account id
	 * @param string $script
	 *
	 * @return JSONResponse
	 *
	 * @throws ClientException
	 * @throws CouldNotConnectException
	 */
	#[TrapError]
	public function updateActiveScript(int $id, string $script): JSONResponse {
		try {
			$this->sieveService->updateActiveScript($this->currentUserId, $id, $script);
		} catch (ManagesieveException $e) {
			$this->logger->error('Installing sieve script failed: ' . $e->getMessage(), ['app' => 'mail', 'exception' => $e]);
			return new JSONResponse(data: ['message' => $e->getMessage()], statusCode: Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		return new JSONResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id account id
	 * @param bool $sieveEnabled
	 * @param string $sieveHost
	 * @param int $sievePort
	 * @param string $sieveUser
	 * @param string $sievePassword
	 * @param string $sieveSslMode
	 *
	 * @return JSONResponse
	 *
	 * @throws CouldNotConnectException
	 * @throws DoesNotExistException
	 */
	#[TrapError]
	public function updateAccount(int $id,
		bool $sieveEnabled,
		string $sieveHost,
		int $sievePort,
		string $sieveUser,
		string $sievePassword,
		string $sieveSslMode
	): JSONResponse {
		if (!$this->hostValidator->isValid($sieveHost)) {
			return MailJsonResponse::fail(
				[
					'error' => 'CONNECTION_ERROR',
					'service' => 'ManageSieve',
					'host' => $sieveHost,
					'port' => $sievePort,
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		$mailAccount = $this->mailAccountMapper->find($this->currentUserId, $id);

		if ($sieveEnabled === false) {
			$mailAccount->setSieveEnabled(false);
			$mailAccount->setSieveHost(null);
			$mailAccount->setSievePort(null);
			$mailAccount->setSieveUser(null);
			$mailAccount->setSievePassword(null);
			$mailAccount->setSieveSslMode(null);

			$this->mailAccountMapper->save($mailAccount);
			return new JSONResponse(['sieveEnabled' => $mailAccount->isSieveEnabled()]);
		}

		if (empty($sieveUser)) {
			$sieveUser = $mailAccount->getInboundUser();
		}

		if (empty($sievePassword)) {
			$sievePassword = $mailAccount->getInboundPassword();
		} else {
			$sievePassword = $this->crypto->encrypt($sievePassword);
		}

		try {
			$this->sieveClientFactory->createClient($sieveHost, $sievePort, $sieveUser, $sievePassword, $sieveSslMode);
		} catch (ManagesieveException $e) {
			throw new CouldNotConnectException($e, 'ManageSieve', $sieveHost, $sievePort);
		}

		$mailAccount->setSieveEnabled(true);
		$mailAccount->setSieveHost($sieveHost);
		$mailAccount->setSievePort($sievePort);
		$mailAccount->setSieveUser($mailAccount->getInboundUser() === $sieveUser ? null : $sieveUser);
		$mailAccount->setSievePassword($mailAccount->getInboundPassword() === $sievePassword ? null : $sievePassword);
		$mailAccount->setSieveSslMode($sieveSslMode);

		$this->mailAccountMapper->save($mailAccount);
		return new JSONResponse(['sieveEnabled' => $mailAccount->isSieveEnabled()]);
	}
}

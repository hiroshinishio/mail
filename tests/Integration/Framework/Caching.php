<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Tests\Integration\Framework;

use OC\Memcache\Factory;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\Profiler\IProfiler;
use OCP\Server;
use Psr\Log\LoggerInterface;

class Caching {
	/**
	 * Force usage of a real cache as configured in system config. The original ICacheFactory
	 * service closure is hard-coded to always return an instance of ArrayCache when the global
	 * PHPUNIT_RUN is defined.
	 */
	public static function registerConfiguredCache(): void {
		\OC::$server->registerService(
			ICacheFactory::class,
			function () {
				$config = Server::get(IConfig::class);
				return new Factory(
					'mail-integration-tests',
					Server::get(LoggerInterface::class),
					Server::get(IProfiler::class),
					$config->getSystemValue('memcache.local', null),
					$config->getSystemValue('memcache.distributed', null),
					$config->getSystemValue('memcache.locking', null),
					$config->getSystemValueString('redis_log_file')
				);
			},
		);
	}
}

<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Tests\Unit\Service\MailFilter;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\Mail\Service\MailFilter\SieveBuilder;

class SieveBuilderTest extends TestCase {
	private SieveBuilder $sieveBuilder;

	public function setUp(): void {
		parent::setUp();
		$this->sieveBuilder = new SieveBuilder();
	}

	/**
	 * @dataProvider dataBuild
	 */
	public function testBuild(string $testName): void {
		$filters = json_decode(
			file_get_contents(__DIR__ . '/../../../data/' . $testName . '.json'),
			true,
			512,
			JSON_THROW_ON_ERROR
		);

		$script = $this->sieveBuilder->build($filters);

		$this->assertStringEqualsFile(
			__DIR__ . '/../../../data/' . $testName . '.sieve',
			$script
		);
	}

	public function dataBuild(): array {
		return [
			['filter-test-1']
		];
	}
}

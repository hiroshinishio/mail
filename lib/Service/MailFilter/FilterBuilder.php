<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Service\MailFilter;

class FilterBuilder {
	private const SEPARATOR = '### Nextcloud Mail: Filters ### DON\'T EDIT ###';
	private const DATA_MARKER = '# DATA: ';

	public function buildSieve(array $filters, string $untouchedScript): string {
		$commands = [];
		$extensions = [];

		foreach ($filters as $filter) {
			if ($filter['enable'] === false) {
				continue;
			}

			$commands[] = '# Filter: ' . $filter['name'];

			$tests = [];
			foreach ($filter['tests'] as $test) {
				if ($test['field'] === 'subject') {
					$tests[] = sprintf(
						'header :%s "subject" %s',
						$test['operator'],
						$this->stringList($test['value'])
					);
				}
				if ($test['field'] === 'to') {
					$tests[] = sprintf(
						'address :%s :all "to" %s',
						$test['operator'],
						$this->stringList($test['value'])
					);
				}
			}

			$actions = [];
			foreach ($filter['actions'] as $action) {
				if ($action['type'] === 'fileinto') {
					$extensions[] = 'fileinto';
					$actions[] = sprintf(
						'fileinto "%s";',
						$action['mailbox']
					);
				}
				if ($action['type'] === 'addflag') {
					$extensions[] = 'imap4flags';
					$actions[] = sprintf(
						'addflag %s;',
						$this->stringList($action['flag'])
					);
				}
			}

			if (count($tests) > 1) {
				$ifTest = sprintf('%s (%s)', $filter['operator'], implode(', ', $tests));
			} else {
				$ifTest = $tests[0];
			}

			$ifBlock = sprintf(
				"if %s {\r\n%s\r\n}\r\n",
				$ifTest,
				implode("\r\n", $actions)
			);

			$commands[] = $ifBlock;
		}

		$extensions = array_unique($extensions);

		$requireSection = [
			self::SEPARATOR,
			'require ' . $this->stringList($extensions) . ';',
			self::SEPARATOR,
		];

		$stateJsonString = json_encode($this->sanitizeDefinition($filters), JSON_THROW_ON_ERROR);

		$filterSection = [
			self::SEPARATOR,
			self::DATA_MARKER . $stateJsonString,
			...$commands,
		];

		return implode("\r\n", array_merge(
			$requireSection,
			[$untouchedScript],
			$filterSection,
		));
	}

	private function stringList(string|array $value): string {
		if (is_string($value)) {
			$items = explode(',', $value);
		} else {
			$items = $value;
		}

		$items = array_map([$this, 'quoteString'], $items);

		return '[' . implode(', ', $items) . ']';
	}

	private function quoteString(string $value): string {
		return '"' . $value . '"';
	}

	private function sanitizeDefinition(array $filters): array {
		return array_map(static function ($filter) {
			unset($filter['accountId'], $filter['id']);
			$filter['tests'] = array_map(static function ($test) {
				unset($test['id']);
				return $test;
			}, $filter['tests']);
			$filter['actions'] = array_map(static function ($action) {
				unset($action['id']);
				return $action;
			}, $filter['actions']);
			return $filter;
		}, $filters);
	}
}

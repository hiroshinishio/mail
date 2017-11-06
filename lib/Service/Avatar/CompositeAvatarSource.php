<?php

/**
 * @copyright 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Mail\Service\Avatar;

/**
 * Composition of all avatar sources for easier usage
 */
class CompositeAvatarSource implements IAvatarSource {

	/** @var IAvatarSource[] */
	private $sources;

	/**
	 * @param AddressbookSource $addressbookSource
	 * @param FaviconSource $faviconSource
	 * @param GravatarSource $gravatarSource
	 */
	public function __construct(AddressbookSource $addressbookSource, FaviconSource $faviconSource, GravatarSource $gravatarSource) {
		// This determines the priority of known sources
		$this->sources = [
			$addressbookSource,
			$gravatarSource,
			$faviconSource,
		];
	}

	/**
	 * Find avatar URL with the help of avatar sources and return the first
	 * valid result.
	 *
	 * @param string $email
	 * @return string|null
	 */
	public function fetch($email) {
		foreach ($this->sources as $source) {
			$avatar = $source->fetch($email);

			if (is_null($avatar)) {
				continue;
			}

			return $avatar;
		}

		return null;
	}

}

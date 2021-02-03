<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCP\App\Maintenance;

/**
 * @since 22.0.0
 */
interface IMissingIndex {
	/**
	 * Get the name of the missing index to display
	 *
	 * @since 22.0.0
	 * @return string
	 */
	public function getMissingIndexName(): string;

	/**
	 * Add the index
	 *
	 * @since 22.0.0
	 */
	public function addIndex(): void;

	/**
	 * Get the SQL for the index creation so we can display this for admins
	 * to run the queries directly on their DB
	 *
	 * @since 22.0.0
	 * @return string
	 */
	public function getSQL(): string;
}

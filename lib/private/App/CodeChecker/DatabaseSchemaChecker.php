<?php
/**
 * @copyright Copyright (c) 2017, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\App\CodeChecker;

class DatabaseSchemaChecker {

	/**
	 * @param string $appId
	 * @return array
	 */
	public function analyse($appId) {
		$appPath = \OC_App::getAppPath($appId);
		if ($appPath === false) {
			throw new \RuntimeException("No app with given id <$appId> known.");
		}

		if (!file_exists($appPath . '/appinfo/database.xml')) {
			return ['errors' => [], 'warnings' => []];
		}

		libxml_use_internal_errors(true);
		$loadEntities = libxml_disable_entity_loader(false);
		$xml = simplexml_load_file($appPath . '/appinfo/database.xml');
		libxml_disable_entity_loader($loadEntities);


		$errors = $warnings = [];

		foreach ($xml->table as $table) {
			// Table names
			if (strpos($table->name, '*dbprefix*') !== 0) {
				$errors[] = 'Database schema error: name of table ' . $table->name . ' does not start with *dbprefix*';
			}
			$tableName = substr($table->name, strlen('*dbprefix*'));
			if (strpos($tableName, '*dbprefix*') !== false) {
				$warnings[] = 'Database schema warning: *dbprefix* should only appear once in name of table ' . $table->name;
			}

			if (strlen($tableName) > 27) {
				$errors[] = 'Database schema error: Name of table ' . $table->name . ' is too long (' . strlen($tableName) . '), max. 27 characters (21 characters for tables with autoincrement) + *dbprefix* allowed';
			}

			$hasAutoIncrement = false;

			// Column names
			foreach ($table->declaration->field as $column) {
				if (strpos($column->name, '*dbprefix*') !== false) {
					$warnings[] = 'Database schema warning: *dbprefix* should not appear in name of column ' . $column->name . ' on table ' . $table->name;
				}

				if (strlen($column->name) > 30) {
					$errors[] = 'Database schema error: Name of column ' . $column->name . ' on table ' . $table->name . ' is too long (' . strlen($tableName) . '), max. 30 characters allowed';
				}

				if ($column->autoincrement) {
					if ($hasAutoIncrement) {
						$errors[] = 'Database schema error: Table ' . $table->name . ' has multiple autoincrement columns';
					}

					if (strlen($tableName) > 21) {
						$errors[] = 'Database schema error: Name of table ' . $table->name . ' is too long (' . strlen($tableName) . '), max. 27 characters (21 characters for tables with autoincrement) + *dbprefix* allowed';
					}

					$hasAutoIncrement = true;
				}
			}

			// Index names
			foreach ($table->declaration->index as $index) {
				$hasPrefix = strpos($index->name, '*dbprefix*');
				if ($hasPrefix !== false && $hasPrefix !== 0) {
					$warnings[] = 'Database schema warning: *dbprefix* should only appear at the beginning in name of index ' . $index->name . ' on table ' . $table->name;
				}

				$indexName = $hasPrefix === 0 ? substr($index->name, strlen('*dbprefix*')) : $index->name;
				if (strlen($indexName) > 27) {
					$errors[] = 'Database schema error: Name of index ' . $index->name . ' on table ' . $table->name . ' is too long (' . strlen($tableName) . '), max. 27 characters + *dbprefix* allowed';
				}
			}
		}

		return ['errors' => $errors, 'warnings' => $warnings];
	}
}

<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud GmbH.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <vincent@nextcloud.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\DAV\AppInfo;

use OC\ServerContainer;
use OCA\DAV\CalDAV\Integration\ICalendarProvider;
use OCA\DAV\CardDAV\Integration\IAddressBookProvider;
use OCP\App\IAppManager;
use OCP\AppFramework\QueryException;
use Sabre\DAV\Collection;
use Sabre\DAV\ServerPlugin;
use function array_map;
use function class_exists;
use function is_array;

/**
 * Manager for DAV plugins from apps, used to register them
 * to the Sabre server.
 */
class PluginManager {

	/**
	 * @var ServerContainer
	 */
	private $container;

	/**
	 * @var IAppManager
	 */
	private $appManager;

	/**
	 * App plugins
	 *
	 * @var ServerPlugin[]
	 */
	private $plugins = [];

	/**
	 * App collections
	 *
	 * @var Collection[]
	 */
	private $collections = [];

	/**
	 * Address book plugins
	 *
	 * @var IAddressBookProvider[]
	 */
	private $addressBookPlugins = [];

	/**
	 * Calendar plugins
	 *
	 * @var ICalendarProvider[]
	 */
	private $calendarPlugins = [];

	/** @var bool */
	private $populated = false;

	/**
	 * Contstruct a PluginManager
	 *
	 * @param ServerContainer $container server container for resolving plugin classes
	 * @param IAppManager $appManager app manager to loading apps and their info
	 */
	public function __construct(ServerContainer $container, IAppManager $appManager) {
		$this->container = $container;
		$this->appManager = $appManager;
	}

	/**
	 * Returns an array of app-registered plugins
	 *
	 * @return ServerPlugin[]
	 */
	public function getAppPlugins() {
		$this->populate();
		return $this->plugins;
	}

	/**
	 * Returns an array of app-registered collections
	 *
	 * @return array
	 */
	public function getAppCollections() {
		$this->populate();
		return $this->collections;
	}

	/**
	 * @return IAddressBookProvider[]
	 */
	public function getAddressBookPlugins(): array {
		$this->populate();
		return $this->addressBookPlugins;
	}

	/**
	 * Returns an array of app-registered calendar plugins
	 *
	 * @return ICalendarProvider[]
	 */
	public function getCalendarPlugins():array {
		$this->populate();
		return $this->calendarPlugins;
	}

	/**
	 * Retrieve plugin and collection list and populate attributes
	 */
	private function populate(): void {
		if ($this->populated) {
			return;
		}

		foreach ($this->appManager->getInstalledApps() as $app) {
			// load plugins and collections from info.xml
			$info = $this->appManager->getAppInfo($app);
			if (!isset($info['types']) || !in_array('dav', $info['types'], true)) {
				continue;
			}
			$plugins = $this->loadSabrePluginsFromInfoXml($this->extractPluginList($info));
			foreach ($plugins as $plugin) {
				$this->plugins[] = $plugin;
			}

			$collections = $this->loadSabreCollectionsFromInfoXml($this->extractCollectionList($info));
			foreach ($collections as $collection) {
				$this->collections[] = $collection;
			}

			$addresbookPlugins = $this->loadSabreAddressBookPluginsFromInfoXml($this->extractAddressBookPluginList($info));
			foreach ($addresbookPlugins as $addresbookPlugin) {
				$this->addressBookPlugins[] = $addresbookPlugin;
			}

			$calendarPlugins = $this->loadSabreCalendarPluginsFromInfoXml($this->extractCalendarPluginList($info));
			foreach ($calendarPlugins as $calendarPlugin) {
				$this->calendarPlugins[] = $calendarPlugin;
			}
		}
	}

	private function extractPluginList(array $array): array {
		if (isset($array['sabre']) && is_array($array['sabre'])) {
			if (isset($array['sabre']['plugins']) && is_array($array['sabre']['plugins'])) {
				if (isset($array['sabre']['plugins']['plugin'])) {
					$items = $array['sabre']['plugins']['plugin'];
					if (!is_array($items)) {
						$items = [$items];
					}
					return $items;
				}
			}
		}
		return [];
	}

	private function extractCollectionList(array $array): array {
		if (isset($array['sabre']) && is_array($array['sabre'])) {
			if (isset($array['sabre']['collections']) && is_array($array['sabre']['collections'])) {
				if (isset($array['sabre']['collections']['collection'])) {
					$items = $array['sabre']['collections']['collection'];
					if (!is_array($items)) {
						$items = [$items];
					}
					return $items;
				}
			}
		}
		return [];
	}

	/**
	 * @param array $array
	 *
	 * @return string[]
	 */
	private function extractAddressBookPluginList(array $array): array {
		if (!isset($array['sabre']) || !is_array($array['sabre'])) {
			return [];
		}
		if (!isset($array['sabre']['address-book-plugins']) || !is_array($array['sabre']['address-book-plugins'])) {
			return [];
		}
		if (!isset($array['sabre']['address-book-plugins']['plugin'])) {
			return [];
		}

		$items = $array['sabre']['address-book-plugins']['plugin'];
		if (!is_array($items)) {
			$items = [$items];
		}
		return $items;
	}

	private function extractCalendarPluginList(array $array): array {
		if (isset($array['sabre']) && is_array($array['sabre'])) {
			if (isset($array['sabre']['calendar-plugins']) && is_array($array['sabre']['calendar-plugins'])) {
				if (isset($array['sabre']['calendar-plugins']['plugin'])) {
					$items = $array['sabre']['calendar-plugins']['plugin'];
					if (!is_array($items)) {
						$items = [$items];
					}
					return $items;
				}
			}
		}
		return [];
	}


	/**
	 * @param array $collections
	 * @return ServerPlugin[]
	 * @throws \Exception
	 */
	private function loadSabrePluginsFromInfoXml(array $plugins): array {
		$result = [];
		foreach ($plugins as $plugin) {
			try {
				$result[] = $this->container->query($plugin);
			} catch (QueryException $e) {
				if (class_exists($plugin)) {
					$result[] = new $plugin();
				} else {
					throw new \Exception("Sabre plugin class '$plugin' is unknown and could not be loaded");
				}
			}
		}

		foreach ($result as $plugin) {
			if (!($plugin instanceof ServerPlugin)) {
				throw new \Exception('Sabre plugin class ' . get_class($plugin) . ' is not an instance of ' . ServerPlugin::class);
			}
		}

		return $result;
	}

	/**
	 * @param array $collections
	 * @return Collection[]
	 */
	private function loadSabreCollectionsFromInfoXml(array $collections): array {
		$result = [];
		foreach ($collections as $collection) {
			try {
				$result[] = $this->container->query($collection);
			} catch (QueryException $e) {
				if (class_exists($collection)) {
					$result[] = new $collection();
				} else {
					throw new \Exception("Sabre collection class '$collection' is unknown and could not be loaded");
				}
			}
		}

		foreach ($result as $collection) {
			if (!($collection instanceof Collection)) {
				throw new \Exception('Sabre plugin class ' . get_class($collection) . ' is not an instance of ' . Collection::class);
			}
		}

		return $result;
	}

	private function createPluginInstance(string $className): object {
		try {
			return $this->container->query($className);
		} catch (QueryException $e) {
			if (class_exists($className)) {
				return new $className();
			}
		}

		throw new \Exception("Sabre plugin class '$className' is unknown and could not be loaded");
	}

	/**
	 * @param string[] $plugins
	 * @return IAddressBookProvider[]
	 */
	private function loadSabreAddressBookPluginsFromInfoXml(array $plugins): array {
		$providers = array_map(function (string $className): IAddressBookProvider {
			$instance = $this->createPluginInstance($className);
			if (!($instance instanceof IAddressBookProvider)) {
				throw new \Exception("Sabre address book plugin class '$className' does not implement the \OCA\DAV\CardDAV\Integration\IAddressBookProvider interface");
			}
			return $instance;
		}, $plugins);

		return $providers;
	}

	/**
	 * @param string[] $calendarPlugins
	 * @return ICalendarProvider[]
	 */
	private function loadSabreCalendarPluginsFromInfoXml(array $calendarPlugins): array {
		$result = [];
		foreach ($calendarPlugins as $calendarPlugin) {
			try {
				$instantiatedCalendarPlugin = $this->container->query($calendarPlugin);
			} catch (QueryException $e) {
				if (class_exists($calendarPlugin)) {
					$instantiatedCalendarPlugin = new $calendarPlugin();
				} else {
					throw new \Exception("Sabre calendar-plugin class '$calendarPlugin' is unknown and could not be loaded");
				}
			}

			if (!($instantiatedCalendarPlugin instanceof ICalendarProvider)) {
				throw new \Exception("Sabre calendar-plugin class '$calendarPlugin' does not implement ICalendarProvider interface");
			}

			$result[] = $instantiatedCalendarPlugin;
		}

		return $result;
	}
}

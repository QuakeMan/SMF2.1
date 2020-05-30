<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2020 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC3
 */

namespace SMF\Cache\APIs;

use SMF\Cache\CacheApi;
use SMF\Cache\CacheApiInterface;

if (!defined('SMF'))
	die('No direct access...');

/**
 * SQLite Cache API class
 *
 * @package CacheAPI
 */
class Sqlite extends CacheApi implements CacheApiInterface
{
	/**
	 * @var string The path to the current $cachedir directory.
	 */
	private $cachedir = null;

	/**
	 * @var SQLite3
	 */
	private $cacheDB = null;

	public function __construct()
	{
		parent::__construct();

		// Set our default cachedir.
		$this->setCachedir();
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect()
	{
		$database = $this->cachedir . '/' . 'SQLite3Cache.db3';
		$this->cacheDB = new \SQLite3($database);
		$this->cacheDB->busyTimeout(1000);

		if (0 == filesize($database))
		{
			$this->cacheDB->exec('CREATE TABLE cache (key text unique, value blob, ttl int);');
			$this->cacheDB->exec('CREATE INDEX ttls ON cache(ttl);');
		}

		$this->cacheTime = time();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		$supported = class_exists("SQLite3") && is_writable($this->cachedir);

		if ($test)
			return $supported;

		return parent::isSupported() && $supported;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData($key, $ttl = null)
	{
		$query = 'SELECT value FROM cache WHERE key = \'' . $this->cacheDB->escapeString($key) . '\' AND ttl >= ' . time() . ' LIMIT 1';
		$result = $this->cacheDB->query($query);

		$value = null;
		while ($res = $result->fetchArray(SQLITE3_ASSOC))
			$value = $res['value'];

		return !empty($value) ? $value : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
		$ttl = time() + (int) ($ttl !== null ? $ttl : $this->ttl);
		if ($value === null)
			$query = 'DELETE FROM cache WHERE key = \'' . $this->cacheDB->escapeString($key) . '\';';
		else
			$query = 'REPLACE INTO cache VALUES (\'' . $this->cacheDB->escapeString($key) . '\', \'' . $this->cacheDB->escapeString($value) . '\', ' . $ttl . ');';
		$result = $this->cacheDB->exec($query);

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = '')
	{
		if ($type == 'expired')
			$query = 'DELETE FROM cache WHERE ttl >= ' . time() . ';';

		else
			$query = 'DELETE FROM cache;';

		$result = $this->cacheDB->exec($query);

		$query = 'VACUUM;';
		$this->cacheDB->exec($query);

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function cacheSettings(array &$config_vars)
	{
		global $context, $txt;

		$class_key = $this->getImplementationClassKeyName();

		$config_vars[] = $txt['cache_'. $class_key .'_settings'];
		$config_vars[] = array('cachedir_'. $class_key, $txt['cachedir_'. $class_key], 'file', 'text', 36, 'cache_'. $class_key .'_cachedir');

		if (!isset($context['settings_post_javascript']))
			$context['settings_post_javascript'] = '';

		$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#cachedir_'. $class_key .'").prop("disabled", cache_type != "'. $class_key .'");
			});';
	}

	/**
	 * Sets the $cachedir or uses the SMF default $cachedir..
	 *
	 * @access public
	 *
	 * @param string $dir A valid path
	 *
	 * @return boolean If this was successful or not.
	 */
	public function setCachedir($dir = null)
	{
		global $cachedir, $cachedir_sqlite;

		// If its invalid, use SMF's.
		if (is_null($dir) || !is_writable($dir))
		{
			if (is_null($cachedir_sqlite) || !is_writable($cachedir_sqlite))
				$this->cachedir = $cachedir;

			else
				$this->cachedir = $cachedir_sqlite;
		}

		else
			$this->cachedir = $dir;

		return !is_null($this->cachedir);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getVersion()
	{
		$temp = $this->cacheDB->version();

		return $temp['versionString'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function housekeeping()
	{
		$this->cleanCache('expired');
	}
}

?>
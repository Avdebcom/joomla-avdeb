<?php

/**
 * @package     AVDEB Products for Joomla
 * @copyright   (C) 2026 AVDEB <https://avdeb.com>
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

/**
 * Package installer: Joomla installs plugins disabled, so the content plugin and the editor button
 * are enabled on first install (never on update — an admin may have disabled them on purpose).
 */
return new class () implements InstallerScriptInterface {
    private const MIN_PHP    = '8.1.0';
    private const MIN_JOOMLA = '5.4.0';

    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        if (version_compare(PHP_VERSION, self::MIN_PHP, '<') || version_compare(JVERSION, self::MIN_JOOMLA, '<')) {
            Factory::getApplication()->enqueueMessage(
                \sprintf('AVDEB Products needs Joomla %s+ and PHP %s+.', self::MIN_JOOMLA, self::MIN_PHP),
                'error'
            );

            return false;
        }

        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type !== 'install' && $type !== 'discover_install') {
            return true;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        foreach (['content', 'editors-xtd'] as $folder) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote($folder))
                ->where($db->quoteName('element') . ' = ' . $db->quote('avdeb'));
            $db->setQuery($query)->execute();
        }

        $app = Factory::getApplication();

        if ($app instanceof AdministratorApplication) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('content'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('avdeb'));
            $id = (int) $db->setQuery($query)->loadResult();

            $app->getLanguage()->load('pkg_avdeb.sys', JPATH_SITE);
            $app->enqueueMessage(Text::sprintf(
                'PKG_AVDEB_POSTFLIGHT',
                Route::_('index.php?option=com_plugins&task=plugin.edit&extension_id=' . $id)
            ), 'success');
        }

        return true;
    }
};

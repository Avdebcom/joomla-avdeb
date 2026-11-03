<?php

/**
 * @package     AVDEB Products for Joomla
 * @copyright   (C) 2026 AVDEB <https://avdeb.com>
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Avdeb\Plugin\EditorsXtd\Avdeb\Extension\AvdebButton;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(AvdebButton::class, function (Container $container) {
                $plugin = new AvdebButton((array) PluginHelper::getPlugin('editors-xtd', 'avdeb'));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};

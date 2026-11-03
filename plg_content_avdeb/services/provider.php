<?php

/**
 * @package     AVDEB Products for Joomla
 * @copyright   (C) 2026 AVDEB <https://avdeb.com>
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Avdeb\Plugin\Content\Avdeb\Extension\Avdeb;
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
            $container->lazy(Avdeb::class, function (Container $container) {
                $plugin = new Avdeb((array) PluginHelper::getPlugin('content', 'avdeb'));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};

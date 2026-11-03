<?php

/**
 * @package     AVDEB Products for Joomla
 * @copyright   (C) 2026 AVDEB <https://avdeb.com>
 * @license     GNU General Public License version 2 or later
 */

namespace Avdeb\Library\Store;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\OutputController;
use Joomla\CMS\Factory;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The site's Joomla cache (file, Redis, Memcached… whatever is configured), in its own group.
 * Caching is forced on for this group: the product cache must work even when page caching is off.
 * Entries live for a week (the "last good copy" window); freshness is decided by the client.
 */
final class JoomlaCacheStore implements StoreInterface
{
    public const GROUP = 'avdeb_products';

    private OutputController $cache;

    public function __construct(int $lifetimeMinutes = 7 * 24 * 60 + 60)
    {
        /** @var OutputController $controller */
        $controller  = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController('output', [
            'defaultgroup' => self::GROUP,
            'caching'      => true,
            'lifetime'     => $lifetimeMinutes,
        ]);
        $this->cache = $controller;
    }

    public function get(string $key): mixed
    {
        $value = $this->cache->get($key, self::GROUP);

        return $value === false ? null : $value;
    }

    public function set(string $key, mixed $value): void
    {
        $this->cache->store($value, $key, self::GROUP);
    }

    public function delete(string $key): void
    {
        $this->cache->remove($key, self::GROUP);
    }

    public function clear(): void
    {
        $this->cache->clean(self::GROUP);
    }
}

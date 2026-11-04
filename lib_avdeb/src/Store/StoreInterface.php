<?php

/**
 * @package     AVDEB Products for Joomla
 * @copyright   (C) 2026 AVDEB <https://avdeb.com>
 * @license     GNU General Public License version 2 or later
 */

namespace Avdeb\Library\Store;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/** Minimal key/value store for the catalog client (Joomla cache in production, an array in tests). */
interface StoreInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function delete(string $key): void;

    /** Drop every entry ("Refresh products now"). */
    public function clear(): void;
}

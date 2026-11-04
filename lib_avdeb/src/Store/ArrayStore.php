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

/** In-memory store (tests, or a request-local fallback when the Joomla cache can't be written). */
final class ArrayStore implements StoreInterface
{
    /** @var array<string, string> serialized values, so callers never share references */
    public array $data = [];

    public function get(string $key): mixed
    {
        return isset($this->data[$key]) ? unserialize($this->data[$key], ['allowed_classes' => false]) : null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = serialize($value);
    }

    public function delete(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }
}

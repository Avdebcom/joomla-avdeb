<?php

/**
 * @package     AVDEB Products for Joomla
 * @copyright   (C) 2026 AVDEB <https://avdeb.com>
 * @license     GNU General Public License version 2 or later
 */

namespace Avdeb\Module\AvdebProducts\Site\Dispatcher;

use Avdeb\Library\Embeds;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Renders the module's products with the shared AVDEB settings (Content - AVDEB Products plugin).
 * Empty module options ("Use Global") fall back to those settings.
 */
class Dispatcher extends AbstractModuleDispatcher
{
    /** The module's own embed fields (Joomla's `layout`, `style`, `header_tag`… must not leak in). */
    private const FIELDS = [
        'mode', 'link', 'source', 'limit', 'intro', 'view_all', 'variant', 'badge', 'note',
        'embed_layout', 'columns', 'card_style', 'theme', 'cta_label', 'show_price', 'show_creator',
    ];

    protected function getLayoutData()
    {
        $data   = parent::getLayoutData();
        $params = array_intersect_key($data['params']->toArray(), array_flip(self::FIELDS));

        // The module chrome prints the title as a heading; card titles sit one level below it.
        $headerTag = (string) $data['params']->get('header_tag', 'h3');
        $level     = preg_match('/^h([1-6])$/', $headerTag, $m) ? (int) $m[1] : 3;

        try {
            $data['avdebHtml'] = class_exists(Embeds::class)
                ? Embeds::instance()->render($params, $level)
                : '<!-- AVDEB Products: the AVDEB library is not installed -->';
        } catch (\Throwable $e) {
            $data['avdebHtml'] = '<!-- AVDEB Products: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ' -->';
        }

        return $data;
    }
}

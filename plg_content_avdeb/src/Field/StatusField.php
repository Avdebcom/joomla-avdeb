<?php

/**
 * @package     AVDEB Products for Joomla
 * @copyright   (C) 2026 AVDEB <https://avdeb.com>
 * @license     GNU General Public License version 2 or later
 */

namespace Avdeb\Plugin\Content\Avdeb\Field;

use Avdeb\Library\Embeds;
use Avdeb\Library\Renderer;
use Avdeb\Library\View;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Read-only "Connection" panel in the plugin settings: who the saved key belongs to, the referral
 * code links carry, a live preview, request stats and "Refresh products now". Reflects the SAVED
 * settings (Save the form to check a new key). Never prints the secret key.
 */
final class StatusField extends FormField
{
    protected $type = 'Status';

    private static function e(mixed $v): string
    {
        return Renderer::e($v);
    }

    private static function ago(?int $t): string
    {
        if (!$t) {
            return Text::_('PLG_CONTENT_AVDEB_STATUS_NEVER');
        }

        $min = (int) round((time() - $t) / 60);

        return $min < 1 ? Text::_('PLG_CONTENT_AVDEB_STATUS_JUST_NOW') : Text::sprintf('PLG_CONTENT_AVDEB_STATUS_MINUTES_AGO', $min);
    }

    protected function getInput()
    {
        try {
            Embeds::reset();
            $s = Embeds::instance()->status();
        } catch (\Throwable $e) {
            return '<div class="alert alert-danger">' . self::e($e->getMessage()) . '</div>';
        }

        if ($s['keyProblem'] === 'publishable') {
            $alert = ['danger', Text::_('PLG_CONTENT_AVDEB_STATUS_PUBLISHABLE')];
        } elseif ($s['keyProblem'] === 'malformed') {
            $alert = ['danger', Text::_('PLG_CONTENT_AVDEB_STATUS_MALFORMED')];
        } elseif ($s['key'] && !$s['key']['ok']) {
            $alert = ['danger', Text::sprintf('PLG_CONTENT_AVDEB_STATUS_REJECTED', self::e($s['key']['error']))];
        } elseif ($s['key'] && $s['key']['ok']) {
            $owner = $s['key']['owner'];
            $alert = ['success', Text::sprintf('PLG_CONTENT_AVDEB_STATUS_CONNECTED', self::e($owner['name'] ?: $owner['type']), self::e($s['ref']))];
        } elseif ($s['ref'] !== '') {
            $alert = ['info', Text::sprintf('PLG_CONTENT_AVDEB_STATUS_REF_ONLY', self::e($s['ref']))];
        } else {
            $alert = ['warning', Text::_('PLG_CONTENT_AVDEB_STATUS_NOT_CONNECTED')];
        }

        $html = '<div class="avdeb-status"><div class="alert alert-' . $alert[0] . '">' . $alert[1] . '</div>';

        if ($s['hasKey']) {
            $html .= '<p class="small text-muted">' . Text::sprintf('PLG_CONTENT_AVDEB_STATUS_SAVED_KEY', '<code>' . self::e($s['maskedKey']) . '</code>') . '</p>';
        }

        $products = $s['preview']['products'];
        $html    .= '<h4 class="h6 mt-3">' . self::e($s['creator'] !== '' ? Text::_('PLG_CONTENT_AVDEB_STATUS_PREVIEW_OWN') : Text::_('PLG_CONTENT_AVDEB_STATUS_PREVIEW_NEWEST')) . '</h4>';

        if (!$products) {
            $html .= '<p class="text-muted">' . self::e($s['preview']['error'] ?? Text::_('PLG_CONTENT_AVDEB_STATUS_NO_PRODUCTS')) . '</p>';
        } else {
            $html .= '<div class="d-flex flex-wrap gap-3 mb-3">';

            foreach ($products as $p) {
                $price = View::formatPrice($p['price'], $p['currency'], Embeds::locale());
                $html .= '<a class="card text-decoration-none" style="width:140px" target="_blank" rel="noopener" href="' . self::e($p['url']) . '">'
                    . ($p['image'] ? '<img class="card-img-top" style="aspect-ratio:1;object-fit:cover" loading="lazy" alt="" src="' . self::e($p['image']) . '">' : '')
                    . '<span class="card-body p-2 small"><span class="d-block text-truncate fw-semibold">' . self::e($p['title']) . '</span>'
                    . ($price ? '<span class="text-muted">' . self::e($price) . '</span>' : '') . '</span></a>';
            }

            $html .= '</div>';
        }

        $st    = $s['stats'];
        $html .= '<p class="small text-muted mb-2">' . Text::sprintf(
            'PLG_CONTENT_AVDEB_STATUS_STATS',
            (int) $st['requests'],
            (int) $st['errors'],
            self::e(self::ago($st['lastFetchAt']))
        ) . ($st['lastError'] ? ' · ' . self::e(Text::sprintf('PLG_CONTENT_AVDEB_STATUS_LAST_ERROR', $st['lastError'], self::ago($st['lastErrorAt']))) : '') . '</p>';

        $url = Uri::base() . 'index.php?option=com_ajax&group=content&plugin=avdeb&format=json&task=refresh&' . Session::getFormToken() . '=1';
        $id  = 'avdeb-refresh-' . substr(md5($this->id), 0, 6);
        $html .= '<button type="button" class="btn btn-secondary btn-sm" id="' . $id . '" data-url="' . self::e($url) . '">'
            . Text::_('PLG_CONTENT_AVDEB_STATUS_REFRESH') . '</button></div>';

        Factory::getApplication()->getDocument()->getWebAssetManager()->addInlineScript(
            "document.getElementById('" . $id . "')?.addEventListener('click',async e=>{const b=e.currentTarget;b.disabled=true;"
            . "try{const r=await fetch(b.dataset.url,{credentials:'same-origin'});const j=await r.json();"
            . "if(!r.ok||j.success===false)throw new Error(j.message||r.status);location.reload();}"
            . "catch(err){b.disabled=false;Joomla.renderMessages({error:[String(err.message||err)]});}});"
        );

        return $html;
    }
}

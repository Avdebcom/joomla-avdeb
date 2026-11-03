<?php

/**
 * @package     AVDEB Products for Joomla
 * @copyright   (C) 2026 AVDEB <https://avdeb.com>
 * @license     GNU General Public License version 2 or later
 */

namespace Avdeb\Plugin\Content\Avdeb\Extension;

use Avdeb\Library\Embeds;
use Avdeb\Library\Shortcodes;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Replaces {avdeb-products …}, {avdeb-product …} and {avdeb-button …} in articles (and anything else
 * that runs content plugins, e.g. Custom modules with "Prepare Content"). Its parameters are the
 * site-wide AVDEB settings used by every AVDEB extension.
 */
final class Avdeb extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare' => 'onContentPrepare',
            'onAjaxAvdeb'      => 'onAjaxAvdeb',
        ];
    }

    public function onContentPrepare(ContentPrepareEvent $event): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        $item = $event->getItem();

        if (!\is_object($item) || !property_exists($item, 'text') || !\is_string($item->text) || !Shortcodes::contains($item->text)) {
            return;
        }

        // Search indexing and smart-search snippets get the text without the tags.
        if ($event->getContext() === 'com_finder.indexer') {
            $item->text = Shortcodes::strip($item->text);

            return;
        }

        $embeds     = Embeds::instance();
        $item->text = Shortcodes::replace($item->text, static function (string $tag, array $attrs) use ($embeds): string {
            try {
                if ($tag === 'button') {
                    return $embeds->button($attrs);
                }

                if ($tag === 'product') {
                    $attrs['mode'] = 'product';
                }

                $level = (int) ($attrs['heading-level'] ?? $attrs['heading_level'] ?? 2);

                return $embeds->render($attrs, $level ?: 2);
            } catch (\Throwable $e) {
                // Never break an article because of the embed.
                return '<!-- AVDEB Products: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ' -->';
            }
        });
    }

    /**
     * Administrator → com_ajax: "Refresh products now" from the plugin settings.
     * index.php?option=com_ajax&group=content&plugin=avdeb&format=json&task=refresh&{token}=1
     */
    public function onAjaxAvdeb(AjaxEvent $event): void
    {
        $app  = $this->getApplication();
        $user = $app->getIdentity();

        if (!$app->isClient('administrator') || !$user || !$user->authorise('core.manage', 'com_plugins') || !Session::checkToken('get')) {
            throw new \RuntimeException('Not allowed', 403);
        }

        if ($app->getInput()->getCmd('task') !== 'refresh') {
            throw new \InvalidArgumentException('Unknown task', 400);
        }

        Embeds::instance()->client()->flush();
        $event->addResult(['ok' => true]);
    }
}

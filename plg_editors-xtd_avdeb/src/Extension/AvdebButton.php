<?php

/**
 * @package     AVDEB Products for Joomla
 * @copyright   (C) 2026 AVDEB <https://avdeb.com>
 * @license     GNU General Public License version 2 or later
 */

namespace Avdeb\Plugin\EditorsXtd\Avdeb\Extension;

use Joomla\CMS\Editor\Button\Button;
use Joomla\CMS\Event\Editor\EditorButtonsSetupEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * "AVDEB" button under the article editor: a dialog that inserts {avdeb-products …} tags.
 */
final class AvdebButton extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return ['onEditorButtonsSetup' => 'onEditorButtonsSetup'];
    }

    public function onEditorButtonsSetup(EditorButtonsSetupEvent $event): void
    {
        if (\in_array($this->_name, $event->getDisabledButtons(), true)) {
            return;
        }

        $user = $this->getApplication()->getIdentity();

        if (!$user || !($user->authorise('core.create', 'com_content') || $user->authorise('core.edit', 'com_content') || $user->authorise('core.edit.own', 'com_content'))) {
            return;
        }

        /** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = $this->getApplication()->getDocument()->getWebAssetManager();

        // Loaded by the button layout as "editor-button.<button name>".
        if (!$wa->assetExists('script', 'editor-button.' . $this->_name)) {
            $wa->registerScript(
                'editor-button.' . $this->_name,
                'plg_editors-xtd_avdeb/editor-button.js',
                ['version' => 'auto'],
                ['type' => 'module'],
                ['editors', 'joomla.dialog']
            );
        }

        foreach (['TITLE', 'KIND', 'KIND_PRODUCTS', 'KIND_PRODUCT', 'KIND_BUTTON', 'LINK', 'LAYOUT', 'LAYOUT_DEFAULT', 'LIMIT', 'LABEL', 'HINT', 'INSERT'] as $key) {
            Text::script('PLG_EDITORS-XTD_AVDEB_' . $key);
        }

        Text::script('JCANCEL');

        $event->getButtonsRegistry()->add(new Button(
            $this->_name,
            [
                'action'  => 'avdeb-insert',
                'text'    => Text::_('PLG_EDITORS-XTD_AVDEB_BUTTON'),
                'icon'    => 'cart',
                'iconSVG' => '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z M4 7.5l8 4.5 8-4.5 M12 12v9" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>',
                'name'    => $this->_type . '_' . $this->_name,
            ]
        ));
    }
}

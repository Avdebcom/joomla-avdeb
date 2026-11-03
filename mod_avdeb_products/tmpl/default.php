<?php

/**
 * @package     AVDEB Products for Joomla
 * @copyright   (C) 2026 AVDEB <https://avdeb.com>
 * @license     GNU General Public License version 2 or later
 *
 * Template overrides: copy to templates/YOUR_TEMPLATE/html/mod_avdeb_products/default.php.
 * $avdebHtml is ready, escaped markup (see Avdeb\Library\Renderer).
 */

\defined('_JEXEC') or die;

/** @var string $avdebHtml */
/** @var \Joomla\Registry\Registry $params */

$suffix = htmlspecialchars((string) $params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8');
?>
<div class="mod-avdeb-products<?php echo $suffix !== '' ? ' ' . $suffix : ''; ?>">
	<?php echo $avdebHtml; ?>
</div>

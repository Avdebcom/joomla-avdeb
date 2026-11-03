<?php

/**
 * @package     AVDEB Products for Joomla
 * @copyright   (C) 2026 AVDEB <https://avdeb.com>
 * @license     GNU General Public License version 2 or later
 */

namespace Avdeb\Library;

use Avdeb\Library\Store\ArrayStore;
use Avdeb\Library\Store\JoomlaCacheStore;
use Avdeb\Library\Store\StoreInterface;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The one entry point the content plugin, the module and the admin status field use: settings,
 * a cached client, rendering and assets. Identical embeds on one page share a single lookup.
 */
final class Embeds
{
    private static ?self $instance = null;

    private ?Client $client = null;

    /** @var array<string, array> products per query within this request */
    private array $memo = [];

    private bool $assetsLoaded = false;

    public function __construct(public readonly array $settings, private ?StoreInterface $store = null)
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self(Settings::fromParams(self::pluginParams()));
    }

    /** Forget the instance (after settings were saved). */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * The "Content - AVDEB Products" plugin parameters — read from the database so the module and
     * the admin field work even while the content plugin is disabled.
     */
    public static function pluginParams(): array
    {
        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('content'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('avdeb'));

            return (new Registry((string) $db->setQuery($query)->loadResult()))->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    public function client(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        if (!$this->store) {
            try {
                $this->store = new JoomlaCacheStore();
            } catch (\Throwable) {
                $this->store = new ArrayStore();
            }
        }

        $http = static function (string $url, array $headers, int $timeout): array {
            $response = HttpFactory::getHttp()->get($url, $headers, $timeout);

            return ['status' => $response->getStatusCode(), 'body' => (string) $response->getBody()];
        };

        return $this->client = new Client(
            $this->store,
            $http,
            $this->settings['apiKey'],
            $this->settings['refCode'],
            $this->settings['cacheMinutes']
        );
    }

    public static function locale(): string
    {
        try {
            return Factory::getApplication()->getLanguage()->getTag();
        } catch (\Throwable) {
            return 'en-US';
        }
    }

    /** Products for a query, de-duplicated within the request. */
    public function lookup(array $query): array
    {
        return $this->memo[Catalog::queryKey($query)] ??= $this->client()->products($query);
    }

    /** {avdeb-products} / {avdeb-product} / the module. */
    public function render(array $attrs, int $headingLevel = 2): string
    {
        $o = Options::parse($attrs, $this->settings['display']);

        if ($o['mode'] === 'product' && Options::productHandle($o['value']) === '') {
            return '<!-- AVDEB Product: add a product link, e.g. {avdeb-product https://avdeb.com/store/cat-sticker/} -->';
        }

        $query = Catalog::sanitizeQuery(Options::queryFor($o), Catalog::maxLimit($this->settings['apiKey'] !== ''));
        $out   = $this->lookup($query);

        $this->useAssets();

        return Renderer::embed([
            'products'   => $out['products'],
            'ref'        => $out['ref'],
            'query'      => $query,
            'disclosure' => Settings::disclosureFor($this->settings, $out['ref']),
        ], $o, self::locale(), $headingLevel);
    }

    /** {avdeb-button}. */
    public function button(array $attrs): string
    {
        $this->useAssets();

        return Renderer::button(Options::button($attrs), $this->client()->ref(), $this->settings['display']);
    }

    public function useAssets(): void
    {
        if ($this->assetsLoaded) {
            return;
        }

        try {
            $doc = Factory::getApplication()->getDocument();

            if ($doc instanceof HtmlDocument) {
                $wa = $doc->getWebAssetManager();
                $wa->getRegistry()->addExtensionRegistryFile('lib_avdeb');
                $wa->usePreset('lib_avdeb.embed');
                $this->assetsLoaded = true;
            }
        } catch (\Throwable) {
            // No HTML document (feeds, JSON): markup still works without the carousel arrows.
        }
    }

    /** Everything the admin status panel shows. Never contains the secret key. */
    public function status(): array
    {
        $client  = $this->client();
        $key     = $client->keyInfo();
        $creator = $key && $key['ok'] ? $key['owner']['creatorSlug'] : '';
        $preview = $client->products(Catalog::sanitizeQuery(['creator' => $creator, 'limit' => 4], Catalog::maxLimit($this->settings['apiKey'] !== '')));

        return [
            'hasKey'     => $this->settings['rawApiKey'] !== '',
            'maskedKey'  => Settings::maskKey($this->settings['rawApiKey']),
            'keyProblem' => $this->settings['keyProblem'],
            'key'        => $key,
            'ref'        => $client->ref(),
            'stats'      => $client->stats(),
            'preview'    => $preview,
            'creator'    => $creator,
        ];
    }
}

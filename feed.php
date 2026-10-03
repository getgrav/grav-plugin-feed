<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Data;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Plugin;
use Grav\Common\Uri;
use Grav\Common\Utils;
use RocketTheme\Toolbox\Event\Event;
use Twig\TwigFunction;

class FeedPlugin extends Plugin
{
    /**
     * @var bool
     */
    protected $active = false;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var array
     */
    protected $feed_config;

    /**
     * @var array
     */
    protected $valid_types = array('rss','atom');

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => [
                ['autoload', 100000],
                ['onPluginsInitialized', 0],
            ],
            'onBlueprintCreated' => ['onBlueprintCreated', 0],
            'onPageHeaders' => ['onPageHeaders', 0]
        ];
    }

    /**
     * [onPluginsInitialized:100000] Composer autoload.
     *
     * @return ClassLoader
     */
    public function autoload()
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Activate feed plugin only if feed was requested for the current page.
     *
     * Also disables debugger.
     */
    public function onPluginsInitialized()
    {
        if ($this->isAdmin()) {
            return;
        }

        $this->feed_config = (array) $this->config->get('plugins.feed');

        if ($this->feed_config['enable_json_feed']) {
            $this->valid_types[] = 'json';
        }

        /** @var Uri $uri */
        $uri = $this->grav['uri'];
        $this->type = $uri->extension();

        if ($this->type && in_array($this->type, $this->valid_types)) {
            $this->enable([
                'onPageInitialized' => ['onPageInitialized', 0],
                'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
                'onTwigExtensions' => ['onTwigExtensions', 0],
            ]);
        }
    }

    /**
     * Initialize feed configuration.
     */
    public function onPageInitialized()
    {
        $page = $this->grav['page'];

        // Overwrite regular content with feed config, so you can influence the collection processing with feed config
        if (property_exists($page->header(), 'content') || property_exists($page->header(), 'feed')) {
            // Set default template.
            $template = "feed";

            if (property_exists($page->header(), 'feed')) {
                $this->feed_config = array_merge($this->feed_config, $page->header()->feed);
                // Look for feed type override,
                if (isset($this->feed_config['template'][$this->type])) {
                    $template = $this->feed_config['template'][$this->type];
                }
            }

            if (property_exists($page->header(), 'content')) {
                $page->header()->content = array_merge($page->header()->content ?? [], $this->feed_config);
                $this->enable([
                    'onCollectionProcessed' => ['onCollectionProcessed', 0],
                ]);
            }

            // Set page template.
            $this->grav['twig']->template = $template . "." . $this->type . '.twig';

        }
    }

    /**
     * Feed consists of all sub-pages.
     *
     * @param Event $event
     */
    public function onCollectionProcessed(Event $event)
    {
        /** @var Collection $collection */
        $collection = $event['collection']->nonModular();

        foreach ($collection as $page) {
            $header = $page->header();
            if ($this->feed_config['skip_behavior']) {
                // Implicit skip: exclude all, include if skip is set to false
                if (!isset($header->feed['skip']) || !empty($header->feed['skip'])) {
                    $collection->remove($page);
                }
            }
            else {
                // Explicit skip (default): include all, exclude if skip is set to true
                if (isset($header->feed) && !empty($header->feed['skip'])) {
                    $collection->remove($page);
                }
            }
        }

        $this->grav->fireEvent('onFeedCollectionProcessed', $event);
    }

    /**
     * Give the feed templates the `feed_item_content()` function.
     */
    public function onTwigExtensions()
    {
        $this->grav['twig']->twig()->addFunction(new TwigFunction('feed_item_content', [$this, 'feedItemContent']));
    }

    /**
     * Fire `onFeedItemContent` for one feed item and return the HTML to print.
     *
     * Listeners get the item's full content and may replace it. The content is cut to the
     * feed's length afterwards, unless a listener set `truncate` to false.
     *
     * @param PageInterface $page
     * @param string $format 'rss', 'atom' or 'json'
     * @param int|null $length
     * @return string
     */
    public function feedItemContent(PageInterface $page, string $format, $length = null)
    {
        $event = new Event([
            'page' => $page,
            'content' => (string) $page->content(),
            'format' => $format,
            'truncate' => true,
        ]);
        $this->grav->fireEvent('onFeedItemContent', $event);

        $content = (string) $event['content'];

        return $event['truncate'] ? Utils::safeTruncateHtml($content, $length) : $content;
    }

    /**
     * Add current directory to twig lookup paths.
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }


    /**
     * Extend page blueprints with feed configuration options.
     *
     * @param Event $event
     */
    public function onBlueprintCreated(Event $event)
    {
        static $inEvent = false;

        /** @var Data\Blueprint $blueprint */
        $blueprint = $event['blueprint'];
        $form = $blueprint->form();

        $blog_tab_exists = isset($form['fields']['tabs']['fields']['blog']);

        if (!$inEvent && $blog_tab_exists) {
            $inEvent = true;
            $blueprints = new Data\Blueprints(__DIR__ . '/blueprints/');
            $extends = $blueprints->get('feed');
            $blueprint->extend($extends, true);
            $inEvent = false;
        }
    }

    /**
     * Force UTF-8 char-set encoding via `Content-Type` header
     *
     * @param Event $e
     * @return void
     */
    public function onPageHeaders(Event $e)
    {
        $headers = $e['headers'];
        $content_type = $headers->{'Content-Type'} ?? null;
        // Grav 2.1 already sends a charset on its Markdown output, and a site can
        // set one in media.yaml, so only add it when the header has none.
        if ($content_type && stripos((string)$content_type, 'charset=') === false) {
            $headers->{'Content-Type'} = "$content_type; charset=utf-8";
        }
    }
}

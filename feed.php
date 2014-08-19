<?php
namespace Grav\Plugin;

use Grav\Common\Data;
use Grav\Common\Page\Collection;
use Grav\Common\Plugin;
use Grav\Common\Uri;
use Grav\Common\Page\Page;
use Grav\Component\EventDispatcher\Event;

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
    public static function getSubscribedEvents() {
        return [
            'onAfterInitPlugins' => ['onAfterInitPlugins', 0],
            'onCreateBlueprint' => ['onCreateBlueprint', 0]
        ];
    }

    /**
     * Activate feed plugin only if feed was requested for the current page.
     *
     * Also disables debugger.
     */
    public function onAfterInitPlugins()
    {
        /** @var Uri $uri */
        $uri = $this->grav['uri'];
        $this->type = $uri->extension();

        if ($this->type && in_array($this->type, $this->valid_types)) {
            $this->config->set('system.debugger.enabled', false);

            $this->enable([
                'onAfterGetPage' => ['onAfterGetPage', 0],
                'onAfterCollectionProcessed' => ['onAfterCollectionProcessed', 0],
                'onAfterTwigTemplatesPaths' => ['onAfterTwigTemplatesPaths', 0],
                'onAfterTwigSiteVars' => ['onAfterTwigSiteVars', 0],
                'onCreateBlueprint' => ['onCreateBlueprint', 0]
            ]);
        }
    }

    /**
     * Initialize feed configuration.
     */
    public function onAfterGetPage()
    {
        $defaults = (array) $this->config->get('plugins.feed');

        /** @var Page $page */
        $page = $this->grav['page'];
        if (isset($page->header()->feed)) {
            $this->feed_config = array_merge($defaults, $page->header()->feed);
        } else {
            $this->feed_config = $defaults;
        }
    }

    /**
     * Feed consists of all sub-pages.
     *
     * @param Event $event
     */
    public function onAfterCollectionProcessed(Event $event)
    {
        /** @var Collection $collection */
        $collection = $event['collection'];
        $collection->setParams(array_merge($collection->params(), $this->feed_config));;
    }

    /**
     * Add current directory to twig lookup paths.
     */
    public function onAfterTwigTemplatesPaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Set needed variables to display the feed.
     */
    public function onAfterTwigSiteVars()
    {
        $twig = $this->grav['twig'];
        $twig->template = 'feed.' . $this->type . '.twig';
    }

    /**
     * Extend page blueprints with feed configuration options.
     *
     * @param Event $event
     */
    public function onCreateBlueprint(Event $event)
    {
        static $inEvent = false;

        /** @var Data\Blueprint $blueprint */
        $blueprint = $event['blueprint'];
        if (!$inEvent && $blueprint->name == 'blog_list') {
            $inEvent = true;
            $blueprints = new Data\Blueprints(__DIR__ . '/blueprints/');
            $extends = $blueprints->get('feed');
            $blueprint->extend($extends, true);
            $inEvent = false;
        }
    }
}

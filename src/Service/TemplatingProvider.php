<?php
/**
 * Joomla! Help Site
 *
 * @copyright  Copyright (C) 2016 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 *
 * Portions of this code are derived from the previous help screen proxy component,
 * please see https://github.com/joomla-projects/help-proxy for attribution
 */

namespace Joomla\Help\Service;

use Joomla\Application\AbstractApplication;
use Joomla\Cache\Item\Item;
use Joomla\DI\Container;
use Joomla\DI\ContainerAwareInterface;
use Joomla\DI\ContainerAwareTrait;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Http\Http;
use Joomla\Renderer\PlatesRenderer;
use Joomla\Renderer\RendererInterface;
use League\Plates\Engine;
use League\Plates\Extension\Asset;

/**
 * Templating service provider
 */
class TemplatingProvider implements ServiceProviderInterface, ContainerAwareInterface
{
	use ContainerAwareTrait;

	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 */
	public function register(Container $container)
	{
		$this->setContainer($container);

		$container->share(
			RendererInterface::class,
			function (Container $container)
			{
				$engine = new Engine(JPATH_ROOT . '/templates');
				$engine->addFolder('partials', JPATH_ROOT . '/templates/partials');

				// Add extensions to the renderer
				$engine->loadExtension(
					new Asset(JPATH_ROOT . '/www')
				);

				// Add functions to the renderer
				$engine->registerFunction('current_url', [$this, 'getCurrentUrl']);
				$engine->registerFunction('cdn_menu', [$this, 'getCdnMenu']);
				$engine->registerFunction('cdn_footer', [$this, 'getCdnFooter']);

				return new PlatesRenderer($engine);
			},
			true
		);
	}

	/**
	 * Retrieve the template footer contents from the CDN.
	 *
	 * @return  string
	 */
	public function getCdnFooter() : string
	{
		/** @var \Psr\Cache\CacheItemPoolInterface $cache */
		$cache = $this->getContainer()->get('cache');

		$key = md5(get_class($this) . 'cdn_footer');

		$remoteRequest = function () use ($cache, $key)
		{
			try
			{
				/** @var Http $http */
				$http = $this->getContainer()->get(Http::class);

				// Set a very short timeout to try and not bring the site down
				$response = $http->get('https://cdn.joomla.org/template/renderer.php?section=footer', [], 2);

				if ($response->getStatusCode() !== 200)
				{
					return 'Could not load template section.';
				}

				$body = (string) $response->getBody();

				// Remove the login link
				$body = str_replace("\t\t<li><a href=\"%loginroute%\">%logintext%</a></li>\n", '', $body);

				// Replace the placeholders
				$body = strtr(
					$body,
					[
						'%reportroute%' => 'https://github.com/joomla/joomla-websites/issues/new?title=[jhelp]%20&amp;body=Please%20describe%20the%20problem%20or%20your%20issue',
						'%currentyear%' => date('Y'),
					]
				);

				$item = (new Item($key, $this->getContainer()->get(AbstractApplication::class)->get('cache.lifetime', 900)))
					->set($body);

				$cache->save($item);

				return $body;
			}
			catch (\RuntimeException $e)
			{
				return 'Could not load template section.';
			}
		};

		if ($cache->hasItem($key))
		{
			$item = $cache->getItem($key);

			// Make sure we got a hit on the item, otherwise we'll have to re-cache
			if ($item->isHit())
			{
				$body = $item->get();
			}
			else
			{
				$body = $remoteRequest();
			}
		}
		else
		{
			$body = $remoteRequest();
		}

		return $body;
	}

	/**
	 * Retrieve the template mega menu from the CDN.
	 *
	 * @return  string
	 */
	public function getCdnMenu() : string
	{
		/** @var \Psr\Cache\CacheItemPoolInterface $cache */
		$cache = $this->getContainer()->get('cache');

		$key = md5(get_class($this) . 'cdn_menu');

		$remoteRequest = function () use ($cache, $key)
		{
			try
			{
				/** @var Http $http */
				$http = $this->getContainer()->get(Http::class);

				// Set a very short timeout to try and not bring the site down
				$response = $http->get('https://cdn.joomla.org/template/renderer.php?section=menu', [], 2);

				if ($response->getStatusCode() !== 200)
				{
					return 'Could not load template section.';
				}

				$body = (string) $response->getBody();

				// Remove the search module
				$body = str_replace(
					"\t<div id=\"nav-search\" class=\"navbar-search pull-right\">\n\t\t<jdoc:include type=\"modules\" name=\"position-0\" style=\"none\" />\n\t</div>\n",
					'',
					$body
				);

				$item = (new Item($key, $this->getContainer()->get(AbstractApplication::class)->get('cache.lifetime', 900)))
					->set($body);

				$cache->save($item);

				return $body;
			}
			catch (\RuntimeException $e)
			{
				return 'Could not load template section.';
			}
		};

		if ($cache->hasItem($key))
		{
			$item = $cache->getItem($key);

			// Make sure we got a hit on the item, otherwise we'll have to re-cache
			if ($item->isHit())
			{
				$body = $item->get();
			}
			else
			{
				$body = $remoteRequest();
			}
		}
		else
		{
			$body = $remoteRequest();
		}

		return $body;
	}

	/**
	 * Retrieve the current URL.
	 *
	 * @return  string
	 */
	public function getCurrentUrl() : string
	{
		return $this->getContainer()->get(AbstractApplication::class)->get('uri.request');
	}
}

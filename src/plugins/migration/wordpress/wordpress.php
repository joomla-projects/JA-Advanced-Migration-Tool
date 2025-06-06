<?php

defined('_JEXEC') or die;

use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Plugin\CMSPlugin;

class PlgMigrationWordpress extends CMSPlugin
{
    public function onMigrationConvert(AbstractEvent $event)
    {
        $args = $event->getArguments();
        $sourceCms = $args['sourceCms'] ?? null;
        $filePath = $args['filePath'] ?? null;

        if ($sourceCms !== 'wordpress' || empty($filePath)) {
            return;
        }

        $xmlContent = file_get_contents($filePath);
        if (empty($xmlContent)) {
            return;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);

        if ($xml === false) {
            return;
        }

        $itemList = [
            '@context' => 'http://schema.org',
            '@type'    => 'ItemList',
            'itemListElement' => [],
        ];

        foreach ($xml->channel->item as $item) {
            $wp_ns = $item->children('wp', true);
            $postType = (string) $wp_ns->post_type;

            if (($postType !== 'post' && $postType !== 'page') || (string) $wp_ns->status !== 'publish') {
                continue;
            }

            $dc_ns      = $item->children('dc', true);
            $content_ns = $item->children('content', true);

            $tags = [];
            $categories = [];
            foreach ($item->category as $category) {
                $domain = (string) $category['domain'];
                if ($domain === 'post_tag') {
                    $tags[] = (string) $category;
                }
                if ($domain === 'category') {
                    $categories[] = (string) $category;
                }
            }

            $article = [
                '@type'         => 'Article',
                'headline'      => (string) $item->title,
                'articleBody'   => (string) $content_ns->encoded,
                'datePublished' => (string) $wp_ns->post_date,
                'author'        => [
                    '@type' => 'Person',
                    'name'  => (string) $dc_ns->creator,
                ],
                'keywords'      => implode(', ', $tags),
                'articleSection' => $categories,
            ];

            $itemList['itemListElement'][] = [
                '@type'    => 'ListItem',
                'position' => count($itemList['itemListElement']) + 1,
                'item'     => $article,
            ];
        }

        $event->addResult(json_encode($itemList, JSON_PRETTY_PRINT));
    }
} 
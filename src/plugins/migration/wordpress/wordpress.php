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

        // Register namespaces
        $namespaces = $xml->getNamespaces(true);
        $wp = $namespaces['wp'] ?? 'wp';
        $content = $namespaces['content'] ?? 'content';
        $dc = $namespaces['dc'] ?? 'dc';

        $itemList = [];
        $position = 1;

        foreach ($xml->channel->item as $item) {
            $wp_ns = $item->children($wp);
            $postType = (string)$wp_ns->post_type;

            // Only process posts and pages
            if (($postType !== 'post' && $postType !== 'page') || (string)$wp_ns->status !== 'publish') {
                continue;
            }

            // Get categories and tags
            $categories = [];
            $tags = [];
            foreach ($item->category as $category) {
                $domain = (string)$category['domain'];
                if ($domain === 'category') {
                    $categories[] = (string)$category;
                } elseif ($domain === 'post_tag') {
                    $tags[] = (string)$category;
                }
            }

            // Article body with replaced image URLs
            $contentEncoded = $item->children($content)->encoded;
            $articleBody = (string)$contentEncoded;

            // Replace local image URLs
            foreach ($wp_ns->attachment_url as $attachment) {
                $localUrl = 'http://localhost/wp/wp-content/uploads/';
                $attachmentUrl = (string)$attachment;
                $articleBody = str_replace($localUrl, $attachmentUrl, $articleBody);
            }

            // Convert date to ISO 8601
            $pubDate = new DateTime((string)$item->pubDate);
            $isoDate = $pubDate->format(DateTime::ATOM);

            // Build article
            $article = [
                '@type' => 'Article',
                'headline' => (string)$item->title,
                'articleSection' => $categories ?: ['Uncategorized'],
                'keywords' => implode(', ', $tags),
                'articleBody' => $articleBody,
                'datePublished' => $isoDate,
                'author' => [
                    '@type' => 'Person',
                    'name' => (string)$item->children($dc)->creator
                ]
            ];

            // Add to list
            $itemList[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'item' => $article
            ];
        }

        $final = [
            '@context' => 'http://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => $itemList
        ];

        $event->addResult(json_encode($final, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

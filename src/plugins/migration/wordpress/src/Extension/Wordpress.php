<?php

namespace Joomla\Plugin\Migration\Wordpress\Extension;

defined('_JEXEC') or die;

use DateTime;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * WordPress Migration Plugin
 *
 * @since  1.0.0
 */
class Wordpress extends CMSPlugin implements SubscriberInterface
{
    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onMigrationConvert' => 'onMigrationConvert',
        ];
    }

    /**
     * Handles the onMigrationConvert event.
     *
     * @param   Event  $event  The event object.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onMigrationConvert(Event $event)
    {
        // Use this format to get the arguments for both Joomla 4 and Joomla 5
        [$sourceCms, $filePath] = array_values($event->getArguments());

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

        // Validate that this is a WordPress export file
        if (!isset($xml->channel)) {
            return;
        }

        // Register namespaces
        $namespaces = $xml->getNamespaces(true);
        $wp      = $namespaces['wp']      ?? 'wp';
        $content = $namespaces['content'] ?? 'content';
        $dc      = $namespaces['dc']      ?? 'dc';

        // Map author login to email
        $authorEmailMap = [];
        foreach ($xml->channel->children($wp)->author as $authorNode) {
            $login = (string) $authorNode->children($wp)->author_login;
            $email = (string) $authorNode->children($wp)->author_email;
            $authorEmailMap[$login] = $email;
        }

        // Build full map of all tags
        $tagMetaMap = [];
        foreach ($xml->channel->children($wp)->tag as $tagNode) {
            $tagName = (string) $tagNode->children($wp)->tag_name;
            $tagSlug = (string) $tagNode->children($wp)->tag_slug;
            $tagDesc = (string) $tagNode->children($wp)->tag_description;

            $tagMetaMap[$tagName] = [
                'name'        => $tagName,
                'slug'        => $tagSlug,
                'description' => $tagDesc,
            ];
        }
        $allTags = array_values($tagMetaMap);

        $itemList   = [];
        $mediaItems = [];
        $position   = 1;

        // Process each item
        foreach ($xml->channel->item as $item) {
            $wp_ns = $item->children($wp);

            // Handle media attachments separately
            if ((string) $wp_ns->post_type === 'attachment') {
                $mediaItem = [
                    '@type'        => 'MediaObject',
                    'name'         => (string) $item->title,
                    'url'          => (string) $wp_ns->attachment_url,
                    'description'  => (string) $item->description,
                    'mediaType'    => (string) $item->children($dc)->format ?? null,
                    'dateUploaded' => (new DateTime((string) $item->pubDate))->format(DateTime::ATOM),
                ];
                $mediaItems[] = $mediaItem;
                continue;
            }

            // Only published posts and pages
            $postType = (string) $wp_ns->post_type;
            if (
                !in_array($postType, ['post', 'page'], true)
                || (string) $wp_ns->status !== 'publish'
            ) {
                continue;
            }

            // Collect categories and tag slugs for this item
            $categories   = [];
            $postTagSlugs = [];
            foreach ($item->category as $category) {
                $domain   = (string) $category['domain'];
                $value    = (string) $category;
                $nicename = (string) $category['nicename'];

                if ($domain === 'category') {
                    $categories[] = $value;
                } elseif ($domain === 'post_tag') {
                    $postTagSlugs[] = $nicename;
                }
            }

            // Article body and URL replacements
            $contentEncoded = $item->children($content)->encoded;
            $articleBody    = (string) $contentEncoded;
            foreach ($wp_ns->attachment_url as $attachment) {
                $localUrl    = 'http://localhost/wp/wp-content/uploads/';
                $attachmentUrl = (string) $attachment;
                $articleBody = str_replace($localUrl, $attachmentUrl, $articleBody);
            }

            // Date published in ISO 8601
            $pubDate = new DateTime((string) $item->pubDate);
            $isoDate = $pubDate->format(DateTime::ATOM);

            // Author info
            $authorLogin = (string) $item->children($dc)->creator;
            $authorEmail = $authorEmailMap[$authorLogin] ?? null;

            // Custom fields (exclude _ prefixed)
            $customFields = [];
            foreach ($wp_ns->postmeta as $meta) {
                $key   = (string) $meta->meta_key;
                $value = (string) $meta->meta_value;
                if (strpos($key, '_') === 0) {
                    continue;
                }
                $customFields[$key] = $value;
            }

            // Build article object
            $article = [
                '@type'         => 'Article',
                'headline'      => (string) $item->title,
                'articleSection' => $categories ?: ['Uncategorized'],
                'tags'          => $postTagSlugs,
                'articleBody'   => $articleBody,
                'datePublished' => $isoDate,
                'author'        => [
                    '@type' => 'Person',
                    'name'  => $authorLogin,
                    'email' => $authorEmail,
                ],
                'customFields'  => $customFields,
            ];

            $itemList[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'item'     => $article,
            ];
        }

        // Final JSON structure
        $final = [
            '@context'       => 'http://schema.org',
            '@type'          => 'ItemList',
            'allTags'        => $allTags,
            'itemListElement' => $itemList,
            'mediaItems'     => $mediaItems,
        ];

        // Return result using the modern approach
        $event->addResult(json_encode($final, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

# How to Build Migration Plugins

Want to add support for your favorite CMS? This guide shows you how to create plugins that convert any CMS data into Joomla format.

## How It Works

Think of migration plugins like translators. When someone uploads a file:

1. **Upload**: User uploads an export file from their old CMS
2. **Detection**: Your plugin checks "Hey, is this my type of file?"  
3. **Translation**: Your plugin converts the data to Joomla format
4. **Import**: The main system imports everything into Joomla

## Building a Plugin

For complex CMS formats like WordPress XML, you need to parse and convert the data. Here's how:

### Step 1: Create the Plugin Files

Create this folder structure:
```
src/plugins/migration/mycms/
├── mycms.xml              # Plugin Manifest File
├── script.php             # Installer script for plugin
├── src/Extension/
│   └── Mycms.php         # Main plugin code
├── services/
│   └── provider.php      # DI configuration
└── language/en-GB/       # Contains localization files
```

### Step 2: Write the Main Plugin

**File: `src/Extension/Mycms.php`**

```php
<?php
namespace My\Plugin\Migration\Mycms\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

class Mycms extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onMigrationConvert' => 'onMigrationConvert'];
    }

    public function onMigrationConvert(Event $event)
    {
        [$sourceCms, $filePath] = array_values($event->getArguments());

        // Only handle our CMS
        if ($sourceCms !== 'mycms') {
            return;
        }

        try {
            // Read and convert the file
            $convertedData = $this->convertMyCmsData($filePath);
            $event->addResult(json_encode($convertedData));
        } catch (\Exception $e) {
            // Handle errors gracefully
            $this->getApplication()->enqueueMessage('Error: ' . $e->getMessage(), 'error');
        }
    }

    private function convertMyCmsData(string $filePath): array
    {
        $content = file_get_contents($filePath);
        
        // Parse your CMS format (XML, CSV, etc.)
        $sourceData = $this->parseFile($content);
        
        // Convert to Joomla format
        return [
            'users' => $this->convertUsers($sourceData['users'] ?? []),
            'taxonomies' => [
                'category' => $this->convertCategories($sourceData['categories'] ?? []),
                'post_tag' => $this->convertTags($sourceData['tags'] ?? [])
            ],
            'post_types' => [
                'post' => $this->convertPosts($sourceData['posts'] ?? []),
                'page' => $this->convertPages($sourceData['pages'] ?? [])
            ]
        ];
    }

    private function convertUsers(array $users): array
    {
        $result = [];
        foreach ($users as $user) {
            $result[] = [
                'ID' => $user['id'],
                'user_login' => $user['username'],
                'user_email' => $user['email'],
                'display_name' => $user['name'],
                'user_registered' => $user['join_date']
            ];
        }
        return $result;
    }

    private function convertPosts(array $posts): array
    {
        $result = [];
        foreach ($posts as $post) {
            $result[] = [
                'ID' => $post['id'],
                'post_title' => $post['title'],
                'post_content' => $post['content'],
                'post_status' => $post['published'] ? 'publish' : 'draft',
                'post_author' => $post['author_id'],
                'post_date' => $post['created_date'],
                'categories' => $post['category_ids'] ?? [],
                'tags' => $post['tag_ids'] ?? []
            ];
        }
        return $result;
    }

    // Add more conversion methods as needed...
}
```

## The Data Format Joomla Expects

Your plugin should output JSON in this format:

```json
{
  "users": [
    {
      "ID": "1",
      "user_login": "admin", 
      "user_email": "admin@site.com",
      "display_name": "Administrator"
    }
  ],
  "taxonomies": {
    "category": [
      {
        "term_id": "1",
        "name": "Technology",
        "slug": "tech",
        "description": "Tech posts"
      }
    ],
    "post_tag": [
      {
        "term_id": "1", 
        "name": "News",
        "slug": "news"
      }
    ]
  },
  "post_types": {
    "post": [
      {
        "ID": "1",
        "post_title": "My First Post",
        "post_content": "Hello world!",
        "post_status": "publish",
        "post_author": "1",
        "post_date": "2024-01-01 12:00:00",
        "categories": ["1"],
        "tags": ["1"]
      }
    ]
  }
}
```

## Installation Files

### Plugin Manifest (`mycms.xml`)
```xml
<?xml version="1.0" encoding="utf-8"?>
<extension method="upgrade" type="plugin" group="migration">
    <name>PLG_MIGRATION_MYCMS</name>
    <version>1.0.0</version>
    <description>Migrate from MyCMS to Joomla</description>
    <author>Your Name</author>
    <namespace path="src">My\Plugin\Migration\Mycms</namespace>
    <files>
        <folder plugin="mycms">services</folder>
        <folder>src</folder>
        <folder>language</folder>
    </files>
    <scriptfile>script.php</scriptfile>
</extension>
```

### Service Provider (`services/provider.php`)
```php
<?php
use Joomla\CMS\Extension\PluginInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use My\Plugin\Migration\Mycms\Extension\Mycms;

return new class() implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container->set(PluginInterface::class, function (Container $container) {
            $config = (array) PluginHelper::getPlugin('migration', 'mycms');
            $plugin = new Mycms($container->get(DispatcherInterface::class), $config);
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
```

## Testing Your Plugin

1. **Package** your plugin files into a ZIP
2. **Install** via Joomla Extension Manager  
3. **Enable** the plugin in Plugin Manager
4. **Test** with sample export files

Create test data like:
```json
{
  "users": [{"id": "1", "username": "test", "email": "test@example.com"}],
  "posts": [{"id": "1", "title": "Test Post", "content": "Hello!"}]
}
```

## Troubleshooting

**Plugin not working?**
- Check it's enabled in Plugin Manager
- Clear Joomla cache
- Check error logs in System → Global Configuration → Logging

**Data not importing?**
- Verify your JSON format matches the expected structure
- Check for PHP errors in the conversion process
- Test with small sample files first

**Memory issues?**
- Process large files in chunks
- Increase PHP memory limit if needed

## Need Help?

- Look at existing plugin in `src/plugins/migration/`
- Check Joomla plugin documentation
- Ask questions in the project GitHub issues

---
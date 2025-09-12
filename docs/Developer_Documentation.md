# Advanced Migration Tool - Developer Documentation

**Comprehensive technical documentation for developers extending and contributing to the  Advanced Migration Tool.**

## Table of Contents
1. [Project Overview](#project-overview)
2. [Architecture](#architecture)
3. [Project Structure](#project-structure)
4. [Core Components](#core-components)
5. [Plugin System](#plugin-system)
6. [Local Development Setup](#local-development-setup)
7. [Extension Development](#extension-development)
8. [Plugin Development Guide](./Plugin_Development_Guide.md)

## Project Overview

The **Advanced Migration Tool** is a Joomla extension that helps website owners migrate their content from other content management systems (like WordPress) into Joomla. Built on Joomla 5+ framework, it follows modern PHP practices and Joomla development standards.

### Key Features
- **Multi-platform Support**: WordPress, JSON, and extensible plugin architecture
- **Media Migration**: FTP/FTPS/SFTP and ZIP upload support
- **Batch Processing**: Optimized for large content migrations
- **Progress Tracking**: Real-time migration status updates
- **Transaction Safety**: Database rollback on failure
- **Extensible Architecture**: Plugin-based migration system

### Technology Stack
- **Backend**: PHP 8.1+, Joomla 5+ Framework
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Database**: MySQL/MariaDB with Joomla ORM
- **External Libraries**: phpseclib for secure connections
- **Testing**: PHPUnit, Cypress E2E

> **Note**: For user-facing features and installation instructions, see [User Documentation](./User_Documentation.md). For testing details, refer to [Testing Documentation](./Testing_Documentation.md).

## Architecture

### Design Patterns
- **MVC (Model-View-Controller)**: Separation of concerns
- **Plugin Architecture**: Extensible migration system
- **Event-Driven**: Joomla event system integration
- **Factory Pattern**: Component instantiation
- **Service Provider**: Dependency injection

### Data Flow
```
User Upload → Plugin Conversion → Data Processing → Joomla Database 
```

1. **Upload Phase**: File validation and temporary storage
2. **Conversion Phase**: Plugin-specific data transformation
3. **Processing Phase**: Joomla-compatible data structure creation
4. **Storage Phase**: Database transactions with rollback support
5. **Media Phase**: Asset migration and URL rewriting

## Project Structure

```
JA-Advanced-Migration-Tool/
├── src/                          # Source code
│   ├── component/                # Main Joomla component
│   │   ├── admin/               # Administrator interface
│   │   │   ├── src/            # PHP classes
│   │   │   │   ├── Controller/ # MVC Controllers
│   │   │   │   ├── Model/      # Business logic models
│   │   │   │   ├── View/       # View layer
│   │   │   │   ├── Table/      # Database tables
│   │   │   │   ├── Event/      # Event classes
│   │   │   │   └── Extension/  # Component extension class
│   │   │   ├── tmpl/           # Template files
│   │   │   ├── language/       # Localization files
│   │   │   ├── forms/          # XML form definitions
│   │   │   ├── sql/            # Database schemas
│   │   │   └── services/       # DI container setup
│   │   ├── media/              # Static assets
│   │   └── cmsmigrator.xml     # Component manifest
│   └── plugins/                 # Migration plugins
│       └── migration/          # Plugin group
│           ├── wordpress/      # WordPress migration
│           └── json/           # JSON migration
├── tests/                       # Test suites
│   ├── unit/                   # PHPUnit tests
│   ├── e2e/                    # Cypress E2E tests
│   └── support/                # Test utilities
├── docs/                        # Documentation
├── media/                       # Runtime media storage
├── cypress/                     # E2E test configurations
├── vendor/                      # Composer dependencies
├── composer.json               # PHP dependencies
├── package.json                # Node.js dependencies
└── phpunit.xml.dist           # PHPUnit configuration
```

## Core Components

### 1. ImportController (`src/component/admin/src/Controller/ImportController.php`)

Main controller handling migration requests.

**Key Methods:**
- `import()`: Orchestrates the migration process
- `testConnection()`: Validates FTP/SFTP connections
- `progress()`: Returns JSON progress status

**Responsibilities:**
- Form validation and file upload handling
- Progress file management
- Error handling and user feedback
- Media configuration validation

### 2. ImportModel (`src/component/admin/src/Model/ImportModel.php`)

Core migration orchestration logic.

**Key Methods:**
- `import($file, $sourceCms, $sourceUrl, $ftpConfig, $importAsSuperUser)`: Main import method
- Plugin event dispatching for conversion

**Data Flow:**
```php
// Plugin conversion
$event = new MigrationEvent('onMigrationConvert', [
    'sourceCms' => $sourceCms, 
    'filePath' => $file['tmp_name']
]);
$dispatcher->dispatch('onMigrationConvert', $event);

// Data processing
$processor = $mvcFactory->createModel('Processor', 'Administrator');
$result = $processor->process($data, $sourceUrl, $ftpConfig, $importAsSuperUser);
```

### 3. ProcessorModel (`src/component/admin/src/Model/ProcessorModel.php`)

Data processing and database operations.

**Key Features:**
- **Multi-format Support**: JSON and WordPress JSON-LD
- **Batch Processing**: Configurable batch sizes for performance
- **Transaction Management**: Database rollback on failure
- **Progress Tracking**: Real-time status updates

**Batch Processing Logic:**
```php
private function calculateBatchSize(int $total): int
{
    if ($total <= 25) return $total;
    elseif ($total <= 300) return 25;
    elseif ($total <= 1000) return 50;
    else return 100; // Cap for large migrations
}
```

### 4. MediaModel (`src/component/admin/src/Model/MediaModel.php`)

Comprehensive media migration system.

**Connection Types:**
- **FTP**: Standard File Transfer Protocol
- **FTPS**: FTP over SSL/TLS
- **SFTP**: SSH File Transfer Protocol
- **ZIP**: Direct file upload and extraction

**Key Features:**
- Auto-detection of WordPress directory structure
- Parallel media downloads
- URL pattern matching and conversion
- Storage directory configuration

**Media Processing Flow:**
```php
// Extract URLs from content
$imageUrls = $this->extractImageUrls($content);

// Batch download with connection pooling
$results = $this->batchDownloadMedia($imageUrls, $config);

// Update content with new URLs
$updatedContent = $this->migrateMediaInContent($config, $content, $sourceUrl);
```

## Plugin System

### Plugin Architecture

The migration system uses Joomla's plugin architecture for extensibility:

```php
// Event subscription
public static function getSubscribedEvents(): array
{
    return ['onMigrationConvert' => 'onMigrationConvert'];
}

// Event handling
public function onMigrationConvert(Event $event)
{
    [$sourceCms, $filePath] = array_values($event->getArguments());
    
    if ($sourceCms !== 'wordpress') return;
    
    $convertedData = $this->processWordPressXML($filePath);
    $event->addResult($convertedData);
}
```

### Existing Plugins

#### 1. WordPress Plugin (`src/plugins/migration/wordpress/`)

Converts WordPress WXR/XML exports to JSON-LD format.

**Features:**
- XML namespace handling (wp, content, dc)
- Author email mapping
- Tag and category processing
- Custom fields extraction
- Media attachment handling

**Output Format:**
```json
{
    "@context": "http://schema.org",
    "@type": "ItemList",
    "allTags": [...],
    "itemListElement": [...],
    "mediaItems": [...]
}
```

### Creating Custom Plugins

#### 1. Plugin Structure
```
src/plugins/migration/mycms/
├── script.php              # Installer script for plugin
├── mycms.xml               # Plugin Manifest File
├── src/Extension/          # Main plugin code
├── services/provider.php   # DI configuration
└── language/               # Contains localization files
```

#### 2. Plugin Class Template
```php
<?php
namespace My\Plugin\Migration\MyCms\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

class MyCms extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onMigrationConvert' => 'onMigrationConvert'];
    }

    public function onMigrationConvert(Event $event)
    {
        [$sourceCms, $filePath] = array_values($event->getArguments());
        
        if ($sourceCms !== 'mycms') return;
        
        // Your conversion logic here
        $convertedData = $this->convertMyCmsData($filePath);
        
        $event->addResult(json_encode($convertedData));
    }
    
    private function convertMyCmsData(string $filePath): array
    {
        // Implementation specific to your CMS
        return [];
    }
}
```

#### 3. Expected Output Format
Plugins should return JSON data in one of these formats:

**JSON Format (Simple):**
```json
{
    "users": [...],
    "taxonomies": {
        "category": [...],
        "post_tag": [...]
    },
    "post_types": {
        "post": [...],
        "page": [...]
    },
    "navigation_menus": {...}
}
```

**WordPress JSON-LD Format:**
```json
{
    "@context": "http://schema.org",
    "@type": "ItemList",
    "allTags": [...],
    "itemListElement": [...],
    "mediaItems": [...]
}
```

> **For detailed step-by-step instructions and complete templates, see [Plugin Development Guide](./Plugin_Development_Guide.md).**

## Local Development Setup

#### 1. Clone Repository
```bash
git clone https://github.com/joomla-projects/JA-Advanced-Migration-Tool.git
cd JA-Advanced-Migration-Tool
```

#### 2. Install Dependencies
```bash
# PHP dependencies
composer install

# Node.js dependencies (for testing)
npm install
```

#### 3. Configure Environment
Create `.env` file for Cypress testing:
```env
JOOMLA_BASE_URL=http://localhost:8080
JOOMLA_ADMIN_USER=admin
JOOMLA_ADMIN_PASS=admin123
```

### Development Tools

#### Code Quality
```bash
# Syntax checking
composer run-script test:syntax

# Run unit tests with PHPUnit
composer run-script test:unit

# Run full test suite (syntax check + PHPUnit + npm tests)
composer run-script test
```

#### E2E Testing
```bash
# Run all Cypress tests
npm test

# Open Cypress in interactive mode (GUI)
npm run test:open

# Run specific test file: login-admin.cy.js
npm run test:admin

# Run specific test file: install-extension.cy.js
npm run test:install

# Run all E2E spec files in tests/e2e/
npm run test:e2e
```

## Extension Development

### Adding New CMS Support

#### 1. Create Plugin Structure
```bash
mkdir -p src/plugins/migration/newcms/{src/Extension,services,language/en-GB}
```

#### 2. Implement Plugin Class
Follow the template in [Creating Custom Plugins](#creating-custom-plugins)

#### 3. Define Data Mapping
Map source CMS data to expected JSON format:

```php
private function mapContent(array $sourceData): array
{
    return [
        'users' => $this->mapUsers($sourceData['users'] ?? []),
        'taxonomies' => $this->mapTaxonomies($sourceData['categories'] ?? []),
        'post_types' => [
            'post' => $this->mapPosts($sourceData['posts'] ?? [])
        ]
    ];
}
```

#### 4. Handle Media References
```php
private function processMediaUrls(string $content): string
{
    // Extract and convert media URLs
    return preg_replace_callback(
        '/source-media-pattern/',
        [$this, 'convertMediaUrl'],
        $content
    );
}
```

#### 5. Test Integration
- Create test fixtures
- Write unit tests for conversion logic
- Add E2E tests for complete workflow

### Security Considerations

#### File Upload Validation
```php
// Validate file types
$allowedMimes = ['application/xml', 'application/json'];
if (!in_array($mimeType, $allowedMimes)) {
    throw new \RuntimeException('Invalid file type');
}

// Validate file size
if ($fileSize > $maxSize) {
    throw new \RuntimeException('File too large');
}
```

#### SQL Injection Prevention
```php
// Use parameter binding
$query = $db->getQuery(true)
    ->select('id')
    ->from('#__content')
    ->where('title = :title')
    ->bind(':title', $title);
```

#### XSS Prevention
```php
use Joomla\CMS\Filter\OutputFilter;

// Sanitize output
$safeContent = OutputFilter::ampReplace($content);
$safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
```

### Contributing Guidelines

#### Code Standards
- Follow [Joomla Coding Standards](https://manual.joomla.org/docs/get-started/codestyle/)
- Document all public methods with PHPDoc

#### Pull Request Process
1. Fork the repository
2. Create feature branch from `main`
3. Write tests for new functionality
4. Ensure all tests pass
5. Submit pull request with detailed description

#### Testing Requirements
- Unit tests for all new models and controllers
- E2E tests for user workflows
- Performance testing for large migrations
- Cross-browser compatibility testing

---

## Additional Resources

- [Joomla Developer Documentation](https://manual.joomla.org/docs/next/building-extensions/)
- [Joomla Plugin Development](https://manual.joomla.org/docs/building-extensions/plugins/)
- [Cypress Documentation](https://docs.cypress.io/)

For questions and support, please use the project's GitHub issues or join the Joomla Community discussions.
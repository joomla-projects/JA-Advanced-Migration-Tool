# Advanced Migration Tool - Testing Documentation

## Table of Contents
1. [Overview](#overview)
2. [Getting Started](#-getting-started)
3. [Unit Testing with PHPUnit](#-unit-testing-with-phpunit)
4. [Integration Testing](#-integration-testing)
5. [End-to-End Testing with Cypress](#-end-to-end-testing-with-cypress)
6. [Test Data & Fixtures](#-test-data--fixtures)
7. [Continuous Integration](#-continuous-integration)
8. [Testing Best Practices](#-testing-best-practices)
9. [Complete Test Suite Overview](#-complete-test-suite-overview)
10. [Quick Test Commands](#-quick-test-commands)
11. [Test File Organization](#-test-file-organization)
12. [Additional Resources](#-additional-resources)

## Overview

This comprehensive guide covers testing strategies for the Joomla Advanced Migration Tool, ensuring reliability, performance, and compatibility across different environments.

### Testing Strategy

Our testing approach follows a three-tier pyramid:

| Test Type | Framework | Purpose | Coverage |
|-----------|-----------|---------|----------|
| **Unit Tests** | PHPUnit | Test individual components in isolation | Maximum coverage |
| **Integration Tests** | PHPUnit | Test component interactions | Critical paths |
| **E2E Tests** | Cypress | Test complete user workflows | Full migration scenarios |

**📊 Current Test Stats:**
- **95+ Total Tests** across all categories
- **85+ Unit Tests** with comprehensive coverage
- **10 E2E Tests** covering complete workflows
- **15+ Test Classes** organized by functionality

## 🚀 Getting Started

### Prerequisites

Before running tests, ensure you have:

- ✅ **PHP 8.1+** with required extensions
- ✅ **Joomla 5.0+** test instance
- ✅ **Node.js** for Cypress E2E tests
- ✅ **MySQL/MariaDB** database
- ✅ **Web server** (Apache/Nginx)

### ⚡ Quick Setup

**1. Install Dependencies**
```bash
git clone https://github.com/joomla-projects/JA-Advanced-Migration-Tool.git
cd JA-Advanced-Migration-Tool
composer install
npm install
```

**2. Environment Configuration**
Create `.env` file:
```env
JOOMLA_BASE_URL=http://localhost:8080
JOOMLA_ADMIN_USER=admin
JOOMLA_ADMIN_PASS=admin123
```

**3. Database Setup**
```sql
CREATE DATABASE joomla_test;
GRANT ALL PRIVILEGES ON joomla_test.* TO 'test_user'@'localhost';
```

**4. Docker Alternative**
Use `docker-compose.test.yml` for quick Joomla + MySQL setup.

## 🧪 Unit Testing with PHPUnit

Unit tests validate individual components in isolation using mocks and test doubles.

### 🏃‍♂️ Running Tests

```bash
# Run all unit tests
composer test:unit

# Generate coverage report
./vendor/bin/phpunit --coverage-html coverage

# Run specific test class
./vendor/bin/phpunit tests/unit/Model/ImportModelTest.php

# Detailed output
./vendor/bin/phpunit tests/unit/ --testdox
```

### 🎯 What We Test

- **Controllers**: Input validation, error handling, HTTP responses
- **Models**: Data processing, batch operations, format conversions
- **Tables**: Database operations, validation rules, data integrity
- **Events**: Event system, result collection, argument handling
- **Views**: Template rendering, property assignment, display logic
- **Plugins**: Migration conversions for different CMS platforms

## 🔗 Integration Testing

Integration tests verify how different components work together, using real database connections while mocking external services.

### Key Areas Tested
- Complete import workflows
- Database transaction handling
- Plugin interaction chains
- Error recovery mechanisms
- API endpoint interactions

## 🌐 End-to-End Testing with Cypress

E2E tests simulate real user interactions and complete migration workflows.

### ⚙️ Configuration

Cypress is configured in `cypress.config.js` with:
- Environment-based URL configuration
- Automatic video recording on failures
- Code coverage integration
- Custom commands for authentication and UI interactions

### 📝 Test Categories

| Category | Tests | Description |
|----------|-------|-------------|
| **Installation** | 3 tests | Joomla setup, component installation, configuration validation |
| **Security** | 2 tests | Input validation, permission checks, CSRF protection |
| **Migration Workflows** | 4 tests | JSON import, WordPress migration, media handling, cleanup |
| **Framework Validation** | 1 test | Core system validation |

### 🏃‍♂️ Running E2E Tests

```bash
# Run all E2E tests (headless)
npm test

# Open interactive test runner
npm run test:open

# Run specific test file
npx cypress run --spec "tests/e2e/05-json-migration.cy.js"

# Run in different browsers
npx cypress run --browser firefox
npx cypress run --browser edge
```

## 📁 Test Data & Fixtures

### 🗂️ Available Fixtures

| Type | File | Description |
|------|------|-------------|
| **WordPress** | `test-migration-wordpress.xml` | Sample export with posts, users, categories |
| **JSON** | `test-migration-json.json` | Structured data for custom migrations |
| **Media** | `media-files/` | ZIP archives with images and documents |
| **Invalid Data** | `invalid.xml`, `invalid.json` | For error handling tests |

### 🔄 Database Fixtures
- Automated scripts for database state reset
- Seeded test data for consistent testing
- Transaction-based cleanup between tests

## 🚀 Continuous Integration

### 🔄 GitHub Actions Pipeline

**Automated Testing on Every Push/PR:**

| Job | Environment | Coverage |
|-----|-------------|----------|
| **Unit Tests** | PHP 7.4, 8.0, 8.1 | Full test suite with coverage upload |
| **E2E Tests** | Joomla + MySQL | Complete workflow testing |
| **Security Scan** | Latest PHP | Vulnerability detection |

### 📊 Reporting & Monitoring
- **Mochawesome**: Detailed E2E test reports with screenshots
- **Codecov**: Code coverage tracking and trends
- **Artifacts**: Screenshots and videos for failed tests
- **Notifications**: Slack/email alerts for test failures

## 💡 Testing Best Practices

### 🧪 Unit Testing Guidelines
- **Single Responsibility**: One concept per test method
- **Isolation**: Mock all external dependencies
- **Data Providers**: Use for testing multiple input variations
- **Descriptive Names**: Test names should explain what's being tested

### 🌐 E2E Testing Guidelines
- **User Journey Focus**: Test complete workflows, not individual functions
- **Explicit Waits**: Wait for specific elements rather than using timeouts
- **Page Object Model**: Maintain reusable page components
- **Environment Independence**: Tests should work across environments

### ⚡ Performance Guidelines
- **Budget Setting**: Define time and memory constraints
- **Realistic Data**: Test with production-like data volumes
- **Trend Monitoring**: Track performance metrics over time
- **Bottleneck Identification**: Profile and optimize slow operations

### 🔒 Security Testing Guidelines
- **Input Validation**: Test all user input points
- **Authentication**: Verify CSRF protection and session handling
- **File Upload Security**: Test file type and size restrictions
- **Data Sanitization**: Ensure proper escaping and validation

## 📋 Complete Test Suite Overview

### 📊 Test Statistics

| **Category**         | **Tests** | **Focus Area**                           |
|----------------------|-----------|-------------------------------------------|
| **Model Tests**      | 33+       | Data processing, validation, business logic |
| **Table Tests**      | 14+       | Database operations, data integrity        |
| **Event Tests**      | 13+       | Event system, plugin integration           |
| **View Tests**       | 12+       | UI rendering, template logic               |
| **Integration Tests**| 10+       | Component interaction workflows            |
| **Controller Tests** | 8+        | HTTP handling, request processing          |
| **Extension Tests**  | 4+        | Component lifecycle, bootstrapping         |
| **E2E Tests**        | 10        | Complete user workflows                    |
| **🎯 Total**         | **95+**   | **Comprehensive coverage**                 |


### 🧪 Unit Test Categories

#### 🎮 Controller Tests (8+ tests)
**ImportControllerTest** & **DisplayControllerTest**
- Inheritance validation and instantiation
- CSRF token validation for security
- JSON response formatting
- Default view configuration

#### 📊 Model Tests (33+ tests)
**ImportModelTest**
- File validation and processing workflows
- JSON data structure handling
- Plugin discovery and integration
- Error handling for invalid inputs

**MediaModelTest**
- Storage directory configuration and sanitization
- Document root path management
- Image URL extraction from content
- Connection validation (FTP/SFTP)

**ProcessorModelTest**
- Data structure processing (JSON/WordPress)
- Database transaction management
- Error rollback mechanisms
- Dependency injection and mocking

#### 🗄️ Table Tests (14+ tests)
**ArticleTableTest**
- Table inheritance and setup validation
- Automatic alias generation from titles
- Date timestamp management
- Data validation and sanitization
- Database connection handling

#### 🎪 Event Tests (13+ tests)
**MigrationEventTest**
- Event system inheritance and instantiation
- Argument passing and retrieval
- Result collection and management
- Event immutability and preservation

#### 🖼️ View Tests (12+ tests)
**HtmlViewTest**
- View inheritance and instantiation
- Form and document property management
- Toolbar and script integration
- Template parameter handling

#### 🔌 Extension Tests (4+ tests)
**CmsMigratorComponentTest**
- Component architecture validation
- Bootable interface implementation
- Component initialization workflow

#### 🔗 Integration Tests (10+ tests)
**ComponentIntegrationTest**
- End-to-end component initialization
- Model-event system integration
- Complete data flow testing
- Error handling across components
- File upload and media configuration

### 🌐 End-to-End Test Suite (10 tests)

#### 🛠️ Installation & Setup
- **00-a-installer.cy.js**: Joomla installation verification
- **00-configuration-validation.cy.js**: System configuration checks
- **01-framework-validation.cy.js**: Core framework validation
- **02-login-admin.cy.js**: Administrator authentication

#### 🔒 Security & Installation
- **03-security.cy.js**: Security feature testing
- **04-install-extension.cy.js**: Component installation workflow

#### 🔄 Migration Workflows
- **05-json-migration.cy.js**: JSON data migration process
- **06-cleanup.cy.js**: Post-migration cleanup procedures
- **07-wordpress-migration.cy.js**: WordPress import workflow
- **08-media-migration.cy.js**: Media file migration handling

### 🎯 Functional Test Coverage

#### 🔒 Security Testing (8+ tests)
- CSRF token validation across all forms
- File upload security and validation
- Input sanitization and XSS prevention
- SQL injection protection mechanisms

#### 📁 File Handling (12+ tests)
- Multi-format file upload validation
- JSON/XML structure processing
- Media file extraction and processing
- Temporary file cleanup procedures

#### 🗄️ Database Operations (15+ tests)
- Transaction management and rollback
- Data validation and integrity checks
- Table operations and relationships
- Connection handling and pooling

#### 🔌 Plugin System (8+ tests)
- Event-driven architecture testing
- Plugin discovery and registration
- Result collection and aggregation
- Cross-plugin communication

#### 🌐 Media Migration (10+ tests)
- FTP/SFTP connection validation
- ZIP file processing and extraction
- Image URL detection and replacement
- Storage path configuration

#### ⚙️ Configuration Management (12+ tests)
- Component setup and initialization
- Storage path validation and creation
- Connection parameter validation
- Environment-specific settings

### 🚀 Quick Test Commands

```bash
# 🧪 Unit Tests
composer test:unit                                    # All unit tests
vendor/bin/phpunit tests/unit/Controller/            # Controller tests only
vendor/bin/phpunit tests/unit/Model/                 # Model tests only
vendor/bin/phpunit tests/unit/Integration/           # Integration tests only

# 📊 Coverage & Reporting
vendor/bin/phpunit --coverage-html coverage          # Generate coverage report
vendor/bin/phpunit tests/unit/ --testdox            # Detailed test descriptions

# 🌐 E2E Tests
npm test                                              # All E2E tests (headless)
npm run test:open                                     # Interactive test runner
npx cypress run --spec "tests/e2e/05-json-migration.cy.js"  # Specific workflow
```

### 📁 Test File Organization

```
tests/
├── 🧪 unit/                          # Unit & Integration Tests
│   ├── 📜 bootstrap.php               # Test environment setup
│   ├── 🎮 Controller/                 # Controller layer tests
│   ├── 📊 Model/                      # Business logic tests
│   ├── 🗄️ Table/                      # Database layer tests
│   ├── 🎪 Event/                      # Event system tests
│   ├── 🖼️ View/                       # Presentation layer tests
│   ├── 🔌 Extension/                  # Component architecture tests
│   ├── 🔗 Integration/                # Cross-component tests
│   ├── 🎭 Mocks/                      # Test doubles and mocks
│   └── 📋 stubs/                      # Test data and fixtures
└── 🌐 e2e/                           # End-to-End Tests
    ├── 🛠️ Installation & Setup Tests
    ├── 🔒 Security Tests
    ├── 🔄 Migration Workflow Tests
    ├── 📁 support/                    # Test utilities
    └── 📦 fixtures/                   # Test data files
```

## 📚 Additional Resources

### 📖 Documentation Links
- **[PHPUnit Documentation](https://phpunit.readthedocs.io/)** - Complete guide to PHP unit testing
- **[Cypress Testing Guide](https://docs.cypress.io/)** - Modern E2E testing framework
- **[Joomla Testing Standards](https://docs.joomla.org/J4.x:Testing)** - Joomla-specific testing practices

### 🤝 Community Support
- **GitHub Discussions** - Technical questions and feature discussions
- **Joomla Mattermost** - Real-time community chat and support

### 🔗 Related Documentation
- **[Developer Documentation](./Developer_Documentation.md)** - Setup and development guide
- **[User Documentation](./User_Documentation.md)** - End-user migration guide  
- **[Plugin Development Guide](./Plugin_Development_Guide.md)** - Extending the migration tool

---

> **✅ Testing Complete**: This documentation covers our comprehensive testing strategy ensuring the Joomla Advanced Migration Tool is reliable, secure, and performant across all supported platforms and migration scenarios.

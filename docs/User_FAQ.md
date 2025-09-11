# Advanced Migration Tool – Frequently Asked Questions

**Common questions and answers about using the Advanced Migration Tool for Joomla.**

---

## 📋 Table of Contents

- [General Questions](#general-questions)
- [Technical Questions](#technical-questions)
- [Content-Specific Questions](#content-specific-questions)
- [Media Migration Questions](#media-migration-questions)

---

## General Questions

**Q: Can I run multiple migrations on the same Joomla site?**\
A: Yes, the tool safely handles multiple migrations. Duplicate content (same titles) is automatically skipped.

**Q: What happens to existing Joomla content?**\
A: Existing content is preserved. The migration tool only adds new content and skips duplicates.

**Q: How long does a typical migration take?**\
A: Depends on content size:
- Small sites (< 100 articles): 1-5 minutes
- Medium sites (100-1000 articles): 5-30 minutes  
- Large sites (1000+ articles): 30-180 minutes

**Q: Can I migrate from multiple source sites?**\
A: Yes, you can run separate migrations for each source site. Content is merged safely.

## Technical Questions

**Q: Which migration method should I choose?**\
A:
- **Method 1 (Native)**: Best for standard WordPress exports, no source CMS changes required
- **Method 2 (JSON)**: Best for custom requirements, enhanced control, consistent format

**Q: Are user passwords migrated?**\
A: No, for security reasons. All migrated users receive random passwords and must reset them.

**Q: How are custom fields handled?**\
A: Custom fields are automatically mapped to Joomla custom fields. Underscore-prefixed fields are treated as metadata.

**Q: Can I customize the migration process?**\
A: Yes, through plugin configuration and custom export plugins. See Developer Documentation for details.

## Content-Specific Questions

**Q: What content types are supported?**\
A: Standard support includes:
- ✅ Articles/Posts/Pages
- ✅ Categories (with hierarchy)
- ✅ Tags
- ✅ Menus
- ✅ Users
- ✅ Custom Fields
- ✅ Media files

**Q: How are WordPress shortcodes handled?**\
A: Basic shortcodes are converted to Joomla equivalents. Complex shortcodes may require manual review.

## Media Migration Questions

**Q: Where are migrated media files stored?**\
A: Default location is `images/imports/` but you can specify a custom directory.

**Q: What if media files are very large?**\
A: Use the ZIP upload method for large media libraries, or configure FTP with increased timeouts.

**Q: Are media file names preserved?**\
A: Yes, including directory structure. File conflicts are handled with numerical suffixes.

---

> **For more information, see [User Documentation](./User_Documentation.md), [Developer Documentation](./Developer_Documentation.md) and [Testing Documentation](./Testing_Documentation.md). For creating new CMS plugins, see [Plugin Development Guide](./Plugin_Development_Guide.md).**

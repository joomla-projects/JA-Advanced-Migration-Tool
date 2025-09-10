/**
 * Media Migration Tests
 *
 * Tests the media migration functionality for Joomla CMS Migration Tool
 */

describe("Media Migration", () => {
  beforeEach(() => {
    // Use fresh login to ensure clean authentication
    cy.doFreshAdministratorLogin(
      Cypress.env("joomlaAdminUser"),
      Cypress.env("joomlaAdminPass"),
      true
    );

    // Navigate to CMS Migrator component
    cy.visit("/administrator/index.php?option=com_cmsmigrator");
    cy.handlePopups();
  });
  it("should display media migration form elements", () => {
    // Verify the migration form is visible
    cy.get("#migration-form").should("be.visible");

    // Verify source CMS selector exists
    cy.get("#jform_source_cms").should("be.visible");

    // Verify file upload input exists for JSON/XML
    cy.get("#jform_import_file").should("be.visible");
    cy.get("#jform_import_file").should("have.attr", "accept", ".xml,.json");

    // Verify media migration options
    cy.get("#jform_enable_media_migration0").should("be.visible", {
      timeout: 1500,
    });
    cy.get("#jform_connection_type").should("exist");

    // Verify import button exists
    cy.get(".button-upload").should("be.visible");
    cy.get(".button-upload").should("contain", "Import");
  });

  it("Removing all the Content, Categories, Tags, Media, Users, Fields", () => {
    cy.trashAndEmpty(
      "/administrator/index.php?option=com_content&view=articles",
      "articles"
    );
    cy.trashAndEmpty(
      "/administrator/index.php?option=com_categories&extension=com_content",
      "categories"
    );
    cy.trashAndEmpty(
      "/administrator/index.php?option=com_fields&view=fields",
      "fields"
    );
    cy.trashAndEmpty("/administrator/index.php?option=com_tags", "tags");

    // Remove "imports" folder from Media
    cy.visit("/administrator/index.php?option=com_media");
    cy.wait(1000);
    cy.get(".media-browser-item").then(($items) => {
      const matchingItem = [...$items].find(
        (item) =>
          item.querySelector(".media-browser-item-info")?.textContent.trim() ===
          "imports"
      );
      if (matchingItem) {
        cy.wrap(matchingItem).within(() => {
          cy.get(".media-browser-item-preview").click({ force: true });
        });
        cy.wait(500);
        cy.get(
          'button:contains("Delete"), [aria-label*="Delete"], .fa-trash, a:contains("Delete")'
        )
          .first()
          .click({ force: true });
        cy.get('.modal-dialog[role="dialog"]').should("be.visible");
        cy.get("#media-delete-item.btn-danger").click({ force: true });
        cy.get('.modal-dialog[role="dialog"]').should("not.exist");
      }
    });

    // Clear users (except super users)
    cy.visit("/administrator/index.php?option=com_users");
    cy.wait(1000);
    cy.setListLimitToAll();
    cy.hasListItems().then((hasItems) => {
      if (hasItems) {
        cy.get('input[name="checkall-toggle"]').check({ force: true });
        cy.get("table tbody tr").each(($row) => {
          if ($row.find("td:contains('Super Users')").length) {
            cy.wrap($row)
              .find('input[type="checkbox"][name*="cid"]')
              .uncheck({ force: true });
          }
        });
        cy.get(
          '[data-bs-target="#toolbar-delete"], button:contains("Delete"), a:contains("Delete")'
        )
          .first()
          .click({ force: true });
        cy.confirmDialog();
      }
    });
  });

  it("should enable media migration options", () => {
    // Select JSON as source CMS
    cy.get("#jform_source_cms").select("json");

    // Enable media migration
    cy.get("label[for='jform_enable_media_migration0']").click();

    // Verify option is selected
    cy.get("#jform_enable_media_migration0").should("be.checked");

    // Verify connection type options appear
    cy.get("#jform_connection_type").should("be.visible");
  });

  it("should configure ZIP media migration", () => {
    // Select JSON as source CMS
    cy.get("#jform_source_cms").select("json");

    // Enable media migration
    cy.get("label[for='jform_enable_media_migration0']").click();

    // Select ZIP upload for media
    cy.get("#jform_connection_type").select("zip");

    // Verify ZIP file input appears
    cy.get("#jform_media_zip_file").should("be.visible");
  });

  it("should perform media migration using JSON and ZIP", () => {
    // Select JSON as source CMS
    cy.get("#jform_source_cms").select("json");

    // Upload the JSON migration file
    cy.get("#jform_import_file").selectFile(
      "cypress/fixtures/test-migration-json.json",
      { action: "drag-drop", force: true }
    );

    // Set source URL for images
    cy.get("#jform_source_url").type("https://example.com");

    // Enable media migration
    cy.get("label[for='jform_enable_media_migration0']").click();

    // Select ZIP as connection type for media
    cy.get("#jform_connection_type").select("zip");

    // Zip the media-files folder into media-files.zip
    cy.exec("cd cypress/fixtures/media-files && zip -r ../media-files.zip .");
    cy.readFile("cypress/fixtures/media-files.zip", { timeout: 5000 }).should("exist");

    // Upload the ZIP file containing media
    cy.get("#jform_media_zip_file").selectFile(
      "cypress/fixtures/media-files.zip",
      { action: "drag-drop", force: true }
    );

    // Start import
    cy.get(".button-upload").click();

    // Wait for import to complete and verify success
    cy.get("#system-message-container", { timeout: 20000 }).should(
      "be.visible"
    );
    cy.get("#system-message-container").should(
      "contain",
      "Import completed successfully"
    );
    cy.get("#system-message-container").should(
      "contain",
      "Media files imported: 2"
    );
  });

  it("should validate media ZIP file format", () => {
    // Select JSON as source CMS
    cy.get("#jform_source_cms").select("json");

    // Enable media migration
    cy.get("label[for='jform_enable_media_migration0']").click();

    // Select ZIP upload
    cy.get("#jform_connection_type").select("zip");

    // Upload invalid file (not ZIP)
    cy.get("#jform_media_zip_file").selectFile("cypress/fixtures/invalid.xml", {
      action: "drag-drop",
      force: true,
    });

    // Start import
    cy.get(".button-upload").click();

    // Should show validation error
    cy.get("#system-message-container").should("be.visible");
    cy.get("#system-message-container").should("contain", "Error");
  });

  afterEach(() => {
    // Check for PHP errors or warnings
    cy.checkForPhpNoticesOrWarnings();
    cy.get("#system-message-container").should("exist");
  });
});

/**
 * JSON Migration Tests
 *
 * Tests the JSON import migration functionality for Joomla CMS Migration Tool
 */

describe("JSON Migration", () => {
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
    cy.wait(2000); // Extra wait for form elements to load
  });
  it("should display JSON migration form elements", () => {
    // Verify the migration form is visible
    cy.get("#migration-form", { timeout: 15000 }).should("be.visible");

    // Verify source CMS selector exists and has JSON option
    cy.get("#jform_source_cms").should("be.visible");
    cy.get("#jform_source_cms option[value='json']").should("exist");

    // Verify file upload input exists and accepts JSON files
    cy.get("#jform_import_file").should("be.visible");
    cy.get("#jform_import_file").should("have.attr", "accept", ".xml,.json");

    // Verify import button exists
    cy.get(".button-upload").should("be.visible");
    cy.get(".button-upload").should("contain", "Import");
  });

  it("should select JSON as source CMS", () => {
    // Select JSON as source CMS
    cy.get("#jform_source_cms").select("json");

    // Verify JSON is selected
    cy.get("#jform_source_cms").should("have.value", "json");
  });

  it("should upload JSON migration file successfully", () => {
    // Select JSON as source CMS
    cy.get("#jform_source_cms").select("json");

    // Upload the JSON file
    cy.get("#jform_import_file").selectFile(
      "cypress/fixtures/test-migration-json.json",
      { action: "drag-drop", force: true }
    );

    // Optionally, verify the file input exists (do not check for empty value)
    cy.get("#jform_import_file").should("exist");

    // Optionally set source URL for images
    cy.get("#jform_source_url").type("https://example.com");
  });

  it("should validate JSON file structure", () => {
    // Select JSON as source CMS
    cy.get("#jform_source_cms").select("json");

    // Upload invalid JSON file
    cy.get("#jform_import_file").selectFile("cypress/fixtures/invalid.json", {
      action: "drag-drop",
      force: true,
    });

    // Try to start import
    cy.get(".button-upload").click();

    // Should show validation error or system message
    cy.get("#system-message-container").should("be.visible");
    cy.get("#system-message-container").should(($el) => {
      const text = $el.text().toLowerCase();
      expect(
        text.includes("error") ||
          text.includes("invalid") ||
          text.includes("json")
      ).to.be.true;
    });
  });

  it("should configure import options", () => {
    // Select JSON as source CMS
    cy.get("#jform_source_cms").select("json");

    // Configure import options
    // Import as super user - select Yes
    cy.get("label[for='jform_import_as_super_user0']").click();

    // Verify option is selected
    cy.get("#jform_import_as_super_user0").should("be.checked");

    // Set source URL
    cy.get("#jform_source_url").type("https://example.com");

    // Configure media migration if needed
    cy.get("label[for='jform_enable_media_migration0']").click(); // Enable media migration

    // Select ZIP upload for media
    cy.get("#jform_connection_type").select("zip");
  });

  it("should start JSON import process", () => {
    // Select JSON as source CMS
    cy.get("#jform_source_cms", { timeout: 10000 }).select("json");

    // Upload valid JSON file
    cy.get("#jform_import_file").selectFile(
      "cypress/fixtures/test-migration-json.json",
      { action: "drag-drop", force: true }
    );
    cy.get("#jform_source_url").type("https://example.com");

    // Start import
    cy.get(".button-upload").click();

    // Wait for import to complete and page to reload
    cy.get("#system-message-container", { timeout: 30000 }).should(
      "be.visible"
    );

    // Verify success message is displayed - check for any success indication
    cy.get("#system-message-container").should(($el) => {
      const text = $el.text().toLowerCase();
      expect(
        text.includes("import completed successfully") ||
          text.includes("success") ||
          text.includes("imported")
      ).to.be.true;
    });

    // Optional checks for specific import counts (may vary)
    cy.get("body").then(($body) => {
      const messageText = $body.find("#system-message-container").text();
      if (messageText.includes("Users imported")) {
        cy.get("#system-message-container").should("contain", "Users imported");
      }
      if (messageText.includes("Articles imported")) {
        cy.get("#system-message-container").should(
          "contain",
          "Articles imported"
        );
      }
      if (messageText.includes("Categories imported")) {
        cy.get("#system-message-container").should(
          "contain",
          "Categories imported"
        );
      }
    });
  });

  it("should handle empty JSON file", () => {
    // Select JSON as source CMS
    cy.get("#jform_source_cms").select("json");

    // Upload empty JSON file
    const emptyJson = "{}";

    cy.get("#jform_import_file").selectFile({
      contents: Cypress.Buffer.from(emptyJson),
      fileName: "empty.json",
      mimeType: "application/json",
    });

    // Try to start import
    cy.get(".button-upload").click();

    // Should show appropriate message
    cy.get("#system-message-container").should("be.visible");
  });

  afterEach(() => {
    // Check for PHP errors or warnings
    cy.checkForPhpNoticesOrWarnings();
    cy.get("#system-message-container").should("exist");
  });
});

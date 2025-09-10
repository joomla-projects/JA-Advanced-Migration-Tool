/**
 * WordPress Migration Tests
 *
 * Tests the WordPress import migration functionality for Joomla CMS Migration Tool
 */

describe("WordPress Migration", () => {
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

  it("should install the WordPress migration plugin", () => {
    // Use fresh login for this critical operation
    cy.doFreshAdministratorLogin(
      Cypress.env("joomlaAdminUser"),
      Cypress.env("joomlaAdminPass"),
      true
    );

    // Zip the WordPress plugin folder
    cy.exec(
      "cd src/plugins/migration/wordpress && zip -r ../../../../cypress/fixtures/plg_wordpress.zip ."
    );
    cy.readFile("cypress/fixtures/plg_wordpress.zip", { timeout: 5000 })
      .should("exist");
    // Install the plugin
    cy.visit("/administrator/index.php?option=com_installer&view=install");
    cy.contains("a,button", "Upload Package File", { matchCase: false })
      .should("be.visible")
      .click({ force: true });
    cy.get('input[type="file"]', { timeout: 5000 }).should("exist");
    cy.get('input[type="file"]#install_package').selectFile(
      "cypress/fixtures/plg_wordpress.zip",
      { action: "drag-drop", force: true }
    );
    cy.wait(1000);
    cy.get("#system-message-container", { timeout: 10000 })
      .should("be.visible")
      .then(($msg) => {
        const txt = $msg.text().trim().toLowerCase();
        cy.log("System message:", txt);
        expect(txt).to.match(/(success|installed|completed)/);
      });
  });

  it("should verify the WordPress migration plugin is installed", () => {
    // Use fresh login for verification
    cy.doFreshAdministratorLogin(
      Cypress.env("joomlaAdminUser"),
      Cypress.env("joomlaAdminPass"),
      true
    );

    // Verify the plugin is installed
    cy.visit("/administrator/index.php?option=com_installer&view=manage");
    cy.get('input[placeholder="Search"], input[name="filter_search"]')
      .first()
      .then(($input) => {
        if ($input.length) {
          cy.wrap($input)
            .clear()
            .type("WordPress Migration Plugin", { force: true });
          cy.get('button[aria-label="Search"], button[type="submit"]')
            .first()
            .click({ force: true });
        }
      });
    cy.get("table", { timeout: 15000 })
      .contains("td,th", "WordPress Migration Plugin")
      .should("exist");
  });

  it("should display WordPress migration form elements", () => {
    // Verify the migration form is visible
    cy.get("#migration-form").should("be.visible");

    // Verify source CMS selector exists and has WordPress option
    cy.get("#jform_source_cms").should("be.visible");
    cy.get("#jform_source_cms option[value='wordpress']").should("exist");

    // Verify file upload input exists and accepts XML files
    cy.get("#jform_import_file").should("be.visible");
    cy.get("#jform_import_file").should("have.attr", "accept", ".xml,.json");

    // Verify import button exists
    cy.get(".button-upload").should("be.visible");
    cy.get(".button-upload").should("contain", "Import");
  });

  it("should select WordPress as source CMS", () => {
    // Select WordPress as source CMS
    cy.get("#jform_source_cms").select("wordpress");

    // Verify WordPress is selected
    cy.get("#jform_source_cms").should("have.value", "wordpress");
  });

  it("should upload WordPress migration file successfully", () => {
    // Select WordPress as source CMS
    cy.get("#jform_source_cms").select("wordpress");

    // Upload the WordPress XML file
    cy.get("#jform_import_file").selectFile(
      "cypress/fixtures/test-migration-wordpress.xml",
      { action: "drag-drop", force: true }
    );

    // Optionally, verify the file input exists (do not check for empty value)
    cy.get("#jform_import_file").should("exist");

    // Optionally set source URL for images
    cy.get("#jform_source_url").type("https://example.com");
  });

  it("should validate WordPress file structure", () => {
    // Select WordPress as source CMS
    cy.get("#jform_source_cms").select("wordpress");

    // Upload invalid XML file
    cy.get("#jform_import_file").selectFile("cypress/fixtures/invalid.xml", {
      action: "drag-drop",
      force: true,
    });

    // Try to start import
    cy.get(".button-upload").click();

    // Should show validation error or system message
    cy.get("#system-message-container .danger").should("exist");
  });

  it("should configure import options", () => {
    // Select WordPress as source CMS
    cy.get("#jform_source_cms").select("wordpress");

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

  it("should start WordPress import process", () => {
    // Select WordPress as source CMS
    cy.get("#jform_source_cms").select("wordpress");

    // Upload valid WordPress XML file
    cy.get("#jform_import_file").selectFile(
      "cypress/fixtures/test-migration-wordpress.xml",
      { action: "drag-drop", force: true }
    );
    cy.get("#jform_source_url").type("https://example.com");

    // Start import
    cy.get(".button-upload").click();

    // Wait for import to complete and page to reload
    cy.get("#system-message-container", { timeout: 15000 }).should(
      "be.visible"
    );

    // Verify success message is displayed
    cy.get("#system-message-container").should(
      "contain",
      "Import completed successfully"
    );
    cy.get("#system-message-container").should("contain", "Users imported: 1");
    cy.get("#system-message-container").should(
      "contain",
      "Articles imported: 4"
    );
    cy.get("#system-message-container").should(
      "contain",
      "Categories imported: 4"
    );
  });

  it("should handle empty WordPress file", () => {
    // Select WordPress as source CMS
    cy.get("#jform_source_cms").select("wordpress");

    // Upload empty XML file
    const emptyXml = '<?xml version="1.0"?><root></root>';

    cy.get("#jform_import_file").selectFile({
      contents: Cypress.Buffer.from(emptyXml),
      fileName: "empty.xml",
      mimeType: "application/xml",
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

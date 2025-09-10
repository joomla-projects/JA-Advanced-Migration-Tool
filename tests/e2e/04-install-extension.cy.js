describe("Joomla Extension Installation Test ", () => {
  const extName = "Migrate To Joomla";

  beforeEach(() => {
    // Use fresh login to ensure clean authentication
    cy.doFreshAdministratorLogin(
      Cypress.env("joomlaAdminUser"),
      Cypress.env("joomlaAdminPass"),
      true
    );
  });
  it("1) installs the com_cmsmigrator.zip extension via drag and drop", () => {
    cy.visit("/administrator/index.php?option=com_installer&view=install");
    cy.handlePopups();

    // Make sure we're on the Upload Package File tab (usually default)
    cy.contains("a,button", "Upload Package File", { matchCase: false })
      .should("be.visible")
      .click({ force: true });

    // Get the file input for drag and drop
    cy.get('input[type="file"]', { timeout: 5000 }).should("exist");

    // Use cy.selectFile to simulate drag and drop of the local ZIP file
    cy.get('input[type="file"]#install_package').selectFile(
      "cypress/fixtures/com_cmsmigrator.zip",
      { action: "drag-drop", force: true }
    );

    // Wait a moment for the file to be processed
    cy.wait(1000);

    // Verify success message
    cy.get("#system-message-container", { timeout: 20000 })
      .should("be.visible")
      .then(($msg) => {
        const txt = $msg.text().trim().toLowerCase();
        cy.log("System message:", txt);
        expect(txt).to.match(/(success|installed|completed)/);
      });
  });

  it("2) verifies the extension appears in Manage -> Extensions list", () => {
    cy.visit("/administrator/index.php?option=com_installer&view=manage");
    cy.handlePopups();

    cy.get('input[placeholder="Search"], input[name="filter_search"]')
      .first()
      .then(($input) => {
        if ($input.length) {
          cy.wrap($input).clear().type(extName, { force: true });
          cy.get('button[aria-label="Search"], button[type="submit"]')
            .first()
            .click({ force: true });
        }
      });

    // Check extension in the table
    cy.get("table", { timeout: 20000 })
      .contains("td,th", extName)
      .should("exist");
  });

  it("3) opens the component (Components -> Migrate To Joomla)", () => {
    cy.visit("/administrator/index.php?option=com_cpanel");
    cy.handlePopups();
    cy.wait(2000);

    // Try direct navigation first as fallback
    cy.get("body").then(($body) => {
      if ($body.find("#collapse3").length > 0) {
        // Explicitly look inside the Components collapse menu
        cy.get("#collapse3", { timeout: 15000 }).within(() => {
          cy.contains(
            "a.no-dropdown, .sidebar-item-title",
            "Migrate To Joomla",
            {
              matchCase: false,
            }
          ).click({ force: true });
        });
      } else {
        // Fallback: direct navigation to the component
        cy.visit("/administrator/index.php?option=com_cmsmigrator");
      }
    });

    // Verify the component page loaded
    cy.get("h1, .page-title, #migration-form", { timeout: 15000 }).should(
      "exist"
    );
    cy.url().should("include", "com_cmsmigrator");
  });
});
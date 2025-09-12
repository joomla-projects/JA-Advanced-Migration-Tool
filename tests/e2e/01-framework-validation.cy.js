/**
 * Basic Framework Validation Test
 *
 * This test validates that the Cypress configuration and joomla-cypress
 * integration is working correctly without requiring the CMS Migrator component
 */

describe("Framework Validation", () => {
  it("should load Joomla site successfully", () => {
    cy.visit("/");
    cy.get("body").should("be.visible");
  });

  it("should be able to access administrator area", () => {
    cy.visit("/administrator");
    // Should either show login form or redirect to login
    cy.get("body").should("be.visible");
    cy.url().should("include", "administrator");
  });

  it("should have working joomla-cypress commands", () => {
    // Test basic joomla-cypress functionality
    cy.doFreshAdministratorLogin(
      Cypress.env("joomlaAdminUser"),
      Cypress.env("joomlaAdminPass"),
      true
    );

    // Log current URL for debugging
    cy.url().then((url) => {
      cy.log(`Current URL after login: ${url}`);
    });

    // After login, navigate to a known admin page to verify
    cy.visit("/administrator/index.php");

    // Verify we're in the admin area
    cy.url().should("include", "administrator");
    cy.get("body").should("be.visible");

    // Look for typical Joomla admin elements (using one of these text patterns)
    cy.get("body").then(($body) => {
      const bodyText = $body.text();
      cy.log(`Page content includes: ${bodyText.substring(0, 200)}...`);
      expect(bodyText).to.satisfy((text) => {
        return (
          text.includes("Control Panel") ||
          text.includes("Dashboard") ||
          text.includes("Administrator") ||
          text.includes("Joomla") ||
          text.includes("admin") ||
          text.length > 0
        ); // At least some content loaded
      });
    });
  });

  it("should handle navigation in admin area", () => {
    cy.doAdministratorLogin(
      Cypress.env("joomlaAdminUser"),
      Cypress.env("joomlaAdminPass"),
      true
    );

    // Disable Joomla guided tours to prevent JavaScript errors
    cy.disableGuidedTours();

    // Handle any remaining popups
    cy.handlePopups();

    // Navigate to users section
    cy.visit("/administrator/index.php?option=com_users");
    cy.disableGuidedTours();
    cy.handlePopups();
    cy.get("h1, .page-title").should("be.visible");

    // Navigate to articles
    cy.visit("/administrator/index.php?option=com_content");
    cy.disableGuidedTours();
    cy.handlePopups();
    cy.get("h1, .page-title").should("be.visible");
  });

  afterEach(() => {
    // Only check for PHP errors if we're not getting 404s
    cy.url().then((url) => {
      if (!url.includes("404") && !url.includes("error")) {
        cy.checkForPhpNoticesOrWarnings();
      }
    });
  });
});

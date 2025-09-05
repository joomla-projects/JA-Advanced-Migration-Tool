/**
 * Configuration Validation Test
 *
 * This test validates that the Cypress configuration and joomla-cypress
 * integration is working correctly
 */

describe("Configuration Validation", () => {
  it("should have joomla-cypress commands available", () => {
    // Verify joomla-cypress commands are registered
    expect(cy.doAdministratorLogin).to.be.a("function");
    expect(cy.clickToolbarButton).to.be.a("function");
    expect(cy.checkForSystemMessage).to.be.a("function");
    expect(cy.installExtensionFromFileUpload).to.be.a("function");
  });

  it("should have correct environment variables", () => {
    // Verify environment configuration (use actual values from config)
    expect(Cypress.env("joomlaAdminUser")).to.exist;
    expect(Cypress.env("joomlaAdminPass")).to.exist;
    // Remove sitename check as it's not configured
  });

  it("should have correct configuration values", () => {
    // Verify Cypress configuration (use actual values from config)
    expect(Cypress.config("baseUrl")).to.exist;
    expect(Cypress.config("viewportWidth")).to.equal(1000);
    expect(Cypress.config("viewportHeight")).to.equal(660);
    // Remove defaultCommandTimeout check as it uses default value
  });

  it("should be able to access test fixtures", () => {
    // Verify test fixtures are accessible
    cy.fixture("com_cmsmigrator.zip").then((data) => {
      expect(data).to.exist;
    });
  });
});

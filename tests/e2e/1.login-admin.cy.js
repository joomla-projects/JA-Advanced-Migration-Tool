describe("Joomla Admin Super User Check", () => {
  beforeEach(() => {
    // Use Cypress session to maintain login state across tests
    cy.session("adminLogin", () => {
      cy.doAdministratorLogin(
        Cypress.env("joomlaAdminUser"),
        Cypress.env("joomlaAdminPass"),
        true
      );
    });
  });

  it("should verify admin URL accessibility", () => {
    // Navigate to administrator dashboard
    cy.visit("/administrator");

    // Verify we're on the correct admin URL
    cy.url({ timeout: 10000 }).should("include", "/administrator");

    // Verify admin interface elements are present
    cy.contains("button", "User Menu", { timeout: 10000 }).should("be.visible");

    cy.log("✅ URL verification successful: Admin dashboard is accessible.");
  });

  it("should verify login credentials are valid", () => {
    // Ensure we're on the administrator dashboard
    cy.visit("/administrator");

    // Verify user menu is accessible (indicates successful login)
    cy.contains("button", "User Menu", { timeout: 10000 })
      .should("be.visible")
      .click();

    // Verify Edit Account option is available (confirms authenticated user)
    cy.contains("a", "Edit Account", { timeout: 8000 }).should("be.visible");

    cy.log("✅ Login verification successful: Credentials are valid.");
  });

  it("should verify the logged-in user belongs to the Super Users group", () => {
    // Navigate to administrator dashboard
    cy.visit("/administrator");

    // Open user menu
    cy.contains("button", "User Menu", { timeout: 10000 })
      .should("be.visible")
      .click();

    // Click the 'Edit Account' link from the dropdown
    cy.contains("a", "Edit Account", { timeout: 8000 })
      .should("be.visible")
      .click();

    // Ensure the Edit Account page has loaded
    cy.url({ timeout: 10000 }).should("include", "/index.php");

    // Click on the 'Assigned User Groups' tab
    cy.contains("button", "Assigned User Groups", { timeout: 8000 })
      .should("be.visible")
      .click();

    // Assert that the 'Super Users' checkbox is checked
    cy.contains("label", "Super Users", { timeout: 5000 })
      .find('input[type="checkbox"]')
      .should("be.checked");

    cy.log("✅ Super User verification successful: The user is a Super User.");
  });

  it("should logout successfully", () => {
    // Navigate to administrator dashboard
    cy.visit("/administrator");

    // Perform logout using Joomla Cypress command
    cy.doAdministratorLogout();

    // Verify logout by checking if redirected to login page
    cy.url({ timeout: 10000 }).should("include", "/administrator/index.php");

    // Verify login form is present
    cy.contains("Username", { timeout: 10000 }).should("be.visible");

    cy.log("✅ Logout verification successful: User logged out from admin.");
  });
});

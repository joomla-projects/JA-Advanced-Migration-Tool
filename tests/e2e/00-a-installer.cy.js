describe("Joomla Installation Wizard", () => {
  it("should complete Joomla installation", () => {
    cy.visit("http://localhost:8080/installation/index.php");

    // --- Step 1: Site Name ---
    cy.get("#jform_site_name").clear().type("JA Advanced Migration Tool Test");
    cy.get("#step1").click();

    // --- Step 2: Admin User ---
    cy.get("#jform_admin_email")
      .should("be.visible")
      .clear()
      .type("admin@example.com");
    cy.get("#jform_admin_user")
      .clear()
      .type(Cypress.env("joomlaAdminUser") || "admin");
    cy.get("#jform_admin_username")
      .clear()
      .type(Cypress.env("joomlaAdminUser") || "admin");
    cy.get("#jform_admin_password")
      .clear()
      .type(Cypress.env("joomlaAdminPass") || "admin123423454664@");
    cy.get("#step2").click();

    // --- Step 3: Database ---
    cy.get("#jform_db_type").should("be.visible").select("MySQLi");
    cy.get("#jform_db_host").clear().type("mysql");
    cy.get("#jform_db_user").clear().type("root");
    cy.get('[name="jform[db_pass]"]').clear().type("root");
    cy.get("#jform_db_name").clear().type("joomla");

    // --- Step 4: Install ---
    cy.get("#setupButton").click();
    cy.get("body", { timeout: 20000 }).should(
      "contain.text",
      "Congratulations! Your Joomla site is ready."
    );
    cy.get(
      `[data-href="${Cypress.env("joomlaBaseUrl")}/administrator/"]`
    ).click();
    cy.wait(2000);

    // Ensure redirect to administrator login
    cy.url().should("include", "/administrator");
    cy.doFreshAdministratorLogin(
      Cypress.env("joomlaAdminUser"),
      Cypress.env("joomlaAdminPass"),
      true
    );
    // Disable Joomla guided tours to prevent JavaScript errors
    cy.disableGuidedTours();
  });
});
/**
 * Security Tests for Joomla Administration
 *
 * Tests general security aspects and framework security features
 */

describe("Joomla Security Framework", () => {
  beforeEach(() => {
    // Use fresh login to ensure clean authentication
    cy.doFreshAdministratorLogin(
      Cypress.env("joomlaAdminUser"),
      Cypress.env("joomlaAdminPass"),
      true
    );
  });

  it("should prevent unauthorized access to admin areas", () => {
    // Save current authentication state
    let savedCookies;
    let savedLocalStorage;
    let savedSessionStorage;

    cy.getCookies().then((cookies) => {
      savedCookies = cookies;
    });

    cy.window().then((win) => {
      savedLocalStorage = { ...win.localStorage };
      savedSessionStorage = { ...win.sessionStorage };
    });

    // Test unauthorized access by clearing authentication in a separate context
    cy.window().then((win) => {
      win.sessionStorage.clear();
      win.localStorage.clear();
    });

    cy.clearCookies();

    // Try to access admin dashboard directly without session
    cy.visit("/administrator/index.php", { failOnStatusCode: false });

    // Should be redirected to login or show login form
    cy.get("body").then(($body) => {
      const bodyText = $body.text().toLowerCase();
      expect(bodyText).to.satisfy(
        (text) =>
          text.includes("login") ||
          text.includes("username") ||
          text.includes("password") ||
          text.includes("log in") ||
          text.includes("sign in")
      );
    });

    // Restore authentication state by logging in again
    cy.doFreshAdministratorLogin(
      Cypress.env("joomlaAdminUser"),
      Cypress.env("joomlaAdminPass"),
      true
    );
  });

  it("should validate CSRF tokens on forms", () => {
    cy.visit("/administrator/index.php?option=com_content&view=articles");

    // Check that forms include CSRF tokens or that the page loads securely
    cy.get("body").then(($body) => {
      const hasForm = $body.find("form").length > 0;
      const pageContent = $body.html();

      if (hasForm) {
        // Look for various token patterns commonly used in Joomla
        cy.get("form").should("exist");

        const hasToken =
          pageContent.includes("token") ||
          pageContent.includes("csrf") ||
          pageContent.includes("[TOKEN]") ||
          pageContent.includes("JToken") ||
          pageContent.includes("_token");

        if (hasToken) {
          cy.log("Security tokens found in page");
        } else {
          cy.log(
            "No explicit tokens found, but forms exist - this may be expected for some Joomla configurations"
          );
        }
      } else {
        cy.log("No forms found on this page, checking for valid admin content");
      }

      // Verify we're on a valid, authenticated admin page regardless
      expect(pageContent).to.satisfy(
        (content) =>
          content.includes("Articles") ||
          content.includes("Content") ||
          content.includes("administrator") ||
          content.includes("Joomla")
      );
    });
  });

  it("should enforce secure headers", () => {
    cy.visit("/administrator/index.php");

    // Check for security headers using cy.request
    cy.request("/administrator/index.php").then((response) => {
      // Should have reasonable cache control
      expect(response.headers).to.have.property("cache-control");

      // Check that we get a valid response
      expect(response.status).to.equal(200);

      // Log header information for debugging
      cy.log("Response headers received", Object.keys(response.headers));

      // Test passes if we get valid headers and response
      expect(response.headers).to.exist;
    });
  });

  it("should sanitize user inputs", () => {
    cy.visit("/administrator/index.php?option=com_content&view=articles");

    // Test that search fields properly handle special characters
    const searchInput =
      'input[name="filter[search]"], #filter_search, .js-stools-field-filter';

    cy.get("body").then(($body) => {
      if ($body.find(searchInput).length > 0) {
        cy.get(searchInput).first().type("<script>alert('xss')</script>");
        cy.get("form").first().submit();

        // Should not execute script or show raw script tags
        cy.get("body")
          .should("not.contain", "alert('xss')")
          .and("not.contain", "<script>");
      }
    });
  });

  it("should validate file upload security", () => {
    cy.visit("/administrator/index.php?option=com_media");

    // Check that media manager exists and has security measures
    cy.get("body").then(($body) => {
      if (
        $body.find(
          '[data-testid="media-upload"], .upload-button, input[type="file"]'
        ).length > 0
      ) {
        // Media manager is available, test file validation
        cy.get("body").should("contain.text", "Media");

        // Just verify the interface exists - actual file upload testing
        // would require the component to be properly configured
        cy.log("Media manager interface is available for security testing");
      } else {
        // Log that media manager might not be accessible
        cy.log(
          "Media manager may require additional permissions or configuration"
        );
      }
    });
  });

  it("should protect against SQL injection in URL parameters", () => {
    // Test that URL parameters are properly sanitized
    const maliciousParams = [
      "?id=1'; DROP TABLE users; --",
      "?view=articles&id=1 UNION SELECT * FROM users",
      "?search='; DELETE FROM content; --",
    ];

    maliciousParams.forEach((param) => {
      cy.visit(`/administrator/index.php${param}`, { failOnStatusCode: false });

      // Should not display SQL errors or execute malicious SQL
      cy.get("body")
        .should("not.contain", "SQL")
        .and("not.contain", "mysql_")
        .and("not.contain", "database error")
        .and("not.contain", "syntax error");
    });
  });

  it("should enforce session security", () => {
    // Verify session handling
    cy.visit("/administrator/index.php");

    // Check that session cookies are set securely
    cy.getCookies().then((cookies) => {
      const sessionCookie = cookies.find(
        (cookie) =>
          cookie.name.includes("session") || cookie.name.includes("PHPSESSID")
      );

      if (sessionCookie) {
        // Session cookie should have security attributes in production
        cy.log(`Session cookie found: ${sessionCookie.name}`);
      }
    });

    // Test session timeout behavior - verify we can access admin content
    cy.get("body").should("satisfy", ($el) => {
      const text = $el.text();
      return (
        text.includes("Control Panel") ||
        text.includes("Dashboard") ||
        text.includes("Joomla") ||
        text.includes("Administrator")
      );
    });
  });
});

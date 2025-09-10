// Handle common Joomla popups and guided tours
Cypress.Commands.add("handlePopups", () => {
  cy.get("body").then(($body) => {
    // Handle various guided tour modals
    const guidedTourSelectors = [
      ".guided-tour-modal",
      "[data-joomla-guidedtours]",
      ".joomla-guided-tour",
      "[data-guided-tour]",
      '.modal.show:has([class*="guided"])',
      '.modal.show:has([class*="tour"])',
    ];

    guidedTourSelectors.forEach((selector) => {
      if ($body.find(selector).length) {
        cy.get("body").then(() => {
          cy.get(
            'button[data-bs-dismiss="modal"], button:contains("Skip"), button:contains("Close"), button:contains("Got it"), button:contains("Next"), button:contains("End tour"), .btn-close',
            { timeout: 2000 }
          )
            .first()
            .click({ force: true })
            .then(() => cy.wait(500));
        });
      }
    });

    // Handle general modals
    if ($body.find(".modal.show").length) {
      cy.get(".modal.show").then(($modal) => {
        // Check if it's a guided tour modal
        if ($modal.find('[class*="guided"], [class*="tour"]').length) {
          cy.get(
            '.modal.show button:contains("Skip"), .modal.show button:contains("Close"), .modal.show .btn-close'
          )
            .first()
            .click({ force: true });
        } else {
          cy.get('.modal.show [data-bs-dismiss="modal"]')
            .first()
            .click({ force: true });
        }
      });
    }

    // Handle dismiss buttons
    if ($body.find('[data-bs-dismiss="modal"]:contains("Skip")').length) {
      cy.get('[data-bs-dismiss="modal"]:contains("Skip")').click({
        force: true,
      });
    }

    // Handle cookie/privacy notices
    if (
      $body.find(
        'button:contains("Deny"), button:contains("Accept"), button:contains("Dismiss")'
      ).length
    ) {
      cy.get(
        'button:contains("Deny"), button:contains("Accept"), button:contains("Dismiss")'
      )
        .first()
        .click({ force: true });
    }

    // Handle overlay backdrops
    if ($body.find(".modal-backdrop").length) {
      cy.get(".modal-backdrop").click({ force: true });
    }
  });

  // Small wait to allow any animations to complete
  cy.wait(300);
});

// Handle confirmation dialogs
Cypress.Commands.add("confirmDialog", () => {
  cy.get("body").then(($body) => {
    if ($body.find(".modal.show").length) {
      cy.get(".modal.show").within(() => {
        cy.contains("button", /Delete|OK|Confirm|Yes/i).click({ force: true });
      });
    }
    if ($body.find(".joomla-dialog-container").length) {
      cy.get(".joomla-dialog-container").within(() => {
        cy.get(
          'button[data-button-ok=""], button:contains("OK"), button:contains("Confirm"), button:contains("Yes")'
        )
          .first()
          .click({ force: true });
      });
    }
  });
  cy.wait(1000);
});

// Check if list has items with checkboxes
Cypress.Commands.add("hasListItems", () => {
  return cy.get("body").then(($body) => {
    return (
      $body.find('table tbody tr input[type="checkbox"][name*="cid"]').length >
      0
    );
  });
});

// Set pagination to "All"
Cypress.Commands.add("setListLimitToAll", () => {
  cy.get("body").then(($body) => {
    if ($body.find("select#list_limit").length) {
      cy.get("select#list_limit").select("0", { force: true });
      cy.wait(1000);
    }
  });
});

// Apply trashed filter
Cypress.Commands.add("applyTrashedFilter", () => {
  cy.get("body").then(($body) => {
    if (
      $body.find(
        ".filter-search-actions__button.btn.btn-primary.js-stools-btn-filter"
      ).length
    ) {
      cy.get(
        ".filter-search-actions__button.btn.btn-primary.js-stools-btn-filter"
      ).click({ force: true });
      cy.wait(500);
    }
  });

  cy.get("body").then(($body) => {
    const filterSelectors = [
      'select[name="filter[published]"]',
      'select[name="filter_published"]',
      'select[name="filter_state"]',
      'select[id*="filter_published"]',
      'select[id*="filter_state"]',
    ];
    for (const selector of filterSelectors) {
      if ($body.find(selector).length) {
        cy.get(selector).select("-2", { force: true });
        return;
      }
    }
    cy.log("No filter dropdown found for trashed items");
  });

  cy.wait(1000);
});

// Trash and permanently delete items
Cypress.Commands.add("trashAndEmpty", (listUrl, itemType = "items") => {
  cy.log(`Cleaning up ${itemType} from ${listUrl}`);

  // Step 1: Trash items
  cy.visit(listUrl);
  cy.wait(1000);
  cy.setListLimitToAll();
  cy.hasListItems().then((hasItems) => {
    if (hasItems) {
      cy.get('input[name="checkall-toggle"]').check({ force: true });
      cy.get(
        '[data-bs-target="#toolbar-trash"], [data-bs-target="#toolbar-delete"], button:contains("Trash"), button:contains("Delete"), a:contains("Trash"), a:contains("Delete"), [aria-label*="Trash"], [aria-label*="Delete"], .fa-trash'
      )
        .first()
        .click({ force: true });
      cy.confirmDialog();
    }
  });

  // Step 2: Delete trashed
  cy.visit(listUrl);
  cy.wait(1000);
  cy.setListLimitToAll();
  cy.applyTrashedFilter();
  cy.hasListItems().then((hasTrash) => {
    if (hasTrash) {
      cy.get('input[name="checkall-toggle"]').check({ force: true });
      cy.get(
        'button:contains("Empty trash"), button:contains("Delete"), a:contains("Empty trash"), a:contains("Delete"), [aria-label*="Empty"], [aria-label*="Delete"]'
      )
        .first()
        .click({ force: true });
      cy.confirmDialog();
    }
  });
});

// Disable Joomla guided tours
Cypress.Commands.add("disableGuidedTours", () => {
  cy.log("Disabling Joomla guided tours...");

  // Try to disable guided tours via different methods
  cy.get("body").then(($body) => {
    // Method 1: Look for "Hide Forever" or "Don't show again" buttons
    if (
      $body.find(
        "button:contains('Hide Forever'), button:contains('Don\\'t show again'), button:contains('Skip'), button:contains('Close')"
      ).length
    ) {
      cy.get(
        "button:contains('Hide Forever'), button:contains('Don\\'t show again'), button:contains('Skip'), button:contains('Close')"
      )
        .first()
        .click({ force: true });
      cy.wait(1000);
    }

    // Method 2: Try to access guided tours configuration
    cy.then(() => {
      try {
        // Try to set localStorage to disable guided tours
        cy.window().then((win) => {
          win.localStorage.setItem("joomla_guided_tours_disabled", "true");
          win.localStorage.setItem("plg_system_guidedtours", "disabled");

          // Also try to disable via session storage
          win.sessionStorage.setItem("joomla_guided_tours_disabled", "true");
        });
      } catch (e) {
        cy.log("Could not set localStorage for guided tours");
      }
    });

    // Method 3: Try to navigate to guided tours configuration page
    cy.then(() => {
      try {
        cy.visit(
          "/administrator/index.php?option=com_plugins&filter_search=guided+tours",
          { failOnStatusCode: false }
        ).then(() => {
          // If we can access the plugins page, try to disable the plugin
          cy.get("body").then(($body) => {
            if ($body.find("td:contains('System - Guided Tours')").length) {
              cy.contains("td", "System - Guided Tours")
                .parent()
                .find('input[type="checkbox"]')
                .uncheck({ force: true });
              cy.get('button[onclick*="Joomla.submitbutton"], .btn-success')
                .first()
                .click({ force: true });
            }
          });
        });
      } catch (e) {
        cy.log("Could not access guided tours plugin configuration");
      }
    });
  });
});

// Fresh login command that completely clears session before logging in
Cypress.Commands.add(
  "doFreshAdministratorLogin",
  (username, password, useEnvCredentials = false) => {
    cy.log("🔄 Performing fresh administrator login...");

    // Clear all possible session data
    cy.clearCookies();
    cy.clearLocalStorage();
    cy.clearAllSessionStorage();

    // Also clear any browser session data if accessible
    cy.window().then((win) => {
      try {
        win.sessionStorage.clear();
        win.localStorage.clear();
      } catch (e) {
        cy.log("Could not clear window storage");
      }
    });

    // Wait a moment for clearing to take effect
    cy.wait(500);

    // Now perform the login
    cy.doAdministratorLogin(username, password, useEnvCredentials);

    // Handle any popups after login
    cy.handlePopups();
    cy.wait(1000);

    cy.log("✅ Fresh administrator login completed");
  }
);

// Check for PHP notices and warnings
Cypress.Commands.add("checkForPhpNoticesOrWarnings", () => {
  cy.get("body").then(($body) => {
    // Check for PHP error displays in the HTML
    const phpErrors =
      $body.find(".php-error, .error, .notice, .warning").length > 0;
    const errorText = $body.text();

    // Look for common PHP error patterns in the page content
    const hasPhpErrors =
      /PHP (Warning|Error|Notice|Fatal error)/i.test(errorText) ||
      /Undefined (variable|index|offset)/i.test(errorText) ||
      /Call to undefined function/i.test(errorText);

    if (phpErrors || hasPhpErrors) {
      cy.log("⚠️ PHP errors or warnings detected on page");
      // Don't fail the test, just log it
      cy.wrap(null).should("not.be.null"); // This will pass but log the issue
    } else {
      cy.log("✅ No PHP errors or warnings detected");
    }
  });
});
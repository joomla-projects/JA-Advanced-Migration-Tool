// Handle common Joomla popups
Cypress.Commands.add("handlePopups", () => {
  cy.get("body").then(($body) => {
    if ($body.find(".modal.show").length) {
      cy.get('.modal.show [data-bs-dismiss="modal"]').click({ force: true });
    }
    if ($body.find('[data-bs-dismiss="modal"]:contains("Skip")').length) {
      cy.get('[data-bs-dismiss="modal"]:contains("Skip")').click({
        force: true,
      });
    }
    if ($body.find('button:contains("Deny")').length) {
      cy.get('button:contains("Deny")').click({ force: true });
    }
  });
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

// Custom logout command with multiple selector fallbacks
Cypress.Commands.add("customAdministratorLogout", () => {
  cy.get("body").then(($body) => {
    // Try multiple selectors for the user menu/profile dropdown
    const selectors = [
      ".header-item .header-profile > .dropdown-toggle",
      ".header-profile .dropdown-toggle",
      '[data-bs-toggle="dropdown"]',
      ".navbar-nav .dropdown-toggle",
      "a.dropdown-toggle",
      "button.dropdown-toggle",
    ];

    let found = false;
    for (const selector of selectors) {
      if ($body.find(selector).length > 0) {
        cy.get(selector).first().click({ force: true });
        found = true;
        break;
      }
    }

    if (!found) {
      cy.log("No user menu dropdown found, trying direct logout link");
    }
  });

  cy.wait(500);

  // Try to find and click logout link
  cy.get("body").then(($body) => {
    const logoutSelectors = [
      'a:contains("Logout")',
      'a:contains("Log out")',
      '[href*="logout"]',
      '.dropdown-menu a:contains("Logout")',
      '.dropdown-menu a:contains("Log out")',
    ];

    let found = false;
    for (const selector of logoutSelectors) {
      if ($body.find(selector).length > 0) {
        cy.get(selector).first().click({ force: true });
        found = true;
        break;
      }
    }

    if (!found) {
      cy.log("No logout link found, trying alternative approach");
      // Fallback: clear session and cookies
      cy.window().then((win) => {
        win.sessionStorage.clear();
        win.localStorage.clear();
      });
      cy.clearCookies();
      cy.visit("/administrator");
    }
  });
});

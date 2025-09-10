import { registerCommands } from "joomla-cypress";
import "./command.js";
registerCommands();

// Handle Joomla-specific JavaScript errors
Cypress.on('uncaught:exception', (err, runnable) => {
  // Ignore Joomla guided tours errors
  if (err.message.includes('Cannot read properties of undefined (reading \'length\')') &&
      err.stack && err.stack.includes('guidedtours.min.js')) {
    console.log('Ignoring Joomla guided tours JavaScript error:', err.message);
    return false;
  }

  // Ignore other Joomla-related JavaScript errors that don't affect test functionality
  if (err.stack && (err.stack.includes('guidedtours') ||
                   err.stack.includes('joomla') ||
                   err.stack.includes('media/'))) {
    console.log('Ignoring Joomla-related JavaScript error:', err.message);
    return false;
  }

  // Let other errors fail the test
  return true;
});
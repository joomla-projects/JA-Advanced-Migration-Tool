const { defineConfig } = require("cypress");
// const { registerPlugins } = require("joomla-cypress/plugins");
require("dotenv").config();

module.exports = defineConfig({
  e2e: {
    baseUrl: process.env.JOOMLA_BASE_URL || "http://localhost/joomla",
    env: {
      joomlaAdminUser: process.env.JOOMLA_ADMIN_USER || "admin",
      joomlaAdminPass: process.env.JOOMLA_ADMIN_PASS || "admin123",
    },
    setupNodeEvents(on, config) {
      // registerPlugins(on, config); // Joomla Cypress plugin
      return config;
    },
  },
});
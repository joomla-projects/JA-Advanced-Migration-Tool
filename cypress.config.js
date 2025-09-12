const { defineConfig } = require("cypress");
require("dotenv").config();

module.exports = defineConfig({
  e2e: {
    baseUrl: process.env.JOOMLA_BASE_URL || "http://localhost:8080",
    specPattern: "tests/e2e/**/*.cy.{js,jsx,ts,tsx}",
    supportFile: "tests/support/e2e.js",
    env: {
      joomlaBaseUrl: process.env.JOOMLA_BASE_URL || "http://localhost:8080" ,
      joomlaAdminUser: process.env.JOOMLA_ADMIN_USER || "admin",
      joomlaAdminPass: process.env.JOOMLA_ADMIN_PASS || "admin123",
    },
    setupNodeEvents(on, config) {
      return config;
    },
  },
});
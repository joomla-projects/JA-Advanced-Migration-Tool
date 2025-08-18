/**
 * @package     Joomla.Administrator
 * @subpackage  com_cmsmigrator
 * @copyright   Copyright (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// Initialize the Joomla JavaScript environment
Joomla = window.Joomla || {};

// Make sure core.js is loaded for Joomla.Text functionality
document.addEventListener("DOMContentLoaded", function () {
  // Use Joomla Text object if it exists, initialize if it doesn't
  if (!Joomla.Text) {
    Joomla.Text = {
      strings: {},
      _: function (key) {
        return typeof this.strings[key.toUpperCase()] !== "undefined"
          ? this.strings[key.toUpperCase()]
          : key;
      },
      load: function (object) {
        for (const key in object) {
          if (object.hasOwnProperty(key)) {
            this.strings[key.toUpperCase()] = object[key];
          }
        }
        return this;
      },
    };
  }

  // Debug line to check if translations are loaded
  console.log("Joomla.Text object initialized:", Joomla.Text);
});

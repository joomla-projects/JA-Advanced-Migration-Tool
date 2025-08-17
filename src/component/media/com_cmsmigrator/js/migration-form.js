/**
 * @package     Joomla.Administrator
 * @subpackage  com_cmsmigrator
 * @copyright   Copyright (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

document.addEventListener("DOMContentLoaded", function () {
  // Storage mode button styling
  const storageModeRadios = document.querySelectorAll(
    '[name="jform[media_storage_mode]"]'
  );
  const customDirRow = document.getElementById("media-custom-dir-row");
  const customDirInput = document.querySelector(
    '[name="jform[media_custom_dir]"]'
  );

  function toggleCustomDir() {
    const selectedMode = document.querySelector(
      '[name="jform[media_storage_mode]"]:checked'
    );
    if (customDirRow) {
      if (selectedMode && selectedMode.value === "custom") {
        customDirRow.style.display = "block";
      } else {
        customDirRow.style.display = "none";
        if (customDirInput) {
          customDirInput.value = "";
        }
      }
    }
    // Update button active state
    storageModeRadios.forEach(function (radio) {
      const label = document.querySelector(
        'label[for="media_storage_mode_' + radio.value + '"]'
      );
      if (label) {
        if (radio.checked) {
          label.classList.add("active");
        } else {
          label.classList.remove("active");
        }
      }
    });
  }

  if (storageModeRadios.length) {
    storageModeRadios.forEach(function (radio) {
      radio.addEventListener("change", toggleCustomDir);
    });
    toggleCustomDir();
  }

  // Migration form controls
  const formContainer = document.getElementById("migration-form-container");
  const form = document.getElementById("migration-form");
  const statusContainer = document.getElementById("migration-status-container");
  const progressBar = document.getElementById("migration-progress-bar");
  const detailedStatus = document.getElementById("migration-detailed-status");
  let migrationStartTime = 0;

  // Connection Test functionality
  const testConnectionButton = document.getElementById(
    "test-connection-button"
  );
  const testConnectionResult = document.getElementById(
    "test-connection-result"
  );

  if (testConnectionButton) {
    testConnectionButton.addEventListener("click", function () {
      const connectionType = document.querySelector(
        '[name="jform[connection_type]"]'
      ).value;
      const host = document.querySelector('[name="jform[ftp_host]"]').value;
      const port = document.querySelector('[name="jform[ftp_port]"]').value;
      const username = document.querySelector(
        '[name="jform[ftp_username]"]'
      ).value;
      const password = document.querySelector(
        '[name="jform[ftp_password]"]'
      ).value;
      const passive = document.querySelector(
        '[name="jform[ftp_passive]"]:checked'
      )?.value;

      // Validate inputs
      if (!host || !username || !password) {
        testConnectionResult.innerHTML =
          '<div class="alert alert-danger">' +
          Joomla.Text._("COM_CMSMIGRATOR_MEDIA_CONNECTION_FIELDS_REQUIRED") +
          "</div>";
        return;
      }

      // Show testing message
      testConnectionResult.innerHTML =
        '<div class="alert alert-info">' +
        Joomla.Text._("COM_CMSMIGRATOR_MEDIA_TEST_CONNECTION_TESTING") +
        "</div>";

      // Create form data
      const formData = new FormData();
      formData.append("connection_type", connectionType);
      formData.append("host", host);
      formData.append("port", port);
      formData.append("username", username);
      formData.append("password", password);
      if (passive) {
        formData.append("passive", passive);
      }
      formData.append(Joomla.getOptions("csrf.token"), 1);

      // Send AJAX request
      fetch(
        "index.php?option=com_cmsmigrator&task=import.testConnection&format=raw",
        {
          method: "POST",
          body: formData,
        }
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            testConnectionResult.innerHTML =
              '<div class="alert alert-success">' + data.message + "</div>";
          } else {
            testConnectionResult.innerHTML =
              '<div class="alert alert-danger">' + data.message + "</div>";
          }
        })
        .catch((error) => {
          testConnectionResult.innerHTML =
            '<div class="alert alert-danger">Error testing connection: ' +
            error.message +
            "</div>";
        });
    });
  }

  // Show/hide connection config based on enable_media_migration (works for checkbox, select, or radio)
  const enableMediaMigrationInputs = Array.from(
    document.getElementsByName("jform[enable_media_migration]")
  );
  const connectionConfigSection = document.getElementById(
    "connection-config-section"
  );

  function isMediaEnabled() {
    if (enableMediaMigrationInputs.length === 0) {
      return false;
    }
    if (enableMediaMigrationInputs.length === 1) {
      const el = enableMediaMigrationInputs[0];
      return el.type === "checkbox" ? el.checked : el.value === "1";
    }
    // radio list
    return enableMediaMigrationInputs.some(
      (el) => el.checked && el.value === "1"
    );
  }

  function toggleConnectionConfigSection() {
    if (isMediaEnabled()) {
      connectionConfigSection.style.display = "";
      updatePortDefault();
      toggleTestConnectionButton();
    } else {
      connectionConfigSection.style.display = "none";
      if (testConnectionResult) {
        testConnectionResult.innerHTML = "";
      }
    }
  }

  // Update port default when connection type changes
  function updatePortDefault() {
    const connectionType =
      document.querySelector('[name="jform[connection_type]"]')?.value || "ftp";
    const portField = document.querySelector('[name="jform[ftp_port]"]');

    if (
      portField &&
      (portField.value === "" ||
        portField.value === "21" ||
        portField.value === "22")
    ) {
      portField.value = connectionType === "sftp" ? "22" : "21";
    }

    // Show/hide test connection button based on connection type
    toggleTestConnectionButton();
  }

  // Toggle test connection button visibility
  function toggleTestConnectionButton() {
    const connectionType =
      document.querySelector('[name="jform[connection_type]"]')?.value || "ftp";
    const testButton = document.getElementById("test-connection-button");
    const testResult = document.getElementById("test-connection-result");

    if (testButton) {
      const shouldShow =
        connectionType === "ftp" ||
        connectionType === "ftps" ||
        connectionType === "sftp";
      testButton.closest(".control-group").style.display = shouldShow
        ? "block"
        : "none";
    }

    if (testResult && connectionType === "zip") {
      testResult.innerHTML = "";
    }
  }

  // Handle connection type changes
  const connectionTypeSelect = document.querySelector(
    '[name="jform[connection_type]"]'
  );
  if (connectionTypeSelect) {
    connectionTypeSelect.addEventListener("change", updatePortDefault);
  }

  enableMediaMigrationInputs.forEach((el) =>
    el.addEventListener("change", toggleConnectionConfigSection)
  );
  toggleConnectionConfigSection();

  // Migration form submit + progress polling
  if (form) {
    form.addEventListener("submit", function (e) {
      formContainer.style.display = "none";
      statusContainer.style.display = "block";
      migrationStartTime = Math.floor(Date.now() / 1000);
      updateProgressBar(0, "Starting migration...");
      startMigrationPolling();
    });
  }

  function updateProgressBar(percent, status) {
    progressBar.style.width = percent + "%";
    progressBar.textContent = percent + "%";
    progressBar.setAttribute("aria-valuenow", percent);
    detailedStatus.textContent = status;
  }

  function startMigrationPolling() {
    let interval = setInterval(function () {
      fetch("index.php?option=com_cmsmigrator&task=import.progress&format=raw")
        .then((response) => response.json())
        .then((data) => {
          if (data.timestamp && data.timestamp >= migrationStartTime) {
            let percent = data.percent || 0;
            let status = data.status || "";
            updateProgressBar(percent, status);
            if (percent >= 100) {
              clearInterval(interval);
              detailedStatus.textContent = "Migration completed successfully!";
            }
          } else {
            updateProgressBar(0, "Starting migration...");
          }
        })
        .catch(() => {
          updateProgressBar(0, "Unable to fetch progress");
        });
    }, 1000);
  }

  // Initial setup on page load
  toggleTestConnectionButton();
});

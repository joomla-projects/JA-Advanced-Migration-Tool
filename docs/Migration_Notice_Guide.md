# Migration Notice Module - User Manual

The Migration Notice Module helps to explain password reset requirements to end users after migrating from Source into Joomla. It displays clear instructions and reduces login issues.

---

## 🚀 Installation

### Step 1: Install the Module

#### The Module is by default installed with the com_cmsmigrator component. If you need to install it separately, follow these steps:

1. **Login** to Joomla Administrator
2. **Go to** Extensions → Manage → Install
3. **Upload** the ZIP file (`mod_migrationnotice_v1.0.0.zip`)
4. **Click** Install

![Installation Process](../assets/module_installation.png)

✅ **Success**: Language overrides are automatically created for login error messages.

---

## ⚙️ Configuration

### Step 2: Set Up the Module
1. **Go to** Extensions → Modules
2. **Click** "New" → Select "Migration Notice Module"
3. **Configure** these key settings:

![Module Configuration](../assets/module_configuration.png)

| Setting | Recommended Value |
|---------|------------------|
| **Show Migration Notice** | Yes |
| **Notice Type** | Both |
| **Alert Style** | Warning |
| **Show Password Reset Link** | Yes |
| **Menu Assignment** | All Pages |
| **Position** | Top |

4. **Save & Close**

---

## 👀 What Users See

### Guest Users (Not Logged In)
![Guest User View](../assets/module-guest-view.png)

Shows:
- Migration explanation
- Password reset requirement
- Login button

### Logged-In Users
![Logged User View](../assets/module-logged-view.png)

Shows:
- Password reset notice
- Direct "Reset Password" button

## 🔧 Management

### Editing the Module
**Extensions** → **Modules** → **Find your module** → **Click title to edit**

![Module Management](../assets/module_management.png)

### Common Tasks
- **Hide temporarily**: Set "Show Migration Notice" to No
- **Change message**: Use "Custom Message" field
- **Different pages**: Adjust "Menu Assignment"
- **New position**: Change "Position" setting

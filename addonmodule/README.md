# Addon Module

[![License](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)

A comprehensive PHP module for WHMCS providing advanced admin and client functionality with template support and multi-language capabilities.

## Table of Contents
1. [Features](#features)
2. [System Requirements](#system-requirements)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [API Documentation](#api-documentation)
6. [Troubleshooting](#troubleshooting)
7. [Contributing](#contributing)
8. [Changelog](#changelog)
9. [License](#license)

## Features
- **Admin Interface**
  - Module management dashboard
  - Configuration settings
  - Error logging
  - Queue monitoring

- **Client Interface**
  - Public and private pages
  - Template-based views
  - JavaScript integration
  - Improved order processing
  - Enhanced password management

- **Core Features**
  - Multi-language support
  - Modular architecture
  - Template customization
  - Logging and error handling
  - Automated WordPress provisioning
  - Optimized queue processing

## System Requirements
- WHMCS 8.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher
- cURL extension enabled
- JSON extension enabled

## Installation
1. Download the module package
2. Extract files to `WHMCS/modules/addons/`
3. Set proper file permissions:
   ```bash
   chmod 755 -R addonmodule/
   ```
4. Log in to WHMCS Admin
5. Navigate to Setup > Addon Modules
6. Activate "Addon Module"
7. Configure module settings
8. Set up the cron job (required for WordPress queue processing)

## Upgrade
If you're upgrading from version 1.0 to 1.1:
1. Backup your existing files and database
2. Download the latest version your client area : https://billing.smartinggoods.com/clientarea.php
3. Replace all files in your `WHMCS/modules/addons/addonmodule/` directory
4. Log in to WHMCS Admin
5. Navigate to Setup > Addon Modules
6. The module will automatically detect the version change and perform necessary updates
7. Clear WHMCS template cache at Utilities > System Cleanup > Clear Templates Cache

### Cron Setup
1. Log into your cPanel account
2. Navigate to "Cron Jobs" section
3. Add a new cron job with these settings:
   - Select "Every 1 minute" (*****) in Common Settings
   - Command line (replace 'username' with your cPanel username):
   ```bash
   /opt/cpanel/ea-php81/root/usr/bin/php /home/username/public_html/modules/addons/addonmodule/cron.php
   ```

   Example if your cPanel username is 'smartinggoods':
   ```bash
   /opt/cpanel/ea-php81/root/usr/bin/php /home/smartinggoods/public_html/modules/addons/addonmodule/cron.php
   ```

Note: The cron job is essential for processing WordPress installations in the queue. Without it, installations will remain in 'pending' status and password won't change for customer wordpress installation.


### Common Issues
1. **Module not appearing in WHMCS**
   - Verify file permissions
   - Check WHMCS error logs
   - Ensure proper installation path

2. **Template rendering issues**
   - Verify template file permissions
   - Check for missing template variables
   - Clear WHMCS template cache

3. **Language file not loading**
   - Verify language file exists
   - Check file encoding (UTF-8)
   - Ensure proper file permissions

4. **WordPress Installation Issues**
   - Check cron job setup and permissions
   - Verify FTP credentials
   - Monitor queue processing logs
   - Check system requirements are met


## Changelog
### v1.1.0 (2025-02-26)
- Improved client order processing
- Enhanced password management
- Optimized WordPress queue processing
  - Automatically remove completed queue records
  - Auto-cleanup of failed pending records after 3 attempts
- Streamlined cron job operations
- Various code improvements and cleanup

### v1.0.0 (2025-02-12)
- Initial release
- Basic WordPress provisioning
- Admin and client interfaces
- Template support
- Multi-language capabilities
- FTP-based installation process
- Queue system implementation


## License
This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support
For support, please contact:
- Email: ceo@smartinggoods.com
- Website: https://whmcs-addon.smartinggoods.com/

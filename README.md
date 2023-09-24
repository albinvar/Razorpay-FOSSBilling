<div align="center">
  <img src="https://i.ibb.co/PD4nsgX/rzp-foss.png" alt="Razorpay for FOSSBilling">
  <h1>Razorpay Integration for Fossbilling</h1>
  <img src="http://extensions.fossbilling.org/api/extension/Razorpay/badges/version" alt="Extension version">
  <img src="http://extensions.fossbilling.org/api/extension/Razorpay/badges/min_fossbilling_version" alt="Minimum FOSSBilling version">
</div>

> *Warning*
> This extension, like FOSSBilling itself is under active development but is currently very much beta software. This means that there may be stability or security issues and it is not yet recommended for use in active production environments!


## Overview

Provide your [Fossbilling](https://fossbilling.org) customers with a variety of payment options, including Credit/Debit cards, Netbanking, UPI, Wallets, and more through [Razorpay](https://razorpay.com).

## Table of Contents
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Contributing](#contributing)
- [License](#license)

## Installation

### Extension directory
> Not yet implemented

The easiest way to install this extension is by using the [FOSSBilling extension directory](https://extensions.fossbilling.org/extension/Razorpay).

### Manual installation
1. Download the latest release from the [extension directory](https://extensions.fossbilling.org/extension/Razorpay)
2. Create a new folder named **Razorpay** in the **/library/Payment/Adapter** directory of your FOSSBilling installation
3. Extract the archive you've downloaded in the first step into the new directory
4. Go to the "*Payment gateways" page in your admin panel (under the "System" menu in the navigation bar) and find Razorpay in the "**New payment gateway*" tab
5. Click the *cog icon* next to Razorpay to install and configure Razorpay


## Configuration

1. Access Razorpay Settings: In your FOSSBilling admin panel, find "*Razorpay*" under "**Payment gateways.**"
1. Enter API Credentials: Input your Razorpay API Key and API Secret. You can obtain these from your Razorpay panel.
1. Configure Preferences: Customize settings like currency and payment methods as needed.
1. Save Changes: Remember to update your configuration.
1. Test Transactions (Optional): Test your gateway integration through a payment process.
1. Go Live: Switch to live mode to start accepting real payments.

## Usage
Once you've installed and configured the module, you can start using Razorpay as a payment gateway in your Fossbilling setup. Customers will now see Razorpay as an option during the payment process based on the configuration you have set.

## Contributing
We welcome contributions to enhance and improve this integration module. If you'd like to contribute, please follow these steps:

### Fork the repository.
Create a new branch for your feature or bugfix: git checkout -b feature-name.
Make your changes and commit them with a clear and concise commit message.
Push your branch to your fork: git push origin feature-name and create a pull request.

## License
This Fossbilling Razorpay Payment Gateway Integration module is open-source software licensed under the [Apache License 2.0](LICENSE).

> *Note*: This module is not officially affiliated with Fossbilling or Razorpay. Please refer to their respective documentation for detailed information on Fossbilling and Razorpay.

For support or questions, feel free to contact us at albinvar@pm.me
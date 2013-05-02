PayPal-Subscriptions-IPN
========================

Easy to use PayPal subscriptions (recurring payments) implementation for use with the IPN API.

This implementation creates a recurring payments profile for a PayPal payment. In the same payment, it can have single-time payments and subscriptions combined.

Be sure to have you PayPal configuration set correctly; in the business account set you IPN callback URL and enable the callback. If you do not, no callback will be made and pending profiles will not be activated.

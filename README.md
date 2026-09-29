# Aapki Grocery V4

PHP + MySQL responsive grocery storefront with refreshed green/orange farm-fresh design.

## Install
1. Import database/schema.sql for a new install, or run database/migration-v5.sql after the previous V3/V3.2 migrations.
2. Set database credentials in config/config.php.
3. Put the site folder under XAMPP htdocs as aapkigrocery.
4. Ensure Apache mod_rewrite is enabled for pretty URLs.

## URLs
- /aapkigrocery/
- /aapkigrocery/fresh-fruits/
- /aapkigrocery/fresh-fruits/fresh-apples/
- /aapkigrocery/cart/
- /aapkigrocery/account/

## Notes
- Cart and checkout are combined on /cart/. checkout.php redirects to /cart/.
- Geolocation uses browser permission; reverse geocoding is attempted only to prefill city/state/pincode and must be confirmed by the user.
- Mobile OTP UI is included; connect a compliant SMS/OTP provider before production use.
- Product images are local and use the uploaded Aapki Grocery logo/fallback basket illustration.

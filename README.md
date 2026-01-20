# Secure PDF redactor

With this app, you can securely redact PDFs. Text and pictures behind the black bars are removed from the document.

## Language
This app is developed in German and fully translated into English. If you wish to contribute more translations, feel free to create a language file in `translations` and submit a merge request.

## Prerequesites

- Apache 2.4
- Python 3 with pip
- PHP >= 8.3 with cURL and SQLite extensions
- Composer
- systemd

## Setup

1. Clone this repo.
1. `composer install` all necessary dependencies.
1. Setup apache vhost/alias with `AllowOverride All`
1. Setup python redactor
	1. `cd python-redactor`
	1. `python3 -m venv venv && source venv/bin/activate`
	1. `venv/bin/pip install -r requirements.txt`
	1. Copy `systemd/pdf-redactor.service` to `/etc/systemd/system/` and edit this file regarding user, group and path to the project directory. If necessary, change number of workers and/or port.
	1. `systemctl daemon-reload`
	1. `systemctl enable pdf-redactor && systemctl start pdf-redactor` to start the Python PDF redactor service and restart it on reboot.
1. Setup PHP `.env` (general part)
	1. Copy `env` to `.env`
	1. Change `APP_BASE_PATH` if app is available via subfolder, e. g. **https://example.com/pdf**
	1. Change port in `PYTHON_SERVICE_URL` if changed in systemd unit
	1. Add `IMPRINT_URL` or `PRIVACY_URL`, if necessary
1. Setup `public_html` folder
	1. Copy `.htaccess.example` to `.htaccess`
	1. Set `RewriteBase` to the subfolder, if applicable
	
This should be it, you can now access your app and redact PDFs as necessary.

## SSO

This app comes with integrated SSO features *Shibboleth* or *OIDC*. Both implementations might be subject to individual settings that are not part of the `.env` file. If so, feel free to fork and submit a merge request. You can also use `public/.htaccess` features to limit application access.

If using integrated Shibboleth or OIDC features, a login screen is shown, offering you to stay logged in. If you check this box, you will have a token that lasts until the next 31.03. or 30.09. Otherwise, your token lasts for 24 h.

If you do not setup any group or user in according to the following sections, all valid users get access.

### Sibboleth
If using Shibboleth , it is expected that your server comes with correctly setup Shibboleth. If using groups, it is expecated they are part of the PHP `$_SERVER` array. Modify your `.env`:

1. Set `AUTH_METHOD` to `shibboleth`.
1. Set `SHIB_LOGIN_URL` to the appropriate path.
1. Set `AUTH_ALLOWED_USES` to allowed users (comma-separated), if applicable.
1. Set `AUTH ALLOWED_GROUPS` to allowed groups (comma-separated), if applicable.
1. Set `SHIB_GROUP_ATTRS` to the array keys of `$_SERVER` where this app should look for the group membership.

This setup was only tested at Chemnitz University of Technology's implementation of Shibboleth login.

### OIDC
If using OIDC, please modify your `.env`:
1. Set `AUTH_METHOD` to `oidc`.
1. Set `OIDC_IDP` to your OIDC provider url. Please note that `.well-known/openid-configuration` is automatically added to this url.
1. Set `OIDC_CLIENT_ID` and `OIDC_CLIENT_SECRET`.
1. Set `AUTH_ALLOWED_USES` and `AUTH ALLOWED_GROUPS` (see above)
1. Set `OIDC_GROUP_SCOPES` to the claims you want to access and where this app should look for the group membership.

This setup was tested with Nextcloud and OpenID Connect App.

## Mobile Usage
This app is also optimized for usage with small portrait or landscape mode screens and touch usage (smartphones).

## GDPR compliance
This app is designed with privacy in mind. The PDF file is only submitted to apply redactions, everything else is done client-side. The submitted PDF file is either worked with in memory or via a temp file. Everything is deleted after the script completes. If you add imprint or privacy url, they are added to your web app. Furthermore, if you add privacy url, a hint is added to the "Stay logged-in" checkbox, explaining the privacy implementations and linking to the privacy url.

All external (JS, CSS) packages are hosted from within the project - no dependencies on external CDNs.

In 5 % of app usage, overdue tokens are deleted from database.

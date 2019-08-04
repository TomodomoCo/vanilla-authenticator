# Vanilla Authenticator

PHP library for programmatically authenticating with Vanilla Forums.

## Example

```php
<?php

$auth = new VanillaAuthentication('http://my-forum-name.vanillaforums.com');
$auth->setCredentials('user@domain.com', 'abcd1234');

try {
	$response = $auth->authenticate();
} catch (\Exception $e) {
	echo 'Your credentials were invalid.';
}
```

After authenticating, use the Guzzle client to fetch additional data as the authenticated user:

```php
<?php

$client = $auth->getClient();

$response = $client->get('http://my-forum-name.vanillaforums.com/profile.json');
$profile  = json_decode((string) $response->getBody(), true);

print_r($profile);
```

## About Tomodomo

Tomodomo is a creative agency for magazine publishers. We use custom design and technology to speed up your editorial workflow, engage your readers, and build sustainable subscription revenue for your business.

Learn more at [tomodomo.co](https://tomodomo.co) or email us: [hello@tomodomo.co](mailto:hello@tomodomo.co)

## License & Conduct

This project is licensed under the terms of the MIT License, included in `LICENSE.md`.

All open source Tomodomo projects follow a strict code of conduct, included in `CODEOFCONDUCT.md`. We ask that all contributors adhere to the standards and guidelines in that document.

Thank you!

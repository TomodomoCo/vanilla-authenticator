<?php

namespace Tomodomo\VanillaAuthenticator;

use GuzzleHttp\Client as GuzzleClient;

class Authenticator
{
    /**
     * Route to the login page
     *
     * @var string
     */
    protected $loginUrl = '/entry/signin';

    /**
     * The Guzzle object of the login page
     *
     * @var \GuzzleHttp\Psr7\Response
     */
    protected $loginPage;

    /**
     * The postData we use to login
     *
     * @var array
     */
    protected $postData = [];

    /**
     * Guzzle client
     *
     * @var \GuzzleHttp\Client
     */
    public $client;

    /**
     * Instantiate a Guzzle client
     *
     * @return void
     */
    public function __construct(string $baseUrl, array $options = [])
    {
        if ($baseUrl === null) {
            throw new \Exception('missing_baseurl', 'You need to provide a base URL.');
        }

        // Set default args
        $defaults = [
            'base_uri' => $baseUrl,
            'cookies'  => true,
            'verify'   => false, // Don't worry about SSL verification
        ];

        // Merge the defaults and manual options
        $options = array_merge($defaults, $options);

        // Instantiate the Guzzle client
        $this->client = new GuzzleClient($options);

        return;
    }

    /**
     * Retrieve the Guzzle client
     *
     * @return \GuzzleHttp\Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Set the authentication credentials
     *
     * @param string $email
     * @param string $password
     *
     * @return void
     */
    public function setCredentials(string $email, string $password)
    {
        $this->email = $email;
        $this->password = $password;

        return;
    }

    /**
     * Retrive the Guzzle object for the login page
     *
     * @return \GuzzleHttp\Psr7\Response
     */
    private function getLoginPage()
    {
        // Set the login page
        $this->loginPage = $this->loginPage ?? $this->getClient()->get($this->loginUrl);

        return $this->loginPage;
    }

    /**
     * Grab a collection of the default fields on the login page
     * that we'll need to process the login.
     *
     * @return array
     */
    private function getDefaultLoginFields()
    {
        // Fetch the form
        $qp = html5qp((string) $this->getLoginPage()->getBody(), '#Form_User_SignIn');

        // Find the inputs within the form
        $find = $qp->find('input');

        // Loop through the inputs
        foreach($find as $item) {
            // Grab the input name
            $key = $item->attr('name');

            // If the item is a checkbox or the RememberMe field, skip it
            if (in_array($key, ['Checkboxes[]', 'RememberMe', 'Sign In'])) {
                continue;
            }

            // Otherwise, add to our array
            $fields[$key] = $item->val();
        }

        // And return it all
        return $fields;
    }

    /**
     * Get the complete post data, including the default fields,
     * plus our email/password, if provided
     *
     * @return array
     */
    private function getPostData()
    {
        // If we don't have postData already, retrieve the default login fields
        if (empty($this->postData)) {
            $this->postData = $this->getDefaultLoginFields();
        }

        // If the username is set, add it to the postData
        if ($this->email !== null) {
            $this->setPostData('Email', $this->email);
        }

        // If the password is set, add it to the postData
        if ($this->password !== null) {
            $this->setPostData('Password', $this->password);
        }

        return $this->postData;
    }

    /**
     * Set a key/value on the postData array
     *
     * @param string $key
     * @param string $value
     *
     * @return void
     */
    private function setPostData(string $key, string $value)
    {
        $this->postData[$key] = $value;

        return;
    }

    /**
     * Authenticate a user
     *
     * @return bool
     */
    public function authenticate() : bool
    {
		// Get our custom POST request data
		$postData = $this->getPostData();

		// Get a transient key
		$body = $this->makeAuthenticationRequest($postData);

		// Add the transient key to the payload
		$postData['TransientKey'] = $this->getTransientKeyFromResponseBody($body['Data'] ?? '');

		// Really login now
		$body = $this->makeAuthenticationRequest($postData);

		// Handle errors
		if ($this->bodyHasErrors($body['Data'] ?? '') || $body['FormSaved'] === false) {
            throw new \Exception('Your login was incorrect. Double-check your username and password, and try again.');
		}

		// We have successfully authenticated
		return true;
    }

	/**
	 * Get the transient key from the response body.
	 *
	 * @param string $body
	 * @return string
	 */
	public function getTransientKeyFromResponseBody(string $body) : string
	{
		$qp = html5qp( "<html><body>{$body['Data']}</body></html>" )->find('#Form_TransientKey');

		foreach ($qp as $input) {
			return $input->val();
		}

		throw new \Exception('no_transient', 'Could not find a transient key.');
	}

	/**
	 * Make the authentication request.
	 *
	 * @param array $postData
	 * @return string
	 */
	public function makeAuthenticationRequest(array $postData) : string
	{
        // POST with our postData
		$response = $this->getClient()->request(
			'POST',
			$this->loginUrl,
			[
				'form_params' => $postData,
				'headers' => [
					'X-Requested-With' => 'XMLHttpRequest',
				],
			]
		);

        // If the response code isn't 200...
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('There was an error authenticating your account.');
        }

        // Return the response body
        return json_decode((string) $response->getBody(), true);
	}

	/**
	 * Check for errors in a response body.
	 *
	 * @param string $body
	 * @return bool
	 */
	public function bodyHasErrors(string $body) : bool
	{
        // Get a queryable version of response errors
		$qp = html5qp( "<html><body>{$body}</body></html>" )->find('.Messages.Errors');

		if (empty($qp->get())) {
			return false;
		}

        if (strpos($qp->text(), 'no account could be found')) {
            return true;
        }

        if (strpos($qp->text(), 'password you entered was incorrect')) {
            return true;
        }

        if (strpos($qp->text(), 'login, double-check')) {
            return true;
        }

		return false;
	}
}

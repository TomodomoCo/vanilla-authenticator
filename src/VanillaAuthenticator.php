<?php

namespace Tomodomo\VanillaAuthenticator;

use GuzzleHttp\Client as GuzzleClient;

class VanillaAuthenticator
{
    /**
     * Route to the login page
     *
     * @var string
     */
    protected $loginUrl = '/entry/signin?Target=';

    /**
     * Default page to redirect to and fetch
     *
     * @var string
     */
    protected $targetPath = 'profile.json';

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
            throw new Exception('missing_baseurl', 'You need to provide a base URL.');
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
	 * Set the target path to load on sign-in
	 *
	 * @param string $targetPath
	 *
	 * @return void
	 */
	public function setTargetPath(string $targetPath)
	{
		$this->targetPath = $targetPath;

		return;
	}

	/**
	 * Get the full target URL
	 *
	 * @return string
	 */
	public function getLoginUrl()
	{
		return $this->loginUrl . $this->targetPath;
	}

    /**
     * Retrive the Guzzle object for the login page
     *
     * @return \GuzzleHttp\Psr7\Response
     */
    private function getLoginPage()
    {
		// Set the login page
		$this->loginPage = $this->loginPage ?? $this->getClient()->request('GET', $this->getLoginUrl());

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
     * @return array|bool
     */
    public function authenticate()
    {
        // POST with our postData
        $response = $this->getClient()->request('POST', $this->getLoginUrl(), [
            'form_params' => $this->getPostData(),
        ]);

        // If the response code isn't 200...
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('There was an error authenticating your account.');
        }

        // Get the body (cast to a string, because Guzzle)
        $body = (string) $response->getBody();

        // If looking for JSON, and we have valid/decodable JSON...
        if ($this->targetPath === 'profile.json' && $this->user = json_decode($body, true)) {
            return $this->user;
		}

        // Get a queryable version of response errors
        $qp = html5qp($body)->find('.Messages.Errors');

        // Throw an exception when the username is wrong
        if (!empty($qp->get()) && strpos($qp->text(), 'no account could be found')) {
            throw new \Exception('Your account could not be found.');
        }

        // Throw an exception for invalid passwords
        if (!empty($qp->get()) && strpos($qp->text(), 'password you entered was incorrect')) {
            throw new \Exception('Your password was incorrect.');
        }

        // Throw an exception for a generic error
        if (!empty($qp->get()) && strpos($qp->text(), 'Bad login, double-check')) {
            throw new \Exception('Your login was incorrect. Double-check your username and password, and try again.');
        }

        // Return the response body
        return (string) $response->getBody();
    }
}

<?php

require_once(ROOT_DIR . 'Presenters/Authentication/LoginRedirector.php');

class ExternalAuthLoginPresenter
{
    /**
     * @var ExternalAuthLoginPage
     */
    private $page;
    /**
     * @var IWebAuthentication
     */
    private $authentication;
    /**
     * @var IRegistration
     */
    private $registration;

    public function __construct(ExternalAuthLoginPage $page, IWebAuthentication $authentication, IRegistration $registration)
    {
        $this->page = $page;
        $this->authentication = $authentication;
        $this->registration = $registration;
    }

    public function PageLoad()
    {
        if ($this->page->GetType() == 'google') {
            $this->ProcessGoogleSingleSignOn();
        }
        if ($this->page->GetType() == 'fb') {
            $this->ProcessFacebookSingleSignOn();
        }
        if ($this->page->GetType() == 'microsoft') {
            $this->ProcessMicrosoftSingleSignOn();
        }
        if ($this->page->GetType() == 'keycloak') {
            $this->ProcessKeycloakSingleSignOn();
        }
        if ($this->page->GetType() == 'oauth2') {
            $this->ProcessOauth2SingleSignOn();
        }
    }

    /**
     * Exchanges the code given by google in _GET for a token and with said token retrieves client data
     */
    private function ProcessGoogleSingleSignOn()
    {
        $client = new Google\Client();
        $client->setClientId(Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::GOOGLE_CLIENT_ID));
        $client->setClientSecret(Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::GOOGLE_CLIENT_SECRET));
        $client->setRedirectUri(Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::GOOGLE_REDIRECT_URI));
        $client->addScope("email");
        $client->addScope("profile");

        if (isset($_GET['code'])) {
            //Token validations for the client
            $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
            //set the access token that it received
            $client->setAccessToken($token['access_token']);

            //Using the Google API to get the user information
            $google_oauth = new Google\Service\Oauth2($client);
            $google_account_info = $google_oauth->userinfo->get();

            //Save the informations needed to authenticate the login
            $email     =  $google_account_info->email;
            $firstName = $google_account_info->given_name;
            $lastName  = $google_account_info->family_name;

            //Process $userData as needed (e.g., create a user, log in, etc.)
            $this->processUserData($email, $email, $firstName, $lastName);
        }
    }

    /**
     * Exchanges the code given by microsoft in _GET for a token and with said token retrieves client data
     */
    private function ProcessMicrosoftSingleSignOn()
    {
        if (isset($_GET['code'])) {
            $code = filter_input(INPUT_GET, 'code');

            $tokenEndpoint = 'https://login.microsoftonline.com/'
                . urlencode(Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::MICROSOFT_TENANT_ID))
                . '/oauth2/v2.0/token';

            $postData = [
                'client_id' => Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::MICROSOFT_CLIENT_ID),
                'client_secret' => Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::MICROSOFT_CLIENT_SECRET),
                'redirect_uri' => Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::MICROSOFT_REDIRECT_URI),
                'code' => $code, // The authorization code obtained earlier
                'grant_type' => 'authorization_code',
                'scope' => 'user.read',
            ];

            $client = new \GuzzleHttp\Client();

            $response = $client->post($tokenEndpoint, [
                'form_params' => $postData,
            ]);

            // Decode the JSON response
            $tokenData = json_decode($response->getBody(), true);

            // Extract the access token from the response
            $accessToken = $tokenData['access_token'];

            //Get user information
            $graphApiEndpoint = 'https://graph.microsoft.com/v1.0/me';

            // Make a GET request to the Microsoft Graph API endpoint
            $response = $client->request('GET', $graphApiEndpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            // Decode the JSON response
            $userData = json_decode($response->getBody(), true);

            // Handle the user data as needed
            $email     = $userData['mail'];
            $firstName = $userData['givenName'];;
            $lastName  = $userData['surname'];

            //Process $userData as needed (e.g., create a user, log in, etc.)
            $this->processUserData($email, $email, $firstName, $lastName);
        }
    }

    /**
     * Gets token created in facebook-auth.php and exchanges it for the client data
     * Unlike the other two (microsoft and google) the token must be obtained directly in the redirect uri, therefore can't be sent here for exchange(?)
     */
    private function ProcessFacebookSingleSignOn()
    {

        $facebook_Client = new Facebook\Facebook([
            'app_id'                => Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::FACEBOOK_CLIENT_ID),
            'app_secret'            => Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::FACEBOOK_CLIENT_SECRET),
            'default_graph_version' => 'v2.5'
        ]);

        if (isset($_SESSION['facebook_access_token'])) {
            $facebook_Client->setDefaultAccessToken(unserialize($_SESSION['facebook_access_token']));
        }
        unset($_SESSION['facebook_access_token']);

        $profile_request = $facebook_Client->get('/me?fields=name,first_name,last_name,email');
        $profile = $profile_request->getGraphUser();

        $email     = $profile->getField('email');
        $firstName = $profile->getField('first_name');
        $lastName  = $profile->getField('last_name');

        //Process $userData as needed (e.g., create a user, log in, etc.)
        $this->processUserData($email, $email, $firstName, $lastName);
    }

    private function ProcessKeycloakSingleSignOn()
    {
        $code = $_GET['code'];

        $keycloakUrl  = Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::KEYCLOAK_URL);
        $realm        = Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::KEYCLOAK_REALM);
        $clientId     = Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::KEYCLOAK_CLIENT_ID);
        $clientSecret = Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::KEYCLOAK_CLIENT_SECRET);
        $redirectUri = rtrim(Configuration::Instance()->GetScriptUrl(), 'Web/') . Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::KEYCLOAK_REDIRECT_URI);

        $tokenEndpoint = rtrim($keycloakUrl, '/') . '/realms/' . urlencode($realm) . '/protocol/openid-connect/token';

        // Prepare the POST data for the token request.
        $postData = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
        ];

        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->post($tokenEndpoint, [
                'form_params' => $postData,
            ]);
        } catch (\Exception $e) {
            $this->page->ShowError(['Error retrieving Keycloak token: ' . $e->getMessage()]);
            return;
        }

        $tokenData = json_decode($response->getBody(), true);
        if (!isset($tokenData['access_token'])) {
            $this->page->ShowError(['Access token not found in Keycloak response']);
            return;
        }
        $accessToken = $tokenData['access_token'];

        // Build the userinfo endpoint URL (again, without '/auth').
        $userInfoEndpoint = rtrim($keycloakUrl, '/') . '/realms/' . urlencode($realm) . '/protocol/openid-connect/userinfo';

        try {
            $userResponse = $client->get($userInfoEndpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);
        } catch (\Exception $e) {
            $this->page->ShowError(['Error retrieving Keycloak user info: ' . $e->getMessage()]);
            return;
        }

        $userData = json_decode($userResponse->getBody(), true);

        $email     = isset($userData['email']) ? $userData['email'] : '';
        $firstName = isset($userData['given_name']) ? $userData['given_name'] : 'not set';
        $lastName  = isset($userData['family_name']) ? $userData['family_name'] : 'not set';
        $username  = isset($userData['preferred_username']) ? $userData['preferred_username'] : $email;
        $phone  = isset($userData['phone_number']) ? $userData['phone_number'] : '';
        $organization  = isset($userData['organization']) ? $userData['organization'] : '';
        $title  = isset($userData['title']) ? $userData['title'] : '';

        if (empty($email)) {
            $this->page->ShowError(["Email is not set in your Keycloak profile. Please update your profile and try again."]);
            return;
        }

        $this->processUserData($username, $email, $firstName, $lastName, $phone, $organization, $title);
    }

    private function ProcessOauth2SingleSignOn()
    {
        $code = $_GET['code'];

        $oauth2UrlToken  = Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::OAUTH2_URL_TOKEN);
        $oauth2UrlUserinfo = Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::OAUTH2_URL_USERINFO);
        $clientId     = Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::OAUTH2_CLIENT_ID);
        $clientSecret = Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::OAUTH2_CLIENT_SECRET);
        $redirectUri = rtrim(Configuration::Instance()->GetScriptUrl(), 'Web/') . Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::OAUTH2_REDIRECT_URI);

        // Prepare the POST data for the token request.
        $postData = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
        ];

        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->post($oauth2UrlToken, [
                'form_params' => $postData,
            ]);
        } catch (\Exception $e) {
            $this->page->ShowError(['Error retrieving Oauth2 token: ' . $e->getMessage()]);
            return;
        }

        $tokenData = json_decode($response->getBody(), true);
        if (!isset($tokenData['access_token'])) {
            $this->page->ShowError(['Access token not found in Oauth2 response']);
            return;
        }
        $accessToken = $tokenData['access_token'];

        try {
            $userResponse = $client->get($oauth2UrlUserinfo, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);
        } catch (\Exception $e) {
            $this->page->ShowError(['Error retrieving Oauth2 user info: ' . $e->getMessage()]);
            return;
        }

        $userData = json_decode($userResponse->getBody(), true);

        $email     = isset($userData['email']) ? $userData['email'] : '';
        $firstName = isset($userData['given_name']) ? $userData['given_name'] : 'not set';
        $lastName  = isset($userData['family_name']) ? $userData['family_name'] : 'not set';
        $username  = isset($userData['preferred_username']) ? $userData['preferred_username'] : $email;
        $phone  = isset($userData['phone_number']) ? $userData['phone_number'] : '';
        $organization  = isset($userData['organization']) ? $userData['organization'] : '';
        $title  = isset($userData['title']) ? $userData['title'] : '';

        if (empty($email)) {
            $this->page->ShowError(["Email is not set in your Oauth2 profile. Please update your profile and try again."]);
            return;
        }

        $this->processUserData($username, $email, $firstName, $lastName, $phone, $organization, $title);
    }


    /**
     * Processes user given data, creates a user in database if it doesn't exist and logs it in
     */
    private function processUserData($username, $email, $firstName, $lastName, $phone = null, $organization = null, $title = null)
    {
        $requiredDomainValidator = new RequiredEmailDomainValidator($email);
        $requiredDomainValidator->Validate();
        if (!$requiredDomainValidator->IsValid()) {
            $this->page->ShowError(array(Resources::GetInstance()->GetString('InvalidEmailDomain')));
            return;
        }
        if ($this->registration->UserExists($username, $email)) {
            $this->authentication->Login($email, new WebLoginContext(new LoginData()));
            LoginRedirector::Redirect($this->page);
        } else {
            $this->registration->Synchronize(
                new AuthenticatedUser(
                    $username,
                    $email,
                    $firstName,
                    $lastName,
                    Password::GenerateRandom(),
                    Resources::GetInstance()->CurrentLanguage,
                    Configuration::Instance()->GetDefaultTimezone(),
                    $phone,
                    $organization,
                    $title
                ),
                false,
                false
            );
            $this->authentication->Login($email, new WebLoginContext(new LoginData()));
            LoginRedirector::Redirect($this->page);
        }
    }
}

<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'Presenters/Authentication/LoginRedirector.php');

class LoginPresenter
{
    /**
     * @var ILoginPage
     */
    private $_page = null;

    /**
     * @var IWebAuthentication
     */
    private $authentication = null;

    /**
     * @var ICaptchaService
     */
    private $captchaService;

    /**
     * @var IAnnouncementRepository
     */
    private $announcementRepository;
    private $rememberMe;

    /**
     * @param ILoginPage $page
     * @param IWebAuthentication $authentication
     * @param ICaptchaService $captchaService
     * @param IAnnouncementRepository $announcementRepository
     */
    public function __construct(ILoginPage &$page, $authentication = null, $captchaService = null, $announcementRepository = null)
    {
        $this->_page = &$page;
        $this->SetAuthentication($authentication);
        $this->SetCaptchaService($captchaService);
        $this->SetAnnouncementRepository($announcementRepository);

        $this->LoadValidators();
    }

    /**
     * @param IWebAuthentication $authentication
     */
    private function SetAuthentication($authentication)
    {
        if (is_null($authentication)) {
            $this->authentication = new WebAuthentication(PluginManager::Instance()->LoadAuthentication(), ServiceLocator::GetServer());
        } else {
            $this->authentication = $authentication;
        }
    }

    /**
     * @param ICaptchaService $captchaService
     */
    private function SetCaptchaService($captchaService)
    {
        if (is_null($captchaService)) {
            $this->captchaService = CaptchaService::Create();
        } else {
            $this->captchaService = $captchaService;
        }
    }

    /**
     * @param IAnnouncementRepository $announcementRepository
     */
    private function SetAnnouncementRepository($announcementRepository)
    {
        if (is_null($announcementRepository)) {
            $this->announcementRepository = new AnnouncementRepository();
        } else {
            $this->announcementRepository = $announcementRepository;
        }
    }

    public function PageLoad()
    {
        if ($this->authentication->IsLoggedIn()) {
            $this->_Redirect();
            return;
        }

        if (isset($_GET['registration']) && $_GET['registration'] === 'success') {
            $this->_page->SetSuccessMessage(Resources::GetInstance()->GetString('RegistrationSuccess'));
        }

        $this->SetSelectedLanguage();

        if ($this->authentication->AreCredentialsKnown()) {
            $this->Login();
            return;
        }

        $server = ServiceLocator::GetServer();
        $loginCookie = $server->GetCookie(CookieKeys::PERSIST_LOGIN);

        if ($this->IsCookieLogin($loginCookie)) {
            if ($this->authentication->CookieLogin($loginCookie, new WebLoginContext(new LoginData(true)))) {
                $this->_Redirect();
                return;
            }
        }

        $allowRegistration = Configuration::Instance()->GetKey(ConfigKeys::ALLOW_REGISTRATION, new BooleanConverter());
        $allowAnonymousSchedule = Configuration::Instance()->GetSectionKey(ConfigSection::PRIVACY, ConfigKeys::PRIVACY_VIEW_SCHEDULES, new BooleanConverter());
        $allowGuestBookings = Configuration::Instance()->GetSectionKey(ConfigSection::PRIVACY, ConfigKeys::PRIVACY_ALLOW_GUEST_BOOKING, new BooleanConverter());
        $this->_page->SetShowRegisterLink($allowRegistration);
        $this->_page->SetShowScheduleLink($allowAnonymousSchedule || $allowGuestBookings);

        $hideLogin = Configuration::Instance()
            ->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::AUTHENTICATION_HIDE_BOOKED_LOGIN_PROMPT, new BooleanConverter());

        $this->_page->ShowForgotPasswordPrompt(!Configuration::Instance()->GetKey(ConfigKeys::DISABLE_PASSWORD_RESET, new BooleanConverter()) &&
            $this->authentication->ShowForgotPasswordPrompt() &&
            !$hideLogin);
        $this->_page->ShowPasswordPrompt($this->authentication->ShowPasswordPrompt() && !$hideLogin);
        $this->_page->ShowPersistLoginPrompt($this->authentication->ShowPersistLoginPrompt());

        $this->_page->ShowUsernamePrompt($this->authentication->ShowUsernamePrompt() && !$hideLogin);
        $this->_page->SetRegistrationUrl($this->authentication->GetRegistrationUrl() && !$hideLogin);
        $this->_page->SetPasswordResetUrl($this->authentication->GetPasswordResetUrl());
        $this->_page->SetAnnouncements($this->announcementRepository->GetFuture(Pages::ID_LOGIN));
        $this->_page->SetSelectedLanguage(Resources::GetInstance()->CurrentLanguage);

        $this->_page->SetGoogleUrl($this->GetGoogleUrl());
        $this->_page->SetMicrosoftUrl($this->GetMicrosoftUrl());
        $this->_page->SetFacebookUrl($this->GetFacebookUrl());
        $this->_page->SetKeycloakUrl($this->GetKeycloakUrl());
        $this->_page->SetOauth2Url($this->GetOauth2Url());
        $this->_page->SetOauth2Name($this->GetOauth2Name());
    }

    public function Login()
    {
        $this->rememberMe = $this->authentication->ShowPersistLoginPrompt() && $this->_page->GetPersistLogin();

        if (isset($_REQUEST['login_token']) && !empty($_REQUEST['login_token'])) {
            $this->LoginWithToken($_REQUEST['login_token']);
            // LoginWithToken will redirect on success. If it returns, it failed.
        }

        if (!$this->_page->IsValid()) {
            return;
        }

        $id = $this->_page->GetEmailAddress();

        // Just validate credentials. Do NOT create a session yet.
        if ($this->authentication->Validate($id, $this->_page->GetPassword())) {
            
            // We delegate the user ID lookup to the Symfony API.
            // Just pass the raw identifier from the form.
            $this->checkTwoFactorAuth($id);
            
            // The checkTwoFactorAuth function will handle all redirection and exit.
            // This part of the code should never be reached.
            Log::Error("Execution continued after 2FA check for user %s. This should not happen.", $id);
            $this->_page->SetShowLoginError();
            exit;

        } else {
            // Invalid credentials
            sleep(2);
            $this->authentication->HandleLoginFailure($this->_page);
            $this->_page->SetShowLoginError();
            $this->_page->Redirect(Pages::LOGIN);
        }
    }

    private function LoginWithToken($token)
    {
        Log::Debug('[LoginWithToken] Attempting login with token.');
        $db = ServiceLocator::GetDatabase();

        $safe_token = $db->Connection->EscapeString($token);
        
        $sql = "SELECT * FROM user2_fa WHERE temp_login_secret = '" . $safe_token . "' AND temp_login_expires_at > NOW()";
        $command = new AdHocCommand($sql);
        
        $result = $db->Query($command);
        $user2fa = $result->GetRow();

        if ($user2fa) {
            Log::Debug(sprintf('[LoginWithToken] Token found for user_id: %s. Proceeding with login.', $user2fa['user_id']));
            
            // Manually build the user session since WebAuthentication->Login() is problematic
            $userId = intval($user2fa['user_id']);
            $userResult = $db->Query(new AdHocCommand("SELECT * FROM users WHERE user_id = " . $userId));
            $userRow = $userResult->GetRow();

            if ($userRow) {
                // Invalidate the token
                $safe_id = intval($user2fa['id']);
                $updateSql = "UPDATE user2_fa SET temp_login_secret = NULL, temp_login_expires_at = NULL WHERE id = " . $safe_id;
                $updateCommand = new AdHocCommand($updateSql);
                $db->Execute($updateCommand);

                // Create and set the session
                $userSession = new UserSession($userId);
                $userSession->Email = $userRow[ColumnNames::EMAIL];
                $userSession->FirstName = $userRow[ColumnNames::FIRST_NAME];
                $userSession->LastName = $userRow[ColumnNames::LAST_NAME];
                
                // Determine admin roles
                $userSession->IsAdmin = Configuration::Instance()->IsAdminEmail($userSession->Email);
                $userRepo = new UserRepository();
                $groups = $userRepo->LoadGroups($userId);
                $userSession->Groups = $groups;
                foreach ($groups as $group) {
                    if ($group->IsGroupAdmin) $userSession->IsGroupAdmin = true;
                    if ($group->IsApplicationAdmin) $userSession->IsAdmin = true; // Application admin role in a group overrides config
                    if ($group->IsResourceAdmin) $userSession->IsResourceAdmin = true;
                    if ($group->IsScheduleAdmin) $userSession->IsScheduleAdmin = true;
                }
                
                $userSession->Timezone = $userRow[ColumnNames::TIMEZONE_NAME];
                $userSession->LanguageCode = $userRow[ColumnNames::LANGUAGE_CODE];
                $userSession->LoginTime = date('Y-m-d H:i:s');

                ServiceLocator::GetServer()->SetUserSession($userSession);

                $this->_page->Redirect('dashboard.php');
            } else {
                 Log::Debug('[LoginWithToken] Could not find user details for user_id: ' . $userId);
            }
        } else {
            Log::Debug('[LoginWithToken] Token not found in database or expired.');
        }
    }

    /**
     * Vérifie si l'utilisateur a activé sa 2FA
     * @param string $userId
     * @return bool True si 2FA OK ou pas requise, False si 2FA requise mais pas activée
     */
    private function checkTwoFactorAuth($userId)
    {
        // Charger la configuration 2FA
        require_once(ROOT_DIR . 'config/2fa_config.php');
        
        // Vérifier si l'intégration 2FA est activée
        if (!TwoFactorConfig::isEnabled()) {
            Log::Debug('[2FA] Integration disabled, proceeding with legacy login only.');
            $loginContext = new WebLoginContext(new LoginData($this->rememberMe));
            if ($this->authentication->Login($this->_page->GetEmailAddress(), $this->_page->GetPassword(), $loginContext)) {
                return true;
            }
            $this->_Redirect();
            return false;
        }

        $ssoSharedSecret = TwoFactorConfig::getSsoSharedSecret();
        $symfonyUrl = TwoFactorConfig::getSsoLoginUrl();
        $timeout = TwoFactorConfig::getHttpTimeout();

        $postData = http_build_query(['user_id' => $userId]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                            "X-SSO-TOKEN: " . $ssoSharedSecret . "\r\n" .
                            "Content-Length: " . strlen($postData) . "\r\n",
                'content' => $postData,
                'timeout' => $timeout,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($symfonyUrl, false, $context);

        // --- PROPAGATION DU COOKIE DE SESSION SYMFONY ---
        // L’appel ci-dessus est exécuté côté serveur ; nous devons relayer les en-têtes
        // "Set-Cookie" reçus afin que le navigateur de l’utilisateur les reçoive et
        // qu’il puisse ensuite accéder à sa session Symfony.
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $hdr) {
                if (stripos($hdr, 'Set-Cookie:') === 0) {
                    // false pour empiler les éventuels multiples cookies.
                    header($hdr, false);
                }
            }
        }
        // --- FIN PROPAGATION COOKIE ---

        // Détermination de la cible en fonction du statut 2FA
        $db = ServiceLocator::GetDatabase();

        // On convertit l'identifiant en ID numérique si besoin
        if (is_numeric($userId)) {
            $lookupId = intval($userId);
        } else {
            $safeIdentifier = $db->Connection->EscapeString($userId);
            $result = $db->Query(new AdHocCommand("SELECT user_id FROM users WHERE username = '" . $safeIdentifier . "' OR email = '" . $safeIdentifier . "'"));
            $rowConv = $result->GetRow();
            $lookupId = $rowConv ? intval($rowConv['user_id']) : 0;
        }

        $statusResult = $db->Query(new AdHocCommand("SELECT enabled FROM user2_fa WHERE user_id = " . $lookupId));
        $row = $statusResult->GetRow();
        $has2FAEnabled = $row && intval($row['enabled']) === 1;

        if ($has2FAEnabled) {
            // Redirige vers la vérification de code 2FA sur Symfony
            header('Location: ' . TwoFactorConfig::get2faLoginUrl() . '?user_id=' . urlencode($userId));
            exit;
        }

        // Si la 2FA n’est pas activée, on connecte immédiatement l’utilisateur côté legacy
        $loginContext = new WebLoginContext(new LoginData($this->rememberMe));
        if ($this->authentication->Login($this->_page->GetEmailAddress(), $this->_page->GetPassword(), $loginContext)) {
            return true; // La méthode Login() se chargera d’effectuer la redirection finale
        }

        // En cas d’échec, on retombe sur le flux standard d’erreur
        $this->_Redirect();

        return false;
    }

    /**
     * @return int|null
     */
    private function getNumericUserId($identifier)
    {
        // DEPRECATED: This logic is now handled by the Symfony API endpoint.
        return null;
    }

    public function postLogout()
    {
        $url = Configuration::Instance()->GetKey(ConfigKeys::LOGOUT_URL);
        if (empty($url)) {
            $url = htmlspecialchars_decode($this->_page->GetResumeUrl());
            $url = sprintf('%s?%s=%s', Pages::LOGIN, QueryStringKeys::REDIRECT, urlencode($url));
        }
        $this->authentication->postLogout(ServiceLocator::GetServer()->GetUserSession());
        $this->_page->Redirect($url);
    }

    public function ChangeLanguage()
    {
        $resources = Resources::GetInstance();

        $languageCode = $this->_page->GetRequestedLanguage();

        if ($resources->SetLanguage($languageCode)) {
            ServiceLocator::GetServer()->SetCookie(new Cookie(CookieKeys::LANGUAGE, $languageCode));
            $this->_page->SetSelectedLanguage($languageCode);
            $this->_page->Redirect(Pages::LOGIN);
        }
    }

    public function Logout()
    {
        $userSession = ServiceLocator::GetServer()->GetUserSession();
        $userId = $userSession->UserId;
        
        // Charger la configuration 2FA
        require_once(ROOT_DIR . 'config/2fa_config.php');
        
        if (TwoFactorConfig::isEnabled()) {
            $ssoSharedSecret = TwoFactorConfig::getSsoSharedSecret();
            $symfonyUrl = TwoFactorConfig::getSsoLogoutUrl();
            $timeout = TwoFactorConfig::getHttpTimeout();

            // SSO Logout: Notifier Symfony pour détruire sa session
            $postData = http_build_query(['user_id' => $userId]);
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                                "X-SSO-TOKEN: " . $ssoSharedSecret . "\r\n",
                    'content' => $postData,
                    'timeout' => $timeout,
                    'ignore_errors' => true
                ]
            ]);
            @file_get_contents($symfonyUrl, false, $context);
            // On ne vérifie pas la réponse, la déconnexion legacy doit se poursuivre quoi qu'il arrive
        }

        $url = Configuration::Instance()->GetKey(ConfigKeys::LOGOUT_URL);
        if (empty($url)) {
            $url = htmlspecialchars_decode($this->_page->GetResumeUrl() ?? '');
            $url = sprintf('%s?%s=%s', Pages::LOGIN, QueryStringKeys::REDIRECT, urlencode($url));
        }
        $this->authentication->Logout($userSession);
        $this->_page->Redirect($url);
    }

    private function _Redirect()
    {
        $userSession = ServiceLocator::GetServer()->GetUserSession();
        if ($userSession && $userSession->UserId) {
            LoginRedirector::Redirect($this->_page);
        }
    }

    private function IsCookieLogin($loginCookie)
    {
        return !empty($loginCookie);
    }

    private function SetSelectedLanguage()
    {
        $requestedLanguage = $this->_page->GetRequestedLanguage();
        if (!empty($requestedLanguage)) {
            // this is handled by ChangeLanguage()
            return;
        }

        $languageCookie = ServiceLocator::GetServer()->GetCookie(CookieKeys::LANGUAGE);
        $languageHeader = ServiceLocator::GetServer()->GetLanguage();
        $languageCode = Configuration::Instance()->GetKey(ConfigKeys::LANGUAGE);

        $resources = Resources::GetInstance();

        if ($resources->IsLanguageSupported($languageCookie)) {
            $languageCode = $languageCookie;
        } else {
            if ($resources->IsLanguageSupported($languageHeader)) {
                $languageCode = $languageHeader;
            }
        }

        $this->_page->SetSelectedLanguage(strtolower($languageCode));
        $resources->SetLanguage($languageCode);
    }

    protected function LoadValidators()
    {
        if (Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::AUTHENTICATION_CAPTCHA_ON_LOGIN, new BooleanConverter())) {
            $this->_page->RegisterValidator('captcha', new CaptchaValidator($this->_page->GetCaptcha(), $this->captchaService));
        }
    }

    /**
     * Checks in the config files if google authentication is active creating a new client if true and setting it's config keys.
     * Returns the created google url for the authentication
     */
    public function GetGoogleUrl()
    {
        if (Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::AUTHENTICATION_ALLOW_GOOGLE, new BooleanConverter())) {
            $client = new Google\Client();
            $client->setClientId(Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::GOOGLE_CLIENT_ID));
            $client->setClientSecret(Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::GOOGLE_CLIENT_SECRET));
            $client->setRedirectUri(Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::GOOGLE_REDIRECT_URI));
            $client->addScope("email");
            $client->addScope("profile");
            $client->setPrompt("select_account");
            $GoogleUrl = $client->createAuthUrl();

            return $GoogleUrl;
        }
    }

    /**
     * Checks in the config files if microsoft authentication is active creating the url if true with the respective keys
     * Returns the created microsoft url for the authentication
     */
    public function GetMicrosoftUrl()
    {
        if (Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::AUTHENTICATION_ALLOW_MICROSOFT, new BooleanConverter())) {
            $MicrosoftUrl = 'https://login.microsoftonline.com/'
                . urlencode(Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::MICROSOFT_TENANT_ID))
                . '/oauth2/v2.0/authorize?'
                . 'client_id=' . urlencode(Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::MICROSOFT_CLIENT_ID))
                . '&redirect_uri=' . urlencode(Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::MICROSOFT_REDIRECT_URI))
                . '&scope=user.read'
                . '&response_type=code'
                . '&prompt=select_account';

            return $MicrosoftUrl;
        }
    }

    /**
     * Checks in the config files if facebook authentication is active creating the url if true with the respective keys
     * Returns the created facebook url for the authentication
     */
    public function GetFacebookUrl()
    {
        if (Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::AUTHENTICATION_ALLOW_FACEBOOK, new BooleanConverter())) {
            $facebook_Client = new Facebook\Facebook([
                'app_id'                => Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::FACEBOOK_CLIENT_ID),
                'app_secret'            => Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::FACEBOOK_CLIENT_SECRET),
                'default_graph_version' => 'v2.5'
            ]);

            $helper = $facebook_Client->getRedirectLoginHelper();

            $permissions = ['email', 'public_profile']; // Add other permissions as needed

            //The FacebookRedirectLoginHelper makes use of sessions to store a CSRF value.
            //You need to make sure you have sessions enabled before invoking the getLoginUrl() method.
            if (!session_id()) {
                session_start();
            }
            $FacebookUrl = $helper->getLoginUrl(
                Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::FACEBOOK_REDIRECT_URI),
                $permissions
            );

            return $FacebookUrl;
        }
    }

    public function GetKeycloakUrl()
    {
        if (Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::AUTHENTICATION_ALLOW_KEYCLOAK, new BooleanConverter())) {
            // Retrieve Keycloak configuration values
            $baseUrl     = Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::KEYCLOAK_URL);
            $realm       = Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::KEYCLOAK_REALM);
            $clientId    = Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::KEYCLOAK_CLIENT_ID);
            $redirectUri = rtrim(Configuration::Instance()->GetScriptUrl(), 'Web/') . Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::KEYCLOAK_REDIRECT_URI);

            // Construct the Keycloak authentication URL
            $keycloakUrl = rtrim($baseUrl, '/')
                . '/realms/' . urlencode($realm)
                . '/protocol/openid-connect/auth?'
                . 'client_id=' . urlencode($clientId)
                . '&redirect_uri=' . urlencode($redirectUri)
                . '&response_type=code'
                . '&scope=' . urlencode('openid email profile');

            return $keycloakUrl;
        }
    }

    public function GetOauth2Url()
    {
        if (Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::AUTHENTICATION_ALLOW_OAUTH2, new BooleanConverter())) {
            // Retrieve Oauth2 configuration values
            $baseUrl     = Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::OAUTH2_URL_AUTHORIZE);
            $clientId    = Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::OAUTH2_CLIENT_ID);
            $redirectUri = rtrim(Configuration::Instance()->GetScriptUrl(), 'Web/') . Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::OAUTH2_REDIRECT_URI);

            // Construct the Oauth2 authentication URL
            $Oauth2Url = $baseUrl
                . '?client_id=' . urlencode($clientId)
                . '&redirect_uri=' . urlencode($redirectUri)
                . '&response_type=code'
                . '&scope=' . urlencode('openid email profile');

            return $Oauth2Url;
        }
    }

    public function GetOauth2Name()
    {
        if (Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::AUTHENTICATION_ALLOW_OAUTH2, new BooleanConverter())) {
            // Retrieve Oauth2 configuration values
            $Oauth2Name = Configuration::Instance()->GetSectionKey(ConfigSection::AUTHENTICATION, ConfigKeys::OAUTH2_NAME);

            return $Oauth2Name;
        }
    }
}

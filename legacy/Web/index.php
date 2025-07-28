<?php
// Harmonisation de la session avec Symfony
if (session_status() === PHP_SESSION_NONE) {
    session_name('PHPSESSID');
    session_set_cookie_params(['path' => '/']);
}
// Fin de l'harmonisation

define('ROOT_DIR', '../');

// Charger les variables d'environnement
require_once(ROOT_DIR . 'lib/Config/EnvironmentLoader.php');
EnvironmentLoader::load();

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'Pages/LoginPage.php');
require_once(ROOT_DIR . 'Presenters/LoginPresenter.php');

$page = new LoginPage();

if ($page->LoggingIn())
{
	$page->Login();
}
else if ($page->ChangingLanguage())
{
	$page->ChangeLanguage();
}
else
{
	$page->PageLoad();
}

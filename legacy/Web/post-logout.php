<?php

define('ROOT_DIR', '../');

require_once(ROOT_DIR . 'Pages/PostLogoutPage.php');

$page = new LogoutPage();

$page->PageLoad();

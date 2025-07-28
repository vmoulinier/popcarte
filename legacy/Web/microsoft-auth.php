<?php
define('ROOT_DIR', '../');

require_once(ROOT_DIR . 'lib/Common/namespace.php');

//Checks if the user was authenticated by microsoft and redirects to external authentication page
if (isset($_GET['code'])) {
    $code = filter_input(INPUT_GET, 'code');
    header("Location: ".ROOT_DIR."Web/external-auth.php?type=microsoft&code=".$code);
    exit;
} else{
    header("Location:".ROOT_DIR."Web");
    exit();
}
?>
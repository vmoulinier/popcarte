<?php

define('ROOT_DIR', '../../');
require_once(ROOT_DIR . 'Pages/Reports/GenerateReportPage.php');

$roles = [RoleLevel::APPLICATION_ADMIN, RoleLevel::GROUP_ADMIN, RoleLevel::RESOURCE_ADMIN, RoleLevel::SCHEDULE_ADMIN];
if (Configuration::Instance()->GetSectionKey(ConfigSection::REPORTS, ConfigKeys::REPORTS_ALLOW_ALL, new BooleanConverter())) {
    $roles = [];
}

$page = new RoleRestrictedPageDecorator(new GenerateReportPage(), $roles);
$page->PageLoad();

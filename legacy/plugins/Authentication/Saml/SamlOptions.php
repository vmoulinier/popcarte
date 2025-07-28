<?php
/**
File in Authentication plugin package for ver 2.1.4 LibreBooking
to implement Single Sign On Capability.  Based on code from the
LibreBooking Authentication Ldap plugin as well as a SAML
Authentication plugin for Moodle 1.9+.
See http://moodle.org/mod/data/view.php?d=13&rid=2574
This plugin uses the SimpleSAMLPHP version 1.8.2 libraries.
http://simplesamlphp.org/
*/

require_once(ROOT_DIR . '/lib/Config/namespace.php');

class SamlOptions
{
    private $_options = [];

    public function __construct()
    {
        require_once(dirname(__FILE__) . '/Saml.config.php');

        Configuration::Instance()->Register(
            dirname(__FILE__) . '/Saml.config.php',
            SamlConfig::CONFIG_ID
        );
    }

    public function AdSamlOptions()
    {
        $this->SetOption('ssphp_lib', $this->GetConfig(SamlConfig::SIMPLESAMLPHP_LIB));
        $this->SetOption('ssphp_config', $this->GetConfig(SamlConfig::SIMPLESAMLPHP_CONFIG));
        $this->SetOption('ssphp_sp', $this->GetConfig(SamlConfig::SIMPLESAMLPHP_SP));
        $this->SetOption('ssphp_username', $this->GetConfig(SamlConfig::USERNAME));
        $this->SetOption('ssphp_firstname', $this->GetConfig(SamlConfig::FIRSTNAME));
        $this->SetOption('ssphp_lastname', $this->GetConfig(SamlConfig::LASTNAME));
        $this->SetOption('ssphp_email', $this->GetConfig(SamlConfig::EMAIL));
        $this->SetOption('ssphp_phone', $this->GetConfig(SamlConfig::PHONE));
        $this->SetOption('ssphp_organization', $this->GetConfig(SamlConfig::ORGANIZATION));
        $this->SetOption('ssphp_position', $this->GetConfig(SamlConfig::POSITION));
        $this->SetOption('ssphp_groups', $this->GetConfig(SamlConfig::GROUPS));

        return $this->_options;
    }

    /**
     * @return bool
     */
    public function SyncGroups()
    {
        return $this->GetConfig(SamlConfig::SYNC_GROUPS, new BooleanConverter());
    }

    private function SetOption($key, $value)
    {
        if (empty($value)) {
            $value = null;
        }

        $this->_options[$key] = $value;
    }

    private function GetConfig($keyName, $converter = null)
    {
        return Configuration::Instance()->File(SamlConfig::CONFIG_ID)->GetKey($keyName, $converter);
    }
}

<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailChangePasswordIspmanagerPlugin;

use Aurora\System\SettingsProperty;

/**
 * @property bool $Disabled
 * @property array $SupportedServers
 * @property string $ISPmanagerHost
 * @property string $ISPmanagerUser
 * @property string $ISPmanagerPass
 */

class Settings extends \Aurora\System\Module\Settings
{
    protected function initDefaults()
    {
        $this->aContainer = [
            "Disabled" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true disables the module",
            ),
            "SupportedServers" => new SettingsProperty(
                [
                    "*"
                ],
                "array",
                null,
                "If IMAP Server value of the mailserver is in this list, password change is enabled for it. * enables it for all the servers.",
            ),
            "ISPmanagerHost" => new SettingsProperty(
                "https://127.0.0.1:1500/ispmgr",
                "string",
                null,
                "Defines main URL of ISPmanager installation",
            ),
            "ISPmanagerUser" => new SettingsProperty(
                "root",
                "string",
                null,
                "Admin username of ISPmanager installation",
            ),
            "ISPmanagerPass" => new SettingsProperty(
                "",
                "string",
                null,
                "Admin password of ISPmanager installation",
            ),
        ];
    }
}

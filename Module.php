<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailChangePasswordIspmanagerPlugin;

use Aurora\Modules\Mail\Models\MailAccount;
use Aurora\System\Notifications;

/**
 * Allows users to change passwords on their email accounts in ISPmanager.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property Settings $oModuleSettings
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    public function init()
    {
        $this->subscribeEvent('Mail::Account::ToResponseArray', array($this, 'onMailAccountToResponseArray'));
        $this->subscribeEvent('ChangeAccountPassword', array($this, 'onChangeAccountPassword'));
    }

    /**
     * @return Module
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     * @return Settings
     */
    public function getModuleSettings()
    {
        return $this->oModuleSettings;
    }

    /**
     * Adds to account response array information about if allowed to change the password for this account.
     * @param array $aArguments
     * @param mixed $mResult
     */
    public function onMailAccountToResponseArray($aArguments, &$mResult)
    {
        $oAccount = $aArguments['Account'];

        if ($oAccount && $this->checkCanChangePassword($oAccount)) {
            if (!isset($mResult['Extend']) || !is_array($mResult['Extend'])) {
                $mResult['Extend'] = [];
            }
            $mResult['Extend']['AllowChangePasswordOnMailServer'] = true;
        }
    }

    /**
     * Tries to change password for account if allowed.
     * @param array $aArguments
     * @param mixed $mResult
     */
    public function onChangeAccountPassword($aArguments, &$mResult)
    {
        $bPasswordChanged = false;
        $bBreakSubscriptions = false;

        $oAccount = $aArguments['Account'] instanceof MailAccount ? $aArguments['Account'] : false;
        if ($oAccount && $this->checkCanChangePassword($oAccount) && $oAccount->getPassword() === $aArguments['CurrentPassword']) {
            $bPasswordChanged = $this->changePassword($oAccount, $aArguments['NewPassword']);
            $bBreakSubscriptions = true; // break if mail server plugin tries to change password in this account.
        }

        if (is_array($mResult)) {
            $mResult['AccountPasswordChanged'] = $mResult['AccountPasswordChanged'] || $bPasswordChanged;
        }

        return $bBreakSubscriptions;
    }

    /**
     * Checks if allowed to change password for account.
     * @param \Aurora\Modules\Mail\Models\MailAccount $oAccount
     * @return bool
     */
    protected function checkCanChangePassword($oAccount)
    {
        $bFound = in_array('*', $this->oModuleSettings->SupportedServers);

        if (!$bFound) {
            $oServer = $oAccount->getServer();

            if ($oServer && in_array($oServer->IncomingServer, $this->oModuleSettings->SupportedServers)) {
                $bFound = true;
            }
        }

        return $bFound;
    }

    /**
     * Tries to change password for account.
     * @param \Aurora\Modules\Mail\Models\MailAccount $oAccount
     * @param string $sPassword
     * @return boolean
     * @throws \Aurora\System\Exceptions\ApiException
     */
    protected function changePassword($oAccount, $sPassword)
    {
        $bResult = false;
        $sPassCurr = $oAccount->getPassword();
        $sEmail = $oAccount->Email;

        if (0 < strlen($sPassCurr) && $sPassCurr !== $sPassword) {
            $sCfgHost = $this->oModuleSettings->ISPmanagerHost;
            $sCfgUser = $this->oModuleSettings->ISPmanagerUser;
            $sCfgPass = $this->oModuleSettings->ISPmanagerPass;

            if ($sCfgPass && !\Aurora\System\Utils::IsEncryptedValue($sCfgPass)) {
                $this->setConfig('ISPmanagerPass', \Aurora\System\Utils::EncryptValue($sCfgPass));
                $this->saveModuleConfig();
            } else {
                $sCfgPass = \Aurora\System\Utils::DecryptValue($sCfgPass);
            }

            $rCurl = curl_init();
            curl_setopt($rCurl, CURLOPT_URL, $sCfgHost . '?authinfo=' . $sCfgUser . ':' . $sCfgPass . '&out=json&func=email.edit&elid=' . $sEmail . '&passwd=' . $sPassword . '&sok=ok');
            curl_setopt($rCurl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($rCurl, CURLOPT_CONNECTTIMEOUT, 20);
            curl_setopt($rCurl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($rCurl, CURLOPT_SSL_VERIFYPEER, 0);
            $data = curl_exec($rCurl);
            curl_close($rCurl);

            if ($data === false) {
                throw new \Aurora\System\Exceptions\ApiException(Notifications::CanNotChangePassword);
            } else {
                $rez = json_decode($data, true);
                if (($rez === false) || isset($rez["doc"]["error"])) {
                    throw new \Aurora\System\Exceptions\ApiException(Notifications::CanNotChangePassword);
                } else {
                    $bResult = true;
                }
            }
        }
        return $bResult;
    }
}

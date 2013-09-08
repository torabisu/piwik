<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package UsersManager
 */
namespace Piwik\Plugins\UsersManager;

use Exception;
use Piwik\Piwik;
use Piwik\Option;
use Piwik\Plugins\UsersManager\API;

/**
 * Manage Piwik users
 *
 * @package UsersManager
 */
class UsersManager extends \Piwik\Plugin
{
    const PASSWORD_MIN_LENGTH = 6;
    const PASSWORD_MAX_LENGTH = 26;

    /**
     * @see Piwik_Plugin::getListHooksRegistered
     */
    public function getListHooksRegistered()
    {
        return array(
            'AdminMenu.add'                 => 'addMenu',
            'AssetManager.getJsFiles'       => 'getJsFiles',
            'AssetManager.getStylesheetFiles'      => 'getStylesheetFiles',
            'SitesManager.deleteSite'       => 'deleteSite',
            'Common.fetchWebsiteAttributes' => 'recordAdminUsersInCache',
        );
    }

    /**
     * Hooks when a website tracker cache is flushed (website/user updated, cache deleted, or empty cache)
     * Will record in the tracker config file the list of Admin token_auth for this website. This
     * will be used when the Tracking API is used with setIp(), setForceDateTime(), setVisitorId(), etc.
     *
     * @param $attributes
     * @param $idSite
     * @return void
     */
    public function recordAdminUsersInCache(&$attributes, $idSite)
    {
        // add the 'hosts' entry in the website array
        $users = API::getInstance()->getUsersWithSiteAccess($idSite, 'admin');

        $tokens = array();
        foreach ($users as $user) {
            $tokens[] = $user['token_auth'];
        }
        $attributes['admin_token_auth'] = $tokens;
    }

    /**
     * Delete user preferences associated with a particular site
     */
    public function deleteSite($idSite)
    {
        Option::getInstance()->deleteLike('%\_' . API::PREFERENCE_DEFAULT_REPORT, $idSite);
    }

    /**
     * Return list of plug-in specific JavaScript files to be imported by the asset manager
     *
     * @see Piwik_AssetManager
     */
    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/UsersManager/javascripts/usersManager.js";
        $jsFiles[] = "plugins/UsersManager/javascripts/usersSettings.js";
    }

    /**
     * Get CSS files
     */
    function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/UsersManager/stylesheets/usersManager.less";
    }

    /**
     * Add admin menu items
     */
    function addMenu()
    {
        Piwik_AddAdminSubMenu('CoreAdminHome_MenuManage', 'UsersManager_MenuUsers',
            array('module' => 'UsersManager', 'action' => 'index'),
            Piwik::isUserHasSomeAdminAccess(),
            $order = 2);
        Piwik_AddAdminSubMenu('CoreAdminHome_MenuManage', 'UsersManager_MenuUserSettings',
            array('module' => 'UsersManager', 'action' => 'userSettings'),
            Piwik::isUserHasSomeViewAccess(),
            $order = 3);
    }

    /**
     * Returns true if the password is complex enough (at least 6 characters and max 26 characters)
     *
     * @param $input string
     * @return bool
     */
    public static function isValidPasswordString($input)
    {
        if (!Piwik::isChecksEnabled()
            && !empty($input)
        ) {
            return true;
        }
        $l = strlen($input);
        return $l >= self::PASSWORD_MIN_LENGTH && $l <= self::PASSWORD_MAX_LENGTH;
    }

    public static function checkPassword($password)
    {
        if (!self::isValidPasswordString($password)) {
            throw new Exception(Piwik_TranslateException('UsersManager_ExceptionInvalidPassword', array(self::PASSWORD_MIN_LENGTH,
                                                                                                        self::PASSWORD_MAX_LENGTH)));
        }
    }

    public static function getPasswordHash($password)
    {
        // if change here, should also edit the installation process
        // to change how the root pwd is saved in the config file
        return md5($password);
    }
}

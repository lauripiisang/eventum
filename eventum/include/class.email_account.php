<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | Eventum - Issue Tracking System                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 2003, 2004 MySQL AB                                    |
// |                                                                      |
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License as published by |
// | the Free Software Foundation; either version 2 of the License, or    |
// | (at your option) any later version.                                  |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to:                           |
// |                                                                      |
// | Free Software Foundation, Inc.                                       |
// | 59 Temple Place - Suite 330                                          |
// | Boston, MA 02111-1307, USA.                                          |
// +----------------------------------------------------------------------+
// | Authors: Jo�o Prado Maia <jpm@mysql.com>                             |
// +----------------------------------------------------------------------+
//
// @(#) $Id$
//


include_once(APP_INC_PATH . "class.error_handler.php");

class Email_Account
{
    /**
     * Method used to get the support email account associated with a given
     * support email message.
     *
     * @access  public
     * @param   integer $sup_id The support email ID
     * @return  integer The email account ID
     */
    function getAccountByEmail($sup_id)
    {
        $stmt = "SELECT
                    sup_ema_id
                 FROM
                    " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "support_email
                 WHERE
                    sup_id=$sup_id";
        $res = $GLOBALS["db_api"]->dbh->getOne($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return "";
        } else {
            return $res;
        }
    }


    /**
     * Method used to get the account ID for a given email account.
     *
     * @access  public
     * @param   string $username The username for the specific email account
     * @param   string $hostname The hostname for the specific email account
     * @param   string $mailbox The mailbox for the specific email account
     * @return  integer The support email account ID
     */
    function getAccountID($username, $hostname, $mailbox)
    {
        $stmt = "SELECT
                    ema_id
                 FROM
                    " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "email_account
                 WHERE
                    ema_username='" . Misc::escapeString($username) . "' AND
                    ema_hostname='" . Misc::escapeString($hostname) . "' AND
                    ema_folder='" . Misc::escapeString($mailbox) . "'";
        $res = $GLOBALS["db_api"]->dbh->getOne($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return 0;
        } else {
            if ($res == NULL) {
                return 0;
            } else {
                return $res;
            }
        }
    }


    /**
     * Method used to get the details of a given support email 
     * account.
     *
     * @access  public
     * @param   integer $ema_id The support email account ID
     * @return  array The account details
     */
    function getDetails($ema_id)
    {
        $stmt = "SELECT
                    *
                 FROM
                    " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "email_account
                 WHERE
                    ema_id=$ema_id";
        $res = $GLOBALS["db_api"]->dbh->getRow($stmt, DB_FETCHMODE_ASSOC);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return "";
        } else {
            return $res;
        }
    }


    /**
     * Method used to remove all support email accounts associated 
     * with a specified set of projects.
     *
     * @access  public
     * @param   array $ids The list of projects
     * @return  boolean
     */
    function removeAccountByProjects($ids)
    {
        $items = @implode(", ", $ids);
        $stmt = "SELECT
                    ema_id
                 FROM
                    " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "email_account
                 WHERE
                    ema_prj_id IN ($items)";
        $res = $GLOBALS["db_api"]->dbh->getCol($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return false;
        } else {
            Support::removeEmailByAccounts($res);
            $stmt = "DELETE FROM
                        " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "email_account
                     WHERE
                        ema_prj_id IN ($items)";
            $res = $GLOBALS["db_api"]->dbh->query($stmt);
            if (PEAR::isError($res)) {
                Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
                return false;
            } else {
                return true;
            }
        }
    }


    /**
     * Method used to remove the specified support email accounts.
     *
     * @access  public
     * @return  boolean
     */
    function remove()
    {
        global $HTTP_POST_VARS;

        $items = @implode(", ", $HTTP_POST_VARS["items"]);
        $stmt = "DELETE FROM
                    " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "email_account
                 WHERE
                    ema_id IN ($items)";
        $res = $GLOBALS["db_api"]->dbh->query($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return false;
        } else {
            Support::removeEmailByAccounts($HTTP_POST_VARS["items"]);
            return true;
        }
    }


    /**
     * Method used to add a new support email account.
     *
     * @access  public
     * @return  integer 1 if the update worked, -1 otherwise
     */
    function insert()
    {
        global $HTTP_POST_VARS;

        if (empty($HTTP_POST_VARS["check_spot"])) {
            $HTTP_POST_VARS["check_spot"] = 0;
        }
        if (empty($HTTP_POST_VARS["get_only_new"])) {
            $HTTP_POST_VARS["get_only_new"] = 0;
        }
        if (empty($HTTP_POST_VARS["leave_copy"])) {
            $HTTP_POST_VARS["leave_copy"] = 0;
        }
        $stmt = "INSERT INTO
                    " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "email_account
                 (
                    ema_prj_id,
                    ema_check_spot,
                    ema_type,
                    ema_hostname,
                    ema_port,
                    ema_folder,
                    ema_username,
                    ema_password,
                    ema_get_only_new,
                    ema_leave_copy
                 ) VALUES (
                    " . $HTTP_POST_VARS["project"] . ",
                    " . $HTTP_POST_VARS["check_spot"] . ",
                    '" . Misc::escapeString($HTTP_POST_VARS["type"]) . "',
                    '" . Misc::escapeString($HTTP_POST_VARS["hostname"]) . "',
                    '" . Misc::escapeString($HTTP_POST_VARS["port"]) . "',
                    '" . Misc::escapeString(@$HTTP_POST_VARS["folder"]) . "',
                    '" . Misc::escapeString($HTTP_POST_VARS["username"]) . "',
                    '" . Misc::escapeString($HTTP_POST_VARS["password"]) . "',
                    " . $HTTP_POST_VARS["get_only_new"] . ",
                    " . $HTTP_POST_VARS["leave_copy"] . "
                 )";
        $res = $GLOBALS["db_api"]->dbh->query($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return -1;
        } else {
            return 1;
        }
    }


    /**
     * Method used to update a support email account details.
     *
     * @access  public
     * @return  integer 1 if the update worked, -1 otherwise
     */
    function update()
    {
        global $HTTP_POST_VARS;

        if (empty($HTTP_POST_VARS["check_spot"])) {
            $HTTP_POST_VARS["check_spot"] = 0;
        }
        if (empty($HTTP_POST_VARS["get_only_new"])) {
            $HTTP_POST_VARS["get_only_new"] = 0;
        }
        if (empty($HTTP_POST_VARS["leave_copy"])) {
            $HTTP_POST_VARS["leave_copy"] = 0;
        }
        $stmt = "UPDATE
                    " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "email_account
                 SET
                    ema_prj_id=" . $HTTP_POST_VARS["project"] . ",
                    ema_check_spot=" . $HTTP_POST_VARS["check_spot"] . ",
                    ema_type='" . Misc::escapeString($HTTP_POST_VARS["type"]) . "',
                    ema_hostname='" . Misc::escapeString($HTTP_POST_VARS["hostname"]) . "',
                    ema_port='" . Misc::escapeString($HTTP_POST_VARS["port"]) . "',
                    ema_folder='" . Misc::escapeString(@$HTTP_POST_VARS["folder"]) . "',
                    ema_username='" . Misc::escapeString($HTTP_POST_VARS["username"]) . "',
                    ema_password='" . Misc::escapeString($HTTP_POST_VARS["password"]) . "',
                    ema_get_only_new=" . $HTTP_POST_VARS["get_only_new"] . ",
                    ema_leave_copy=" . $HTTP_POST_VARS["leave_copy"] . "
                 WHERE
                    ema_id=" . $HTTP_POST_VARS["id"];
        $res = $GLOBALS["db_api"]->dbh->query($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return -1;
        } else {
            return 1;
        }
    }


    /**
     * Method used to get the list of available support email 
     * accounts in the system.
     *
     * @access  public
     * @return  array The list of accounts
     */
    function getList()
    {
        $stmt = "SELECT
                    *
                 FROM
                    " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "email_account
                 ORDER BY
                    ema_hostname";
        $res = $GLOBALS["db_api"]->dbh->getAll($stmt, DB_FETCHMODE_ASSOC);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return "";
        } else {
            for ($i = 0; $i < count($res); $i++) {
                $res[$i]["prj_title"] = Project::getName($res[$i]["ema_prj_id"]);
            }
            return $res;
        }
    }


    /**
     * Method used to get an associative array of the support email
     * accounts in the format of account ID => account title.
     *
     * @access  public
     * @param   integer $prj_id The project ID
     * @return  array The list of accounts
     */
    function getAssocList($prj_id)
    {
        $stmt = "SELECT
                    ema_id,
                    CONCAT(ema_username, '@', ema_hostname, ' ', ema_folder) AS ema_title
                 FROM
                    " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "email_account
                 WHERE
                    ema_prj_id=$prj_id
                 ORDER BY
                    ema_title";
        $res = $GLOBALS["db_api"]->dbh->getAssoc($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return "";
        } else {
            return $res;
        }
    }


    /**
     * Method used to get the first support email account associated
     * with the current activated project.
     *
     * @access  public
     * @return  integer The email account ID
     */
    function getEmailAccount()
    {
        $stmt = "SELECT
                    ema_id
                 FROM
                    " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "email_account
                 WHERE
                    ema_prj_id=" . Auth::getCurrentProject() . "
                 LIMIT
                    0, 1";
        $res = $GLOBALS["db_api"]->dbh->getOne($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return "";
        } else {
            return $res;
        }
    }
}

// benchmarking the included file (aka setup time)
if (APP_BENCHMARK) {
    $GLOBALS['bench']->setMarker('Included Email_Account Class');
}
?>
<?php

/*
 * This file is part of the Eventum (Issue Tracking System) package.
 *
 * @copyright (c) Eventum Team
 * @license GNU General Public License, version 2 or later (GPL-2+)
 *
 * For the full copyright and license information,
 * please see the COPYING and AUTHORS files
 * that were distributed with this source code.
 */

use Eventum\Db\DatabaseException;

/**
 * Class to handle the business logic related to the impact analysis section
 * of the view issue page. This section allows the developer to give feedback
 * on the impacts required to implement a needed feature, or to change an
 * existing application.
 */
class Impact_Analysis
{
    /**
     * Method used to insert a new requirement for an existing issue.
     *
     * @param   integer $issue_id The issue ID
     * @return  integer -1 if an error occurred or 1 otherwise
     */
    public static function insert($issue_id)
    {
        $usr_id = Auth::getUserID();
        $stmt = 'INSERT INTO
                    {{%issue_requirement}}
                 (
                    isr_iss_id,
                    isr_usr_id,
                    isr_created_date,
                    isr_requirement
                 ) VALUES (
                    ?, ?, ?, ?
                 )';
        $params = [$issue_id, $usr_id, Date_Helper::getCurrentDateGMT(), $_POST['new_requirement']];
        try {
            DB_Helper::getInstance()->query($stmt, $params);
        } catch (DatabaseException $e) {
            return -1;
        }

        Issue::markAsUpdated($issue_id);
        // need to save a history entry for this
        History::add($issue_id, $usr_id, 'impact_analysis_added', 'New requirement submitted by {user}', [
            'user' => User::getFullName($usr_id)
        ]);

        return 1;
    }

    /**
     * Method used to get the full list of requirements and impact analysis for
     * a specific issue.
     *
     * @param   integer $issue_id The issue ID
     * @return  array The full list of requirements
     */
    public static function getListing($issue_id)
    {
        $stmt = 'SELECT
                    isr_id,
                    isr_requirement,
                    isr_dev_time,
                    isr_impact_analysis,
                    A.usr_full_name AS submitter_name,
                    B.usr_full_name AS handler_name
                 FROM
                    (
                    {{%issue_requirement}},
                    {{%user}} A
                    )
                 LEFT JOIN
                    {{%user}} B
                 ON
                    isr_updated_usr_id=B.usr_id
                 WHERE
                    isr_iss_id=? AND
                    isr_usr_id=A.usr_id';
        try {
            $res = DB_Helper::getInstance()->getAll($stmt, [$issue_id]);
        } catch (DatabaseException $e) {
            return '';
        }

        if (count($res) == 0) {
            return '';
        }

        $prj_id = Issue::getProjectID($issue_id);
        foreach ($res as &$row) {
            $row['isr_requirement'] = Link_Filter::processText($prj_id, nl2br(htmlspecialchars($row['isr_requirement'])));
            $row['isr_impact_analysis'] = Link_Filter::processText($prj_id, nl2br(htmlspecialchars($row['isr_impact_analysis'])));
            $row['formatted_dev_time'] = Misc::getFormattedTime($row['isr_dev_time']);
        }

        return $res;
    }

    /**
     * Method used to update an existing requirement with the appropriate
     * impact analysis.
     *
     * @param   integer $isr_id The requirement ID
     * @return  integer -1 if an error occurred or 1 otherwise
     */
    public static function update($isr_id)
    {
        $stmt = 'SELECT
                    isr_iss_id
                 FROM
                    {{%issue_requirement}}
                 WHERE
                    isr_id=?';
        $issue_id = DB_Helper::getInstance()->getOne($stmt, [$isr_id]);

        // we are storing minutes, not hours
        $dev_time = $_POST['dev_time'] * 60;
        $usr_id = Auth::getUserID();
        $stmt = 'UPDATE
                    {{%issue_requirement}}
                 SET
                    isr_updated_usr_id=?,
                    isr_updated_date=?,
                    isr_dev_time=?,
                    isr_impact_analysis=?
                 WHERE
                    isr_id=?';
        $params = [$usr_id, Date_Helper::getCurrentDateGMT(), $dev_time, $_POST['impact_analysis'], $isr_id];
        try {
            DB_Helper::getInstance()->query($stmt, $params);
        } catch (DatabaseException $e) {
            return -1;
        }

        Issue::markAsUpdated($issue_id);
        // need to save a history entry for this
        History::add($issue_id, $usr_id, 'impact_analysis_updated', 'Impact analysis submitted by {user}', [
            'user' => User::getFullName($usr_id)
        ]);

        return 1;
    }

    /**
     * Method used to remove an existing set of requirements.
     *
     * @return  integer -1 if an error occurred or 1 otherwise
     */
    public static function remove()
    {
        $items = $_POST['item'];
        $itemlist = DB_Helper::buildList($items);

        $stmt = "SELECT
                    isr_iss_id
                 FROM
                    {{%issue_requirement}}
                 WHERE
                    isr_id IN ($itemlist)";
        $issue_id = DB_Helper::getInstance()->getOne($stmt, $items);

        $stmt = "DELETE FROM
                    {{%issue_requirement}}
                 WHERE
                    isr_id IN ($itemlist)";
        try {
            DB_Helper::getInstance()->query($stmt, $items);
        } catch (DatabaseException $e) {
            return -1;
        }

        Issue::markAsUpdated($issue_id);
        // need to save a history entry for this
        $usr_id = Auth::getUserID();
        History::add($issue_id, $usr_id, 'impact_analysis_removed', 'Impact analysis removed by {user}', [
            'user' => User::getFullName($usr_id)
        ]);

        return 1;
    }

    /**
     * Method used to remove all of the requirements associated with a set of
     * issue IDs.
     *
     * @param   array $ids The list of issue IDs
     * @return  boolean
     */
    public static function removeByIssues($ids)
    {
        $items = DB_Helper::buildList($ids);
        $stmt = "DELETE FROM
                    {{%issue_requirement}}
                 WHERE
                    isr_iss_id IN ($items)";
        try {
            DB_Helper::getInstance()->query($stmt, $ids);
        } catch (DatabaseException $e) {
            return false;
        }

        return true;
    }
}

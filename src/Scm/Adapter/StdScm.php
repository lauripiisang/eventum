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

namespace Eventum\Scm\Adapter;

use Eventum\Model\Entity;
use Eventum\Model\Repository\CommitRepository;
use InvalidArgumentException;
use Issue;

/**
 * Standard SCM handler
 *
 * @package Eventum\Scm
 */
class StdScm extends AbstractScmAdapter
{
    /**
     * @inheritdoc
     */
    public function can()
    {
        // require 'scm' GET parameter to be 'svn' or 'git'
        return in_array($this->request->query->get('scm'), array('svn', 'git'));
    }

    /**
     * @inheritdoc
     */
    public function process()
    {
        $payload = $this->getPayload();

        $issues = $payload->getIssues();
        if (!$issues) {
            throw new InvalidArgumentException('No issues provided');
        }

        $ci = $payload->createCommit();
        $repo = new Entity\CommitRepo($ci->getScmName());

        if (!$repo->branchAllowed($payload->getBranch())) {
            throw new \InvalidArgumentException("Branch not allowed: {$payload->getBranch()}");
        }

        $ci->setChangeset($payload->getCommitId());

        $cr = CommitRepository::create();
        $cr->preCommit($ci, $payload);
        $ci->save();

        // save issue association
        foreach ($issues as $issue_id) {
            Entity\IssueCommit::create()
                ->setCommitId($ci->getId())
                ->setIssueId($issue_id)
                ->save();

            // print report to stdout of commits so hook could report status back to commiter
            $details = Issue::getDetails($issue_id);
            echo "#$issue_id - {$details['iss_summary']} ({$details['sta_title']})\n";
        }

        // save commit files
        foreach ($payload->getFiles() as $file) {
            $cf = Entity\CommitFile::create()
                ->setCommitId($ci->getId())
                ->setFilename($file['file'])
                ->setOldVersion($file['old_version'])
                ->setNewVersion($file['new_version']);
            $cf->save();
            $ci->addFile($cf);
        }

        foreach ($issues as $issue_id) {
            $cr->addCommit($issue_id, $ci);
        }
    }

    /*
     * Get Hook Payload
     */
    private function getPayload()
    {
        return new Entity\StdScmPayload($this->request->query);
    }
}

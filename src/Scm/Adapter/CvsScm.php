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

class CvsScm extends AbstractScmAdapter
{
    /**
     * @inheritdoc
     */
    public function can()
    {
        // require 'scm=cvs' GET parameter
        return $this->request->query->get('scm') == 'cvs';
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

        $cr = CommitRepository::create();

        $commitId = $payload->getCommitId();
        $ci = Entity\Commit::create()->findOneByChangeset($commitId);

        // if ci already seen, skip adding commit and issue association
        // but still process commit files.
        // as cvs handler sends files in subdirs as separate requests
        if (!$ci) {
            $ci = $payload->createCommit();
            // set this last, as it may need other $ci properties
            $ci->setChangeset($commitId ?: $this->generateCommitId($ci));

            $repo = new Entity\CommitRepo($ci->getScmName());
            if (!$repo->branchAllowed($ci->getBranch())) {
                throw new \InvalidArgumentException("Branch not allowed: {$ci->getBranch()}");
            }

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

    /**
     * Seconds to allow commit date to differ to consider them as same commit id
     */
    const COMMIT_TIME_DRIFT = 10;

    /**
     * Generate commit id
     *
     * @param Entity\Commit $ci
     * @return string
     */
    private function generateCommitId(Entity\Commit $ci)
    {
        $seed = array(
            $ci->getCommitDate()->getTimestamp() / self::COMMIT_TIME_DRIFT,
            $ci->getAuthorName(),
            $ci->getAuthorEmail(),
            $ci->getMessage(),
        );
        $checksum = md5(implode('', $seed));

        // CVS commitid is 16 byte length base62 encoded random and seems always end with z0
        // so we use 14 bytes from md5, and z1 suffix to get similar (but not conflicting) commitid
        return substr($checksum, 1, 14) . 'z1';
    }

    /*
     * Get Hook Payload
     */
    private function getPayload()
    {
        return new Entity\StdScmPayload($this->request->query);
    }
}

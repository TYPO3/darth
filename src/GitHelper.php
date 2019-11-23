<?php

declare(strict_types = 1);

namespace TYPO3\Darth;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use GitWrapper\Event\GitOutputStreamListener;
use GitWrapper\GitException;
use GitWrapper\GitWorkingCopy;
use GitWrapper\GitWrapper;
use Symfony\Component\Process\Process;

/**
 * Support to check and fetch certain tasks, usually in conjunction with the GitWorkingCopy object.
 */
class GitHelper
{
    const LOG_DELIMITER_FIELD = '%x00%x00,%x00%x00';
    const LOG_DELIMITER_ITEM = '%x00%x00---%x00%x00';
    const PHP_DELIMITER_FIELD = "\x00\x00,\x00\x00";
    const PHP_DELIMITER_ITEM = "\x00\x00---\x00\x00";

    /**
     * The absolute path to the local GIT repository.
     *
     * @var string
     */
    private $workingDirectory;

    /**
     * Whether the GitWrapper that creates the git object should have the output listener added.
     *
     * @var bool
     */
    private $addOutputListener;

    /**
     * The current instance when initialized to be worked on.
     *
     * @var GitWorkingCopy
     */
    private $git;

    /**
     * Sets up the working directory structure needed to continue, usually called right before
     * "initializeCleanWorkingCopy()".
     *
     * @param string $workingDirectory
     * @param bool   $isVerbose
     */
    public function __construct(string $workingDirectory = null, bool $isVerbose = null)
    {
        $this->workingDirectory = $workingDirectory;
        $this->addOutputListener = $isVerbose;
    }

    /**
     * Initializes the working directory of an existing (!) git repository
     * and resets the current state to the latest version, then also does a fetch() command.
     *
     * @param string $revision the revision to be checked out (optionally)
     *
     * @return GitWorkingCopy the initialized GitWorkingCopy object to do tasks on it
     */
    public function initializeCleanWorkingCopy($revision = null): GitWorkingCopy
    {
        $gitWrapper = new GitWrapper();
        if ($this->addOutputListener) {
            $gitOutputListener = new GitOutputStreamListener();
            $gitWrapper->addOutputListener($gitOutputListener);
        }
        $this->git = $gitWrapper->workingCopy($this->workingDirectory);
        $this->git->clean('-d', '-f')
            ->reset('--hard')
            ->fetch('--tags');
        if ($revision) {
            $this->git->checkout($revision);
        }

        return $this->git;
    }

    /**
     * Check if a signing key is set, and throws an exception if this is not the case
     * Otherwise returns it. This is used to sign commits, but also used when using GPG signing.
     *
     * @return string the found signing key
     */
    public function getSigningKey(): string
    {
        try {
            $this->git->clearOutput();
            $this->git->config('user.signingkey');
            $signingKey = trim($this->git->getOutput());
        } catch (GitException $e) {
        }

        if (empty($signingKey) || !is_string($signingKey)) {
            throw new \RuntimeException('You have to set a signing key in order to do a release.', 1498581824);
        }

        return $signingKey;
    }

    /**
     * Shorthand function to get the current SHA1 of the working copy (HEAD).
     *
     * @return string a SHA1 of the revision sitting on
     */
    public function getCurrentRevision(): string
    {
        $this->git->clearOutput();
        $this->git->run(['rev-parse', 'HEAD']);

        return trim($this->git->getOutput());
    }

    /**
     * Fetches all branches, and greps for all remote branches that either start with a number "9.0" or with "...-9.0)
     * If none matching is found, then "origin/master" is assumed.
     *
     * @param string $nextVersion the version to be expected, only the minor version parts (the first two parts of the version) are evaluated for the branch
     *
     * @return string the remote branch, with the name of the remote before. "origin/master" or "origin/9.0"
     */
    public function findRemoteBranch(string $nextVersion): string
    {
        $versionParts = explode('.', $nextVersion);
        $nextMinorVersion = $versionParts[0] . '.' . $versionParts[1];

        $branches = $this->git->getBranches();
        $usedBranch = null;
        foreach ($branches as $branch) {
            if (preg_match('/remotes\/origin\/([A-z0-9_-]+' . str_replace('.', '-', $nextMinorVersion) . '|' . str_replace('.', '\.', $nextMinorVersion) . ')/', $branch)) {
                // subtract the "remotes/" part
                $usedBranch = substr($branch, 8);
                break;
            }
        }
        if ($usedBranch === null) {
            $usedBranch = 'origin/master';
        }

        return $usedBranch;
    }

    /**
     * Returns the last tag on the HEAD other than the current one.
     *
     * Please note that this method does not respect tags on the SAME commit.
     * Imagine:
     * - 9.0.4 and 9.0.5 are the same commit, it will return "9.0.3"
     *
     * @return string the name of the tag found
     */
    public function getPreviousTagName(): string
    {
        $this->git->clearOutput();
        $this->git->run(['describe', '--abbrev=0', '--match=*.*.*', 'HEAD^']);
        $previousTag = $this->git->getOutput();

        return trim($previousTag);
    }

    /**
     * Returns all commit log entries (as --oneline) from the current head to the previous tag found before HEAD.
     *
     * @param string $previousTag
     * @return array each change log entry in one part of the array
     */
    public function getChangeLogUntilPreviousTag(string $previousTag = null): array
    {
        if ($previousTag === null) {
            $previousTag = $this->getPreviousTagName();
        }

        $options = [
            'oneline' => true,
            'date' => 'short',
        ];
        $pretty = getenv('GIT_CHANGELOG_PRETTY');
        if (!empty($pretty)) {
            $options['pretty'] = $pretty;
        }

        $this->git->clearOutput();
        $this->git->log($previousTag . '..HEAD', $options);
        $changeLog = $this->git->getOutput();

        return explode("\n", trim($changeLog));
    }

    /**
     * Solves Git changes (containing some optional information as given in $grep).
     * Each of the result items contains subject, body and date.
     *
     * @param string|null $previousTag
     * @param string|null $grep
     * @return array
     */
    public function getChangeItemsUntilPreviousTag(string $previousTag = null, string $grep = null): array
    {
        if ($previousTag === null) {
            $previousTag = $this->getPreviousTagName();
        }

        $options = [
            'pretty' => '%s' . self::LOG_DELIMITER_FIELD
                . '%b' . self::LOG_DELIMITER_FIELD
                . '%ci' . self::LOG_DELIMITER_FIELD
                . '///' . self::LOG_DELIMITER_ITEM,
        ];
        if ($grep !== null) {
            $options['grep'] = $grep;
        }

        $this->git->clearOutput();
        $this->git->log($previousTag . '..HEAD', $options);
        $items = array_filter(
            array_map(
                'trim',
                explode(self::PHP_DELIMITER_ITEM, $this->git->getOutput())
            )
        );

        $items = array_map(
            function (string $item) {
                $fields = array_filter(
                    explode(self::PHP_DELIMITER_FIELD, $item)
                );
                return [
                    'subject' => $fields[0],
                    'body' => $fields[1],
                    'date' => $fields[2],
                ];
            },
            $items
        );

        return $items;
    }

    /**
     * Returns a list of all tags "git tag -l", but only tags starting with a number or
     * with "v" and then a number.
     *
     * @return array an array with all tags
     */
    protected function getVersionTags(): array
    {
        $this->git->clearOutput();
        $this->git->tag('-l');
        $tags = explode("\n", $this->git->getOutput());
        rsort($tags);
        $finalTags = [];
        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) {
                continue;
            }
            if ($tagName[0] === 'v' && is_numeric($tagName[1])) {
                $finalTags[] = $tagName;
            } elseif (is_numeric($tagName[0])) {
                $finalTags[] = $tagName;
            }
        }

        return $finalTags;
    }

    /**
     * Finds the next version given by the tags within GIT.
     *
     * Checks the tags in Git of a current minor release what the next bugfix release would be.
     *
     * If a specific version like "8.7.4" is given, it only checks if the version is not in use,
     * otherwise throws an exception.
     *
     * @param string $givenVersion can be "8.7" or "8.7.4"
     *
     * @return string the exact version number to be used
     */
    public function findNextVersion($givenVersion): string
    {
        $specificVersionGiven = count(explode('.', $givenVersion)) > 2;

        // Find available tags, and check what the latest tag in this version was
        $tags = $this->getVersionTags();

        $tagsInUseOfMinorVersion = [];
        foreach ($tags as $tagName) {
            if ($specificVersionGiven && (strpos($tagName, $givenVersion) === 0 || strpos($tagName, 'v' . $givenVersion) === 0)) {
                // @todo how to deal with alpha, beta versions and RCs!
                throw new \RuntimeException('A version for ' . $givenVersion . ' already exists', 1498742777);
            }

            if (strpos($tagName, $givenVersion) === 0 || strpos($tagName, 'v' . $givenVersion) === 0) {
                $tagsInUseOfMinorVersion[] = $tagName;
            }
        }

        // A specific version is given and no tag exists => fine, but use at own care
        // @todo: write a warning
        if ($specificVersionGiven) {
            $nextVersion = $givenVersion;
        } elseif (empty($tagsInUseOfMinorVersion)) {
            $nextVersion = $givenVersion . '.0';
        } else {
            // There is a minor version, already and we up the last number
            $nextVersion = array_shift($tagsInUseOfMinorVersion);
            foreach ($tagsInUseOfMinorVersion as $lastUsedTag) {
                if (version_compare($lastUsedTag, $nextVersion) >= 0) {
                    $nextVersion = $lastUsedTag;
                }
            }
            // raise the version number
            $versionParts = explode('.', $nextVersion);
            ++$versionParts[2];
            $nextVersion = implode('.', $versionParts);
        }

        return ltrim($nextVersion, 'v');
    }

    /**
     * Calls git push origin HEAD:refs/for/$remoteBranch and then does an autoapprove by Gerrit.
     *
     * @param string $remoteBranch
     * @param string $commitHash
     */
    public function pushAndApproveWithGerrit(string $remoteBranch, string $commitHash)
    {
        $this->git->push('origin', 'HEAD:refs/for/' . $remoteBranch);
        // Auto approve by gerrit
        if (getenv('GERRIT_AUTO_APPROVE_COMMAND')) {
            (new Process(getenv('GERRIT_AUTO_APPROVE_COMMAND') . ' ' . $commitHash, $this->workingDirectory))->run();
        }
    }
}

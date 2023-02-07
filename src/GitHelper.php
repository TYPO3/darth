<?php

declare(strict_types=1);

namespace TYPO3\Darth;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Gitonomy\Git\Repository;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Support to check and fetch certain tasks, usually in conjunction with the Git repository object.
 */
class GitHelper
{
    const LOG_DELIMITER_FIELD = '%x00%x00,%x00%x00';
    const LOG_DELIMITER_ITEM = '%x00%x00---%x00%x00';
    const PHP_DELIMITER_FIELD = "\x00\x00,\x00\x00";
    const PHP_DELIMITER_ITEM = "\x00\x00---\x00\x00";

    /**
     * The absolute path to the local GIT repository.
     */
    private string $workingDirectory;

    /**
     * Whether the Git Repository that creates the git object should have the output listener added.
     */
    private bool $isVerbose;

    /**
     * The current instance when initialized to be worked on.
     */
    private ?Repository $git;

    /**
     * Sets up the working directory structure needed to continue, usually called right before
     * "initializeCleanWorkingCopy()".
     */
    public function __construct(string $workingDirectory, bool $isVerbose)
    {
        $this->workingDirectory = $workingDirectory;
        $this->isVerbose = $isVerbose;
    }

    /**
     * Initializes the working directory of an existing (!) git repository
     * and resets the current state to the latest version, then also does a fetch() command.
     *
     * @param string|null $revision the revision to be checked out (optionally)
     * @return Repository the initialized Git repository object to do tasks on it
     */
    public function initializeCleanWorkingCopy(string $revision = null): Repository
    {
        $this->git = new Repository($this->workingDirectory, ['debug' => $this->isVerbose]);
        $this->git->run('clean', ['-d', '-f']);
        $this->git->run('reset', ['--hard']);
        $this->git->run('fetch', ['--tags']);
        if ($revision) {
            $this->git->run('checkout', [$revision]);
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
            $signingKey = $this->git->run('config', ['user.signingkey']);
            $signingKey = trim($signingKey);
        } catch (\Gitonomy\Git\Exception\RuntimeException $e) {
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
        return trim($this->git->run('rev-parse', ['HEAD']));
    }

    /**
     * Fetches all branches, and greps for all remote branches that either start with a number "9.0" or with "...-9.0)
     * If none matching is found, then "origin/main" is assumed.
     *
     * @param string $nextVersion the version to be expected, only the minor version parts (the first two parts of the version) are evaluated for the branch
     *
     * @return string the remote branch, with the name of the remote before. "origin/main" or "origin/9.0"
     */
    public function findRemoteBranch(string $nextVersion): string
    {
        $versionParts = explode('.', $nextVersion);
        $nextMinorVersion = $versionParts[0] . '.' . $versionParts[1];

        $branches = $this->git->getReferences()->getBranches();
        $usedBranch = null;
        foreach ($branches as $branchObj) {
            $branch = $branchObj->getName();
            if (preg_match('/origin\/([A-z0-9_-]+' . str_replace('.', '-', $nextMinorVersion) . '|' . str_replace('.', '\.', $nextMinorVersion) . ')/', $branch)) {
                $usedBranch = $branch;
                break;
            }
        }
        if ($usedBranch === null) {
            $usedBranch = 'origin/main';
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
        $previousTag = $this->git->run('describe', ['--abbrev=0', '--match=*.*.*', 'HEAD^']);
        return trim($previousTag);
    }

    /**
     * Returns all commit log entries (as --oneline) from the current head to the previous tag found before HEAD.
     *
     * @param string|null $previousTag
     * @return array each change log entry in one part of the array
     */
    public function getChangeLogUntilPreviousTag(string $previousTag = null): array
    {
        if ($previousTag === null) {
            $previousTag = $this->getPreviousTagName();
        }

        $arguments = [
            $previousTag . '..HEAD',
            '--oneline',
            '--date=short',
        ];
        $pretty = getenv('GIT_CHANGELOG_PRETTY');
        if (!empty($pretty)) {
            $arguments[] = '--pretty=' . $pretty;
        }

        $changeLog = $this->git->run('log', $arguments);

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
            $previousTag . '..HEAD',
            '--pretty=%s' . self::LOG_DELIMITER_FIELD
                . '%b' . self::LOG_DELIMITER_FIELD
                . '%ci' . self::LOG_DELIMITER_FIELD
                . '///' . self::LOG_DELIMITER_ITEM,
        ];
        if ($grep !== null) {
            $options[] = '--grep=' . $grep;
        }

        $output = $this->git->run('log', $options);
        $items = array_filter(
            array_map(
                'trim',
                explode(self::PHP_DELIMITER_ITEM, $output)
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
        $tagObjects = $this->git->getReferences()->getTags();
        $tags = [];
        foreach ($tagObjects as $tagObject) {
            $tags[] = $tagObject->getName();
        }
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
     * @return string the exact version number to be used
     */
    public function findNextVersion(string $givenVersion): string
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
     * Calls git push origin HEAD:refs/for/$remoteBranch
     */
    public function pushToGerrit(string $remoteBranch): void
    {
        $this->git->run('push', ['origin', 'HEAD:refs/for/' . $remoteBranch]);
    }

    /**
     * Calls auto-approve command for given $commitHash in Gerrit.
     */
    public function approveWithGerrit(string $commitHash): void
    {
        // Auto approve by gerrit
        if (getenv('GERRIT_AUTO_APPROVE_COMMAND')) {
            $process = Process::fromShellCommandline(getenv('GERRIT_AUTO_APPROVE_COMMAND') . ' ' . $commitHash, $this->workingDirectory);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace TYPO3\Darth\Service;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * Goes through the source code and updates the code where it applies.
 *
 * @todo: document the full behaviour of this logic and add tests
 */
class FileVersionModificationService
{
    /**
     * @param string|null $currentVersion current version, used "-dev" flag for replacements
     */
    public function updateFilesWithVersions(string $workingDirectory, array $rawConfiguration, bool $sprintRelease, string $nextVersion, string $currentVersion = null, ?SymfonyStyle $io = null): void
    {
        $versionParts = explode('.', $nextVersion);
        $nextMinorVersion = $versionParts[0] . '.' . $versionParts[1];

        $configuration = [];
        // Split | in `file` property into distinct configuration entries
        foreach ($rawConfiguration as $key => $fileDetails) {
            $files = explode('|', $fileDetails['file']);
            foreach ($files as $file) {
                $configuration[] = [
                    ...$fileDetails,
                    'file' => $file,
                ];
            }
        }

        // now find the files you want to modify
        foreach ($configuration as $fileDetails) {
            try {
                $finder = new Finder();
                $finder->name(basename($fileDetails['file']))
                    ->ignoreUnreadableDirs()
                    ->in($workingDirectory . '/' . dirname($fileDetails['file']));
            } catch (\InvalidArgumentException $exception) {
                // skips directory search patterns that do not exist in older versions
                // (e.g. `Build/composer/composer.dist.json` introduced with TYPO3 v11)
                if ($io?->isVerbose()) {
                    $io?->warning($exception->getMessage());
                }
                continue;
            }

            foreach ($finder as $foundFile) {
                $fileContents = $foundFile->getContents();
                $updatedFileContents = $fileContents;
                switch ($fileDetails['type']) {
                    case 'nextBugfixVersion':
                        if (!$currentVersion) {
                            continue 2;
                        }
                        // just replace the just released version with the latest version
                        $updatedFileContents = preg_replace_callback('/' . $fileDetails['pattern'] . '/u', function ($matches) use ($nextVersion) {
                            return str_replace($matches[1], $nextVersion, $matches[0]);
                        }, $fileContents);
                        break;
                    case 'bugfixVersion':
                        // just replace it with the latest version
                        $updatedFileContents = preg_replace_callback('/' . $fileDetails['pattern'] . '/u', function ($matches) use ($nextVersion) {
                            return str_replace($matches[1], $nextVersion, $matches[0]);
                        }, $fileContents);
                        break;
                    case 'nextDevVersion':
                        if (!$currentVersion) {
                            continue 2;
                        }
                        // just replace the pattern with "1.2.3-dev"
                        $updatedFileContents = preg_replace_callback('/' . $fileDetails['pattern'] . '/u', function ($matches) use ($nextVersion) {
                            return str_replace($matches[1], $nextVersion . '-dev', $matches[0]);
                        }, $fileContents);
                        break;
                    case 'nextDevBranch':
                        if (!$currentVersion) {
                            continue 2;
                        }
                        // just replace the pattern with "1.2.*@dev"
                        $updatedFileContents = preg_replace_callback('/' . $fileDetails['pattern'] . '/u', function ($matches) use ($nextMinorVersion) {
                            return str_replace($matches[1], $nextMinorVersion . '.*@dev', $matches[0]);
                        }, $fileContents);
                        break;
                    case 'nextDevBranchAlias':
                        if (!$currentVersion || !$sprintRelease) {
                            continue 2;
                        }
                        // just replace the pattern with "1.2.x-dev"
                        $updatedFileContents = preg_replace_callback('/' . $fileDetails['pattern'] . '/u', function ($matches) use ($nextMinorVersion) {
                            return str_replace($matches[1], $nextMinorVersion . '.x-dev', $matches[0]);
                        }, $fileContents);
                        break;
                    case 'minorVersion':
                        // just replace it with the latest version
                        $updatedFileContents = preg_replace_callback('/' . $fileDetails['pattern'] . '/u', function ($matches) use ($nextMinorVersion) {
                            return str_replace($matches[1], $nextMinorVersion, $matches[0]);
                        }, $fileContents);
                        break;
                }

                if ($fileContents !== $updatedFileContents && $updatedFileContents !== false) {
                    file_put_contents((string)$foundFile, $updatedFileContents);
                    if ($io?->isVerbose()) {
                        $io?->writeln('Updated ' . $fileDetails['type'] . ' for file ' . $foundFile);
                    }
                }
            }
        }
    }

}

<?php

declare(strict_types = 1);

namespace TYPO3\Darth;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Custom application class that also deals with some files.
 */
class Application extends \Symfony\Component\Console\Application
{
    /**
     * Fetches a sub-key of the conf/release.yaml file
     * by parsing the Yaml file.
     *
     * @param mixed $key
     *
     * @return mixed the parsed configuration (can be either of type array, string or int)
     */
    public function getConfiguration($key)
    {
        $configurationFile = $this->getConfigurationFileName('release.yaml');
        $configuration = Yaml::parse(file_get_contents($configurationFile));

        return $configuration[$key] ?? null;
    }

    /**
     * Fetches a file within the conf/ folder of this package.
     *
     * @param string $fileName
     *
     * @return string the full filename + path
     */
    public function getConfigurationFileName(string $fileName): string
    {
        $fileName = $this->getMainDirectory() . '/conf/' . $fileName;
        if (!file_exists($fileName)) {
            throw new \RuntimeException('The configuration file ' . $fileName . ' does not exist', 1498665638);
        }

        return $fileName;
    }

    /**
     * Checks for a properly configured working/ directory, and if the environment variable is set
     * Also checks if a .git folder exists within the working directory.
     *
     * Mainly used to get the path to the working directory so Git can operate on this folder.
     *
     * @param bool $skipExistenceCheck if set, the method does not check if the folder contains a valid git repository
     *
     * @return string the name of the folder with no trailing slash
     */
    public function getWorkingDirectory($skipExistenceCheck = false): string
    {
        $workingDirectory = getenv('WORKING_DIRECTORY');
        if (empty($workingDirectory)) {
            throw new \RuntimeException('Could not find environment variable WORKING_DIRECTORY to work on a git repository', 1498581320);
        }
        if (substr($workingDirectory, -1) === '/') {
            throw new \RuntimeException('The environment variable WORKING_DIRECTORY must not end with a slash', 1498728445);
        }

        $workingDirectory = $this->getMainDirectory() . '/' . $workingDirectory;

        if (!$skipExistenceCheck && (!is_dir($workingDirectory) || !is_dir($workingDirectory . '/.git'))) {
            throw new \RuntimeException('WORKING_DIRECTORY is not a git repository, cannot continue without a valid git repository', 1498581383);
        }

        return $workingDirectory;
    }

    /**
     * Checks for a properly configured announce/ directory, and if the environment variable is set
     * Also checks if a .git folder exists within the working directory.
     *
     * @return string the name of the folder with no trailing slash
     */
    public function getAnnounceDirectory(): string
    {
        $announceDirectory = getenv('ANNOUNCE_DIRECTORY');
        if (empty($announceDirectory)) {
            throw new \RuntimeException('Could not find environment variable ANNOUNCE_DIRECTORY', 1522936006);
        }
        if (substr($announceDirectory, -1) === '/') {
            throw new \RuntimeException('The environment variable ANNOUNCE_DIRECTORY must not end with a slash', 1522936007);
        }

        $announceDirectory = $this->getMainDirectory() . '/' . $announceDirectory;

        if (!is_dir($announceDirectory) || !is_writable($announceDirectory)) {
            throw new \RuntimeException('ANNOUNCE_DIRECTORY is not a writable directory', 1522936008);
        }

        return $announceDirectory;
    }

    /**
     * Checks for a properly configured security/ directory, and if the environment variable is set
     * Also checks if a .git folder exists within the working directory.
     *
     * @return string the name of the folder with no trailing slash
     */
    public function getSecurityDirectory(): string
    {
        $securityDirectory = getenv('SECURITY_DIRECTORY');
        if (empty($securityDirectory)) {
            throw new \RuntimeException('Could not find environment variable SECURITY_DIRECTORY', 1522936016);
        }
        if (substr($securityDirectory, -1) === '/') {
            throw new \RuntimeException('The environment variable SECURITY_DIRECTORY must not end with a slash', 1522936017);
        }

        $securityDirectory = $this->getMainDirectory() . '/' . $securityDirectory;

        if (!file_exists($securityDirectory)) {
            mkdir($securityDirectory, 0755, true);
        }
        if (!is_writable($securityDirectory)) {
            throw new \RuntimeException('SECURITY_DIRECTORY is not a writable directory', 1522936018);
        }

        return $securityDirectory;
    }

    /**
     * Initializes the publish/ directory where the artefacts are created later-on
     * Ensures that the folder exists as well.
     *
     * @param bool $resetHard if set, then the folder will be destroyed and re-created empty
     *
     * @return string the full path to the publish directory with no trailing slash
     */
    public function initializePublishDirectory($resetHard = false): string
    {
        $publishDirectory = getenv('PUBLISH_DIRECTORY');
        if (empty($publishDirectory)) {
            throw new \RuntimeException('Could not find environment variable PUBLISH_DIRECTORY to work on a git repository', 1498581540);
        }
        if (substr($publishDirectory, -1) === '/') {
            throw new \RuntimeException('The environment variable WORKING_DIRECTORY must not end with a slash', 1498728455);
        }

        $publishDirectory = $this->getMainDirectory() . '/' . $publishDirectory;

        if ($resetHard && is_dir($publishDirectory)) {
            $this->resetDirectory($publishDirectory);
        } else {
            if (!is_dir($publishDirectory)) {
                mkdir($publishDirectory);
            }
        }

        if (!is_dir($publishDirectory) || !is_writable($publishDirectory)) {
            throw new \RuntimeException('PUBLISH_DIRECTORY is not existing and nothing can be done here', 1498581532);
        }

        return $publishDirectory;
    }

    /**
     * Returns the artefacts folder for a given version, something like "/home/benni/typo3-builder/publish/8.7.4/artefacts".
     *
     * @param string $version the name of the version, used as a subdirectory
     *
     * @return string the full path to the artefacts directory
     */
    public function getArtefactsDirectory(string $version): string
    {
        $directory = $this->initializePublishDirectory(false) . '/' . ltrim($version, 'v') . '/' . getenv('ARTEFACTS_DIRECTORY');
        if (!@is_dir($directory)) {
            mkdir($directory);
        }

        return $directory;
    }

    /**
     * Removes and re-creates a directory - use with care, as it removes all contents and does not do a check.
     *
     * @param $directory
     */
    public function resetDirectory($directory)
    {
        // remove any old info
        if (@is_dir($directory)) {
            (new Process('sudo rm -rf ' . $directory))->run();
        }
        mkdir($directory);
    }

    /**
     * Returns the root directory of this PHP project application, only used internally in this class,
     * based on the entry-script (assumed to be located within bin/).
     *
     * @return string the root directory with no trailing slash
     */
    private function getMainDirectory(): string
    {
        $scriptEntryPath = realpath($_SERVER['PWD'] . '/' . $_SERVER['PHP_SELF']);

        return dirname(dirname($scriptEntryPath));
    }
}

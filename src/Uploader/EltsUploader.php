<?php
declare(strict_types=1);
namespace TYPO3\Darth\Uploader;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Process\Process;
use TYPO3\Darth\Exception\EltsPublishException;

class EltsUploader implements UploaderInterface
{
    /**
     * @var string
     */
    private $userName;

    /**
     * @var string
     */
    private $server;

    /**
     * @var string
     */
    private $packageLocation;

    public function __construct()
    {
        $this->userName = getenv('ELTS_REMOTE_USER');
        $this->server = getenv('ELTS_REMOTE_SERVER');
        $this->packageLocation = rtrim(getenv('ELTS_REMOTE_LOCATION'), '/') . '/';
    }

    public function getUploadStartMessage(): string
    {
        return sprintf('Publishing as %s to %s', $this->userName, $this->packageLocation);
    }

    public function upload(string $file, string $blobName)
    {
        $path = $this->uploadPackage($file, $blobName);
        $this->fixPermissions($path);
    }

    /**
     * Uploads the package to the server
     *
     * @param string $localFile
     * @param string $remoteFileName
     * @return string
     */
    private function uploadPackage(string $localFile, string $remoteFileName): string
    {
        $fullPath = $this->packageLocation . $remoteFileName;
        $remoteTargetDirectory = dirname($fullPath);
        $this->createTargetDirectory($remoteTargetDirectory);

        $process = new Process(['scp', $localFile, $this->server . ':' . $fullPath]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new EltsPublishException($process->getErrorOutput());
        }

        return $this->packageLocation . $remoteFileName;
    }

    /**
     * Fixes permissions of the uploaded package
     *
     * @param string $remoteFile
     */
    private function fixPermissions(string $remoteFile)
    {
        $subCommand = sprintf('sudo chown %1$s:%1$s %2$s', escapeshellarg($this->userName), escapeshellarg($remoteFile));
        $this->executeRemoteCommand($subCommand);
    }

    /**
     * Fixes permissions of the uploaded package
     *
     * @param string $remoteTargetDirectory
     */
    private function createTargetDirectory(string $remoteTargetDirectory)
    {
        $subCommand = sprintf('sudo [ -d %1$s ] || mkdir %1$s', escapeshellarg($remoteTargetDirectory));
        $this->executeRemoteCommand($subCommand);
        $this->fixPermissions($remoteTargetDirectory);
    }

    private function executeRemoteCommand(string $command)
    {
        $process = new Process(['ssh', '-t', $this->server, $command]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new EltsPublishException($process->getErrorOutput());
        }
    }
}

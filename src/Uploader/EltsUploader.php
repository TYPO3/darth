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

    public function upload(string $file, string $blobName): void
    {
        $fullPath = $this->packageLocation . $blobName;
        $remoteTargetDirectory = dirname($fullPath);
        $this->createTargetDirectory($remoteTargetDirectory);

        $process = new Process(['scp', $file, $this->userName . '@' . $this->server . ':' . $fullPath]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new EltsPublishException($process->getErrorOutput());
        }
    }

    /**
     * Fixes permissions of the uploaded package
     */
    private function createTargetDirectory(string $remoteTargetDirectory): void
    {
        $subCommand = sprintf('[ -d %1$s ] || mkdir %1$s', escapeshellarg($remoteTargetDirectory));
        $this->executeRemoteCommand($subCommand);
    }

    private function executeRemoteCommand(string $command): void
    {
        $process = new Process(['ssh', '-t', $this->userName . '@' . $this->server, $command]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new EltsPublishException($process->getErrorOutput());
        }
    }
}

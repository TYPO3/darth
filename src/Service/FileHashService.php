<?php
declare(strict_types = 1);
namespace TYPO3\Darth\Service;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Process\Process;

class FileHashService
{
    /**
     * @var array
     */
    private $commands;

    /**
     * @var string
     */
    private $workingDirectory;

    public function __construct(array $commands, string $workingDirectory)
    {
        $this->commands = $commands;
        $this->workingDirectory = $workingDirectory;
    }

    public function multiple(array $algorithmMap, array $fileMap)
    {
        $result = [];
        foreach ($fileMap as $fileKey => $filePattern) {
            foreach ($algorithmMap as $algorithmKey => $algorithmName) {
                $hash = preg_replace(
                    '#^([a-z0-9]+)\b.*$#i',
                    '${1}',
                    $this->execute($algorithmName, $filePattern)
                );
                $result[$fileKey][$algorithmKey] = $hash;
            }
        }
        return $result;
    }

    public function execute(string $algorithmName, string $filePattern): string
    {
        $command = $this->getCommand($algorithmName, $filePattern);
        $process = new Process($command, $this->workingDirectory);
        $process->run();
        if ($process->getExitCode() !== 0) {
            throw new \RuntimeException(
                sprintf(
                    "Command \"%s\" failed:\n%s",
                    $command,
                    $process->getErrorOutput()
                ),
                1522925350
            );
        }
        return trim($process->getOutput(), "- \n\r");
    }

    private function getCommand(string $algorithm, string $filePattern): string
    {
        foreach ($this->commands as $prefix => $command) {
            if (stripos($algorithm, $prefix) === 0) {
                return sprintf(
                    $command,
                    substr($algorithm, strlen($prefix)),
                    $filePattern
                );
            }
        }
        throw new \RuntimeException(
            sprintf('Unknown algorithm "%s', $algorithm),
            1522923934
        );
    }
}

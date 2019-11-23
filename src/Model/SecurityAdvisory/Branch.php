<?php
declare(strict_types = 1);
namespace TYPO3\Darth\Model\SecurityAdvisory;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use TYPO3\Darth\Model\Version;

class Branch
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var \DateTimeInterface
     */
    private $time;

    /**
     * @var Version
     */
    private $version;

    /**
     * @var string[]
     */
    private $versions;

    /**
     * @param \DateTimeInterface $time
     * @param Version $version
     */
    public function __construct(
        \DateTimeInterface $time,
        Version $version
    ) {
        $this->time = $time;
        $this->name = sprintf(
            '%d.x',
            $version->getAsMajor()
        );
        $this->version = $version;
        $this->versions = [
            sprintf('>=%s.0.0', $version->getAsMajor()),
            sprintf('<%s', $version),
        ];
    }

    /**
     * @param array $additional
     * @param \Closure[] $callbacks
     * @return array
     */
    public function export(array $additional = [], array $callbacks = []): array
    {
        $branch = $this;
        if (isset($callbacks[Branch::class])) {
            $branch = $callbacks[Branch::class]($branch);
        }
        if (empty($branch)) {
            return [];
        }

        return array_merge(
            [
                'time' => $branch->time->format('Y-m-d H:i:s'),
                'versions' => $branch->versions,
            ],
            $additional[Branch::class] ?? []
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getTime(): \DateTimeInterface
    {
        return $this->time;
    }

    /**
     * @return mixed
     */
    public function getVersion(): Version
    {
        return $this->version;
    }

    /**
     * @return string[]
     */
    public function getVersions(): array
    {
        return $this->versions;
    }
}

<?php

declare(strict_types=1);

namespace TYPO3\Darth\Model\AnnounceApi;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Release implements \JsonSerializable
{
    public function __construct(
        private string $version,
        private string $type,
        private \DateTimeInterface $date,
        private HashCollection $tarPackage,
        private HashCollection $zipPackage,
        private ?ReleaseNotes $releaseNotes = null,
        private bool $elts = false
    ) {

    }

    public function jsonSerialize(): array
    {
        $result = [
            'version' => $this->version,
            'type' => $this->type,
            'date' => $this->getUtcDate()->format('Y-m-d\TH:i:sP'),
            'tar_package' => $this->tarPackage,
            'zip_package' => $this->zipPackage,
            'elts' => $this->elts,
        ];
        if ($this->releaseNotes !== null) {
            $result['release_notes'] = $this->releaseNotes;
        }
        return $result;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function getUtcDate(): \DateTimeInterface
    {
        /** @var \DateTime|\DateTimeImmutable $date */
        $date = clone $this->date;
        if ($date->getOffset() !== 0) {
            $date = $date->setTimezone(
                new \DateTimeZone('UTC')
            );
        }
        return $date;
    }

    public function getTarPackage(): HashCollection
    {
        return $this->tarPackage;
    }

    public function getZipPackage(): HashCollection
    {
        return $this->zipPackage;
    }

    public function getReleaseNotes(): ReleaseNotes
    {
        return $this->releaseNotes;
    }

    public function isElts(): bool
    {
        return $this->elts;
    }
}

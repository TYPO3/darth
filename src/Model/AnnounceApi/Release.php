<?php
declare(strict_types = 1);
namespace TYPO3\Darth\Model\AnnounceApi;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Release implements \JsonSerializable
{
    /**
     * @var string
     */
    private $version;

    /**
     * @var string
     */
    private $type;

    /**
     * @var \DateTimeInterface
     */
    private $date;

    /**
     * @var HashCollection
     */
    private $tarPackage;

    /**
     * @var HashCollection
     */
    private $zipPackage;

    /**
     * @var ReleaseNotes
     */
    private $releaseNotes;

    /**
     * @var bool
     */
    private $elts;

    public function __construct(
        string $version,
        string $type,
        \DateTimeInterface $date,
        HashCollection $tarPackage,
        HashCollection $zipPackage,
        ReleaseNotes $releaseNotes = null,
        bool $elts = false
    ) {
        $this->version = $version;
        $this->type = $type;
        $this->date = $date;
        $this->tarPackage = $tarPackage;
        $this->zipPackage = $zipPackage;
        $this->releaseNotes = $releaseNotes;
        $this->elts = $elts;
    }

    /**
     * @return array
     */
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

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    /**
     * @return \DateTimeInterface
     */
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

    /**
     * @return HashCollection
     */
    public function getTarPackage(): HashCollection
    {
        return $this->tarPackage;
    }

    /**
     * @return HashCollection
     */
    public function getZipPackage(): HashCollection
    {
        return $this->zipPackage;
    }

    /**
     * @return ReleaseNotes
     */
    public function getReleaseNotes(): ReleaseNotes
    {
        return $this->releaseNotes;
    }

    /**
     * @return bool
     */
    public function isElts(): bool
    {
        return $this->elts;
    }
}

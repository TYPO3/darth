<?php
declare(strict_types = 1);
namespace TYPO3\Darth\Model;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Version implements \JsonSerializable
{
    const MAJOR = 'major';
    const MINOR = 'minor';
    const PATCH = 'patch';
    const PATTERN = '#^v?(?P<major>\d+)\.(?P<minor>\d+)(?:\.(?P<patch>\d+))$#';

    /**
     * @var int
     */
    private $major;

    /**
     * @var int
     */
    private $minor;

    /**
     * @var int
     */
    private $patch;

    /**
     * @param string $version
     */
    public function __construct(string $version)
    {
        if (!preg_match(static::PATTERN, $version, $matches)) {
            throw new \RuntimeException('Invalid version number', 1523540615);
        }

        $this->major = (int)$matches['major'];
        $this->minor = (int)$matches['minor'];
        $this->patch = (int)$matches['patch'] ?? 0;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->export($this->major, $this->minor, $this->patch);
    }

    /**
     * @return mixed|string
     */
    public function jsonSerialize()
    {
        return (string)$this;
    }

    /**
     * @return string
     */
    public function getAsMajor(): string
    {
        return $this->export($this->major);
    }

    /**
     * @return string
     */
    public function getAsMinor(): string
    {
        return $this->export($this->major, $this->minor);
    }

    /**
     * @param string $position
     * @return Version
     */
    public function increment(string $position = self::PATCH): Version
    {
        $this->assertPosition($position);
        $major = $this->major;
        $minor = $this->minor;
        $patch = $this->patch;
        ${$position}++;

        return new static(
            $this->export($major, $minor, $patch)
        );
    }

    /**
     * @param string $position
     * @return Version
     */
    public function decrement(string $position = self::PATCH): Version
    {
        $this->assertPosition($position);
        if ($this->{$position} === 0) {
            throw new \RuntimeException('Cannot decrement', 1523540616);
        }

        $major = $this->major;
        $minor = $this->minor;
        $patch = $this->patch;
        ${$position}--;

        return new static(
            $this->export($major, $minor, $patch)
        );
    }

    /**
     * @param string $position
     */
    private function assertPosition(string $position)
    {
        if (!in_array($position, [static::MAJOR, static::MINOR, static::PATCH])) {
            throw new \RuntimeException('Invalid position', 1523540617);
        }
    }

    /**
     * @param int ...$parts
     * @return string
     */
    private function export(int ...$parts): string
    {
        return implode('.', $parts);
    }
}

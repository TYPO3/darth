<?php
declare(strict_types = 1);
namespace TYPO3\Darth\Model\AnnounceApi;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class HashCollection extends \ArrayObject implements \JsonSerializable
{
    const HASH_DEFINITIONS = [
        'md5sum' => 32,
        'sha1sum' => 40,
        'sha256sum' => 64,
    ];

    /**
     * @param array $hashes
     */
    public function __construct(array $hashes)
    {
        $hashes = array_intersect_key(
            $hashes,
            static::HASH_DEFINITIONS
        );
        if (empty($hashes)) {
            throw new \RuntimeException(
                'No hashes provided',
                1522919560
            );
        }
        foreach ($hashes as $key => $value) {
            $currentLength = strlen($value);
            $expectedLength = static::HASH_DEFINITIONS[$key];
            if ($currentLength !== $expectedLength) {
                throw new \RuntimeException(
                    sprintf(
                        'Hash "%s" expects length of %d, got %d',
                        $key,
                        $expectedLength,
                        $currentLength
                    ),
                    1522919561
                );
            }
        }
        parent::__construct($hashes);
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->getArrayCopy();
    }
}

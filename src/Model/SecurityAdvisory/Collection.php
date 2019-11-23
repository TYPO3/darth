<?php
declare(strict_types = 1);
namespace TYPO3\Darth\Model\SecurityAdvisory;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Collection
{
    /**
     * @var Advisory[]
     */
    private $advisories = [];

    /**
     * @return Advisory[]
     */
    public function getAdvisories(): array
    {
        return $this->advisories;
    }

    /**
     * @param Advisory $advisory
     */
    public function addAdvisory(Advisory $advisory)
    {
        if ($this->has($advisory->getAdvisoryId())) {
            throw new \LogicException(
                sprintf(
                    'Advisory %s already defined',
                    $advisory->getAdvisoryId()
                ),
                1547828347
            );
        }
        $this->advisories[$advisory->getAdvisoryId()] = $advisory;
    }

    /**
     * @param string $advisoryId
     * @return bool
     */
    public function has(string $advisoryId): bool
    {
        return isset($this->advisories[$advisoryId]);
    }
    /**
     * @param string $advisoryId
     * @return Advisory
     */
    public function get(string $advisoryId): Advisory
    {
        return $this->advisories[$advisoryId];
    }
}

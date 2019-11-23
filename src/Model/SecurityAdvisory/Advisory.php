<?php
declare(strict_types = 1);
namespace TYPO3\Darth\Model\SecurityAdvisory;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Advisory
{
    /**
     * @var string
     */
    private $advisoryId;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $link;

    /**
     * @var Branch[]
     */
    private $branches = [];

    /**
     * @param string $advisory
     * @param string $title
     * @param string $link
     */
    public function __construct(
        string $advisory,
        string $title,
        string $link
    ) {
        $this->advisoryId = $advisory;
        $this->title = $title;
        $this->link = $link;
    }

    /**
     * @param array $additional
     * @param \Closure[] $callbacks
     * @return array
     */
    public function export(array $additional = [], array $callbacks = []): array
    {
        return array_merge(
            [
                'title' => $this->title,
                'link' => $this->link,
                'branches' => array_filter(
                    array_map(
                        function (Branch $branch) use ($additional, $callbacks) {
                            return $branch->export($additional, $callbacks);
                        },
                        $this->branches
                    )
                ),
            ],
            $additional[Advisory::class] ?? []
        );
    }

    /**
     * @return \DateTimeInterface
     */
    public function getFirstDate(): \DateTimeInterface
    {
        return array_map(
            function (Branch $branch) {
                return $branch->getTime();
            },
            array_values($this->branches)
        )[0];
    }

    /**
     * @param Branch $branch
     */
    public function addBranch(Branch $branch)
    {
        if (isset($this->branches[$branch->getName()])) {
            throw new \LogicException(
                sprintf(
                    'Branch %s already defined',
                    $branch->getName()
                ),
                1547828346
            );
        }
        $this->branches[$branch->getName()] = $branch;
    }

    /**
     * @return string
     */
    public function getAdvisoryId(): string
    {
        return $this->advisoryId;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getLink(): string
    {
        return $this->link;
    }

    /**
     * @return Branch[]
     */
    public function getBranches(): array
    {
        return $this->branches;
    }
}

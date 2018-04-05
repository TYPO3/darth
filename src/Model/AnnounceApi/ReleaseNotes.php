<?php
declare(strict_types=1);
namespace TYPO3\Darth\Model\AnnounceApi;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class ReleaseNotes implements \JsonSerializable
{
    private $newsLink;
    private $news;
    private $updateInstructions;
    private $changes;

    public function __construct(
        string $newsLink,
        string $news,
        string $updateInstructions,
        string $changes
    )
    {
        $this->newsLink = $newsLink;
        $this->news = $news;
        $this->updateInstructions = $updateInstructions;
        $this->changes = $changes;
    }

    public function jsonSerialize(): array
    {
        return [
            'news_link' => $this->newsLink,
            'news' => $this->news,
            'update_instructions' => $this->updateInstructions,
            'changes' => $this->changes,
        ];
    }

    /**
     * @return string
     */
    public function getNewsLink(): string
    {
        return $this->newsLink;
    }

    /**
     * @return string
     */
    public function getNews(): string
    {
        return $this->news;
    }

    /**
     * @return string
     */
    public function getUpdateInstructions(): string
    {
        return $this->updateInstructions;
    }

    /**
     * @return string
     */
    public function getChanges(): string
    {
        return $this->changes;
    }
}

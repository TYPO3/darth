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
    public function __construct(
        private string $newsLink,
        private string $news,
        private string $upgradingInstructions,
        private string $changes
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'news_link' => $this->newsLink,
            'news' => $this->news,
            'upgrading_instructions' => $this->upgradingInstructions,
            'changes' => $this->changes,
        ];
    }

    public function getNewsLink(): string
    {
        return $this->newsLink;
    }

    public function getNews(): string
    {
        return $this->news;
    }

    public function getUpgradingInstructions(): string
    {
        return $this->upgradingInstructions;
    }

    public function getChanges(): string
    {
        return $this->changes;
    }
}

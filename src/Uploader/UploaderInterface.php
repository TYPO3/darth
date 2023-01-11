<?php

declare(strict_types=1);
namespace TYPO3\Darth\Uploader;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

interface UploaderInterface
{
    public function getUploadStartMessage(): string;
    public function upload(string $file, string $blobName);
}

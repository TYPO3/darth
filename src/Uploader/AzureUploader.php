<?php

declare(strict_types=1);

namespace TYPO3\Darth\Uploader;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use MicrosoftAzure\Storage\Blob\Internal\IBlob;
use MicrosoftAzure\Storage\Common\ServicesBuilder;

class AzureUploader implements UploaderInterface
{
    /**
     * @var IBlob
     */
    private $client;

    /**
     * @var string
     */
    private $containerName;

    public function __construct()
    {
        $this->client = ServicesBuilder::getInstance()->createBlobService(getenv('AZURE_CONNECTIONSTRING'));
        $this->containerName = getenv('AZURE_CONTAINER');
    }

    public function getUploadStartMessage(): string
    {
        return 'Using container ' . $this->containerName;
    }

    public function upload(string $file, string $blobName)
    {
        $content = fopen($file, (substr($file, -2) === 'md') ? 'r' : 'rb');
        $this->client->createBlockBlob($this->containerName, $blobName, $content);
    }
}

<?php

declare(strict_types = 1);

namespace TYPO3\Darth\Command;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Common\ServicesBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use TYPO3\Darth\Application;

/**
 * Upload files to the cloud.
 */
class PublishCommand extends Command
{
    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this
            ->addArgument(
                'version',
                InputArgument::REQUIRED,
                'The version used in the filename ' . getenv('ARTEFACT_PREFIX') . 'VERSION.tar.gz and the readme'
            )
            ->addOption(
                'type',
                null,
                InputOption::VALUE_OPTIONAL,
                'Set it to "security" if something special is needed',
                'bugfix'
            );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Upload all files to the cloud');

        $version = $input->getArgument('version');
        $releaseType = $input->getOption('type');

        $artefactsDirectory = $this->getApplication()->getArtefactsDirectory($version);

        $blobClient = ServicesBuilder::getInstance()->createBlobService(getenv('AZURE_CONNECTIONSTRING'));

        $containerName = getenv('AZURE_CONTAINER');
        $blobPrefix = ($releaseType === 'snapshot' ? 'snapshot-' . $version : ltrim($version, 'v'));
        $this->io->note('Using container ' . $containerName);

        $hasErrors = false;
        $finder = new Finder();
        $finder->in($artefactsDirectory)->files()->depth(0);
        foreach ($finder as $file) {
            $content = fopen((string)$file, (substr((string)$file, -2) === 'md') ? 'r' : 'rb');
            $blobName = $blobPrefix . '/' . basename((string)$file);
            $this->io->note('Uploading to ' . $blobName);
            try {
                $blobClient->createBlockBlob($containerName, $blobName, $content);
                $this->io->success('Uploaded ' . basename((string)$file) . ' on ' . date('Y-m-d H:i:s'));
            } catch (ServiceException $e) {
                // Error codes and messages are here:
                // http://msdn.microsoft.com/library/azure/dd179439.aspx
                $this->io->error('Error while uploading ' . $blobName . ' from ' . $file . ': ' . $e->getMessage());
                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            $this->io->error('Some files could not be uploaded, you should probably upload them manually from ' . $artefactsDirectory);
        } else {
            $this->io->success('All done. Enjoy the rest of your day!');
        }
    }

    /**
     * Stub for allowing proper IDE support.
     *
     * @return \Symfony\Component\Console\Application|Application
     */
    public function getApplication()
    {
        return parent::getApplication();
    }
}

<?php

declare(strict_types = 1);

namespace TYPO3\Darth\Command;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use GitWrapper\GitWorkingCopy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use TYPO3\Darth\Application;
use TYPO3\Darth\GitHelper;
use TYPO3\Darth\Service\FileHashService;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Command to take care of zipping and signing the source code.
 */
class PackageCommand extends Command
{
    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var GitHelper
     */
    private $gitHelper;

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
            ->addArgument(
                'revision',
                InputArgument::OPTIONAL,
                'The git tag to use, could also be a sha1'
            )
            ->addOption(
                'type',
                null,
                InputOption::VALUE_REQUIRED,
                'set it to "security" if something special is needed'
            )
            ->setDescription('Calls git-archive on the working directory, does a composer install, cleans up development files, and then creates signed artefacts.');
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('You\'re almost there! Lets build some packages for the non-composer folks.');
        $this->gitHelper = new GitHelper($this->getApplication()->getWorkingDirectory(), $this->io->isVerbose());

        $version = $input->getArgument('version');
        $revision = $input->getArgument('revision') ?: $version;
        $releaseType = $input->getOption('type');
        if (!$releaseType) {
            $versionParts = explode('.', $version);
            if (count($versionParts) < 3) {
                $releaseType = 'snapshot';
            } elseif ((int)$versionParts[2] > 0) {
                $releaseType = 'bugfix and maintenance';
            } else {
                $releaseType = 'regular sprint';
            }
        }

        $this->io->section('Step 1: Getting the source files from git and dependencies from composer.');
        $git = $this->gitHelper->initializeCleanWorkingCopy($revision);
        $this->io->note('Checked out ' . $revision);

        $sourceCodeDirectory = $this->initializeCleanSourceCodeDirectory($version);
        $artefactsDirectory = $this->getApplication()->getArtefactsDirectory($version);

        $this->io->section('Step 2: Preparing the package files');
        $this->prepare($git, $revision, $sourceCodeDirectory);

        // Generate the ChangeLog
        // Returns the last tag of this branch before the current revision
        $this->io->note('Creating the ChangeLog based on the git history');
        $previousTag = $this->gitHelper->getPreviousTagName();
        $changeLog = $this->gitHelper->getChangeLogUntilPreviousTag();
        $this->io->note(['The following changes have been made since TYPO3 ' . $previousTag, implode("\n", $changeLog)]);

        // create tar.gz and zip
        $this->io->section('Step 3: Create artefacts and sign them');
        $checksums = $this->createAndSignArtefacts($sourceCodeDirectory, $version);

        // Create the README.md file
        $this->io->note('Create the README.md file');
        $readmeTemplate = $this->getApplication()->getConfigurationFileName('README.md');

        $view = new TemplateView();
        $view->getTemplatePaths()->setTemplatePathAndFilename($readmeTemplate);
        $view->assignMultiple(
            [
                'version' => $version,
                'checksums' => $checksums,
                'releaseDate' => date('d.m.Y'),
                'revision' => $revision,
                'changeLog' => $changeLog,
                'previousVersion' => $previousTag,
                'releaseType' => $releaseType,
            ]
        );
        file_put_contents($artefactsDirectory . '/README_unsigned.md', $view->render());

        // Sign the README.md file
        $signingKey = $this->gitHelper->getSigningKey();
        (new Process(
            'gpg --yes --digest-algo SHA256 -u ' . $signingKey . ' --clearsign --output README.md README_unsigned.md',
            $artefactsDirectory
        ))->run();
        unlink($artefactsDirectory . '/README_unsigned.md');

        // Legacy code
        // Show MD5 hashes and SHA1 hashes
        (new Process('md5 -r *.gz *.zip', $artefactsDirectory))->run();
        (new Process('shasum  *.gz *.zip', $artefactsDirectory))->run();

        $this->io->success('All done. Just upload it now with the "publish" process.');
    }

    /**
     * Re-creates the working directory where the files within git-archive are extracted.
     *
     * @param string $version the version number that is used as parent folder name
     *
     * @return string the name of the directory
     */
    protected function initializeCleanSourceCodeDirectory(string $version): string
    {
        $directory = $this->getApplication()->initializePublishDirectory() . '/' . $version;
        if (!is_dir($directory)) {
            mkdir($directory);
        }
        $directory .= '/' . getenv('ARTEFACT_PREFIX') . $version;
        $this->getApplication()->resetDirectory($directory);

        return $directory;
    }

    /**
     * Creates a git-archive out of the current revision and then extracts that into typo3_src-X.Y.Z within
     * the publishing directory.
     *
     * Then triggers a composer install command, afterwards removes files which are relevant for development/testing
     *
     * @param GitWorkingCopy $git
     * @param string         $revision
     * @param string         $sourceCodeDirectory
     */
    protected function prepare(GitWorkingCopy $git, string $revision, string $sourceCodeDirectory)
    {
        $archiveFile = dirname($sourceCodeDirectory) . '/gitarchive-' . date('Ymd-His') . '-' . $revision . '.tar';

        // Note: git-archive also excludes files mentioned in .gitattributes
        $git->archive('--format', 'tar', '-o', $archiveFile, $revision);

        // Extract and remove the GIT archive
        (new Process(getenv('TAR_COMMAND') . ' xf ' . $archiveFile . ' && rm ' . $archiveFile, $sourceCodeDirectory))->run();

        // Run "composer install" - you have to do "COMPOSER_ROOT_VERSION=8.7.5" because we have a git archive, baby!
        $this->runComposerCommand($sourceCodeDirectory);

        // Remove the leftover files, and sets permissions
        $this->removeFilesExcludedForPackaging($sourceCodeDirectory);
        $this->setPermissionsForFilesForPackaging($sourceCodeDirectory);
    }

    /**
     * Runs composer install (set via environment variable).
     *
     * @param string $directory
     */
    protected function runComposerCommand(string $directory)
    {
        $composerCommand = getenv('COMPOSER_INSTALL_COMMAND');
        $this->io->note('Now running ' . $composerCommand);
        (new Process($composerCommand, $directory))->run();
    }

    /**
     * Checks for all file patterns within the configuration file and removes the files.
     *
     * @param string $directory
     */
    protected function removeFilesExcludedForPackaging(string $directory)
    {
        $this->io->note('Removing test, development and example files not handled properly by git/composer');
        $excludeFilesFromPackaging = $this->getApplication()->getConfiguration('excludeFromPackaging');
        foreach ($excludeFilesFromPackaging as $prefix => $filePatterns) {
            foreach ($filePatterns as $filePattern) {
                $finder = new Finder();
                $finder->ignoreVCS(false)
                    ->ignoreDotFiles(false);

                if ($prefix === '.') {
                    $finder->in($directory)->depth(0);
                } else {
                    $finder->in($directory . '/' . $prefix);
                }

                $finder->name($filePattern);

                // see https://github.com/symfony/symfony/issues/9319 why we use iterator_to_array
                foreach (iterator_to_array($finder, true) as $foundFile) {
                    if (is_dir((string)$foundFile)) {
                        $this->io->writeln('Removing folder ' . $foundFile);
                        (new Process('rm -r ' . $foundFile))->run();
                    } else {
                        $this->io->writeln('Removing file ' . $foundFile);
                        unlink((string)$foundFile);
                    }
                }
            }
        }
    }

    /**
     * Sets all permissions for files within the source code directory.
     *
     * @param string $directory the full path to the directory containing all sources codes
     */
    protected function setPermissionsForFilesForPackaging(string $directory)
    {
        // @todo: unsetting the permissions
        // find ${project-permissions.directory} -type f | xargs chmod a-x
        $this->io->note('Setting permission of executable files');
        $executableFiles = $this->getApplication()->getConfiguration('executableFilesInPackage');
        foreach ($executableFiles as $filePattern) {
            $finder = new Finder();
            $finder->ignoreVCS(false)
                ->ignoreDotFiles(false);

            if (strpos($filePattern, '/') !== false && is_dir($directory . '/' . dirname($filePattern))) {
                $finder->in($directory . '/' . dirname($filePattern));
                $finder->name(basename($filePattern));
            } else {
                $finder->in($directory);
                $finder->name($filePattern);
            }
            foreach ($finder as $foundFile) {
                $this->io->writeln('Set file ' . $foundFile . ' to be executable');
                chmod((string)$foundFile, 0755);
            }
        }

        // Change ownership to root
        (new Process('sudo chown -R 0:0 .', $directory))->run();
    }

    /**
     * Creates .tar.gz and .zip files and creates .sig files by signing them.
     *
     * @param string $sourceCodeDirectory
     * @param string $version
     *
     * @return array
     */
    protected function createAndSignArtefacts(string $sourceCodeDirectory, $version): array
    {
        $artefactDirectory = $this->getApplication()->getArtefactsDirectory($version);
        $artefactBaseName = basename($sourceCodeDirectory);
        $artefacts = [
            'tar.gz' => $artefactBaseName . '.tar.gz',
            'zip' => $artefactBaseName . '.zip',
        ];

        (new Process(getenv('TAR_COMMAND') . ' -czf ' . $artefactDirectory . '/' . $artefacts['tar.gz'] . ' ' . $artefactBaseName . '/', dirname($sourceCodeDirectory)))->run();
        (new Process('zip -rq9 ' . $artefactDirectory . '/' . $artefacts['zip'] . ' ' . $artefactBaseName . '/', dirname($sourceCodeDirectory)))->run();

        // create checksums
        $this->io->note('Generating SHA256 checksums for artefacts.');
        $fileHashService = new FileHashService(['sha' => getenv('CHECKSUM_SHA_COMMAND')], $artefactDirectory);
        $checksums = [];
        foreach ($artefacts as $fileName) {
            $checksums[$fileName] = $fileHashService->execute('sha256', $fileName);
        }

        // sign everything
        $this->io->note('Creating GPG signatures for artefacts.');
        $signingKey = $this->gitHelper->getSigningKey();
        foreach ($artefacts as $fileName) {
            (new Process(
                'gpg --yes --digest-algo SHA256 -u ' . $signingKey . ' --output ' . $fileName . '.sig --detach-sig ' . $fileName,
                $artefactDirectory
            ))->run();
        }

        return $checksums;
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

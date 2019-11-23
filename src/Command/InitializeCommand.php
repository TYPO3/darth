<?php

declare(strict_types = 1);

namespace TYPO3\Darth\Command;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use GitWrapper\Event\GitOutputStreamListener;
use GitWrapper\GitWrapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use TYPO3\Darth\Application;
use TYPO3\Darth\GitHelper;

/**
 * Removes all working and artefacts folders and re-creates them, also
 * clone the main GIT repository again and does some further checks.
 */
class InitializeCommand extends Command
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
     * {@inheritdoc}
     */
    public function configure()
    {
        $this->setDescription('This task cleans up previous left-overs, prepares a clean Git repository and checks for all tools needed.');
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Housekeeping - Let\'s bring everything in order for release time.');

        // Cleaning up GIT
        $this->io->section('Step 1: Git repository inside the working directory');

        $this->io->note('Removing the existing working directory.');
        $workingDirectory = $this->getApplication()->getWorkingDirectory(true);
        $this->getApplication()->resetDirectory($workingDirectory);

        // Clone the remote git repository
        $this->io->note('Re-creating the git repository via cloning from ' . getenv('GIT_REMOTE_REPOSITORY') . '. This might take a while.');
        $gitWrapper = new GitWrapper();
        $gitWrapper->addOutputListener(new GitOutputStreamListener());
        $gitWrapper->setTimeout(0);
        $git = $gitWrapper->cloneRepository(getenv('GIT_REMOTE_REPOSITORY'), $workingDirectory);

        $this->io->note('Adding the push url and the gerrit commit hook');
        if (getenv('GIT_REMOTE_PUSH_URL')) {
            $git->config('remote.origin.pushurl', getenv('GIT_REMOTE_PUSH_URL'));
        }

        // Download the latest Gerrit commit hook
        if (getenv('GERRIT_COMMIT_HOOK')) {
            (new Process(getenv('GERRIT_COMMIT_HOOK'), $workingDirectory))->run();
        }

        // Check if the signing key is set
        $this->gitHelper = new GitHelper($this->getApplication()->getWorkingDirectory(), $this->io->isVerbose());
        $this->gitHelper->initializeCleanWorkingCopy();
        $this->gitHelper->getSigningKey();

        $this->io->section('Step 2: Clean up the publishing directory');
        $this->getApplication()->initializePublishDirectory(true);

        $this->io->section('Step 3: Check if all tools necessary are available');
        // @todo check for availability of necessary tools: shasum, gpg, composer
        $this->io->warning('Todo: Check if shasum, gpg, composer is in place.');

        $this->io->success('All set. You can now do releases by calling the "release" command');
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

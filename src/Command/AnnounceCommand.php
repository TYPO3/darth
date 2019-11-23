<?php
declare(strict_types = 1);
namespace TYPO3\Darth\Command;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Composer\Semver\Semver;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use TYPO3\Darth\Application;
use TYPO3\Darth\GitHelper;
use TYPO3\Darth\Model\AnnounceApi\HashCollection;
use TYPO3\Darth\Model\AnnounceApi\Release;
use TYPO3\Darth\Model\AnnounceApi\ReleaseNotes;
use TYPO3\Darth\Model\Version;
use TYPO3\Darth\Service\AnnounceApiService;
use TYPO3\Darth\Service\FileHashService;
use TYPO3\Darth\Service\VariableResolveService;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Announces (or updates) are release to get.typo3.org.
 */
class AnnounceCommand extends Command
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
        $this->setDescription('This command announces (or updates) a release to get.typo3.org')
            ->addArgument(
                'version',
                InputArgument::REQUIRED,
                'The version number to be announced'
            )
            ->addArgument(
                'news-link',
                InputArgument::REQUIRED,
                'Link to the news article'
            )
            ->addArgument(
                'revision',
                InputArgument::OPTIONAL,
                'The Git tag to use'
            )
            ->addOption(
                'type',
                null,
                InputOption::VALUE_OPTIONAL,
                'Set it to "security" if something special is needed',
                'regular'
            )
            ->addOption(
                'sprint-release',
                null,
                InputOption::VALUE_NONE,
                'If this option is set, the version is considered as sprint release (e.g. 9.1.0)'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_OPTIONAL,
                'Whether to force overriding existing releases',
                false
            )
            ->addOption(
                'elts',
                null,
                InputOption::VALUE_OPTIONAL,
                'Whether the release is an ELTS release',
                false
            )
            ->addOption(
                'interactive',
                'i',
                InputOption::VALUE_OPTIONAL,
                'If this option is set, the user will be prompted, enabled by default',
                true
            );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Announce release to ' . getenv('ANNOUNCE_API_BASE_URL'));
        $this->gitHelper = new GitHelper(
            $this->getApplication()->getWorkingDirectory(),
            $this->io->isVerbose()
        );

        $version = $input->getArgument('version');
        $revision = $input->getArgument('revision') ?: 'v' . $version;
        $newsLink = $input->getArgument('news-link');
        $releaseType = $input->getOption('type');
        $sprintRelease = $input->hasOption('sprint-release') && $input->getOption('sprint-release') !== false;
        $force = $input->hasOption('force') && $input->getOption('force') !== false;
        $elts = $input->hasOption('elts') && $input->getOption('elts') !== false;
        $interactive = $input->hasOption('interactive') && $input->getOption('interactive') !== false;
        $configuration = $this->getConfiguration();
        $versionObject = new Version($version);

        $announceApiService = new AnnounceApiService(
            new VariableResolveService(),
            $this->getAnnounceApiClient(),
            $configuration
        );

        $existingRelease = $announceApiService->getRelease($version);
        if ($existingRelease !== null && !$force) {
            $this->io->error(
                sprintf(
                    'Release %s has been announced already.',
                    $existingRelease->getVersion()
                )
            );
            $this->io->note('Use --force option to override existing releases');
            return 1;
        }

        $signatureDate = $this->readSignatureDate($version);
        $fileHashService = new FileHashService(
            [
                'sha' => getenv('CHECKSUM_SHA_COMMAND'),
                'md5' => getenv('CHECKSUM_MD5_COMMAND')
            ],
            $this->getApplication()->getArtefactsDirectory($version)
        );

        $hashes = $fileHashService->multiple(
            ['md5sum' => 'md5', 'sha1sum' => 'sha1', 'sha256sum' => 'sha256'],
            ['tar_package' => '*.tar.gz', 'zip_package' => '*.zip']
        );

        $this->gitHelper->initializeCleanWorkingCopy($revision);
        $previousTag = $this->gitHelper->getPreviousTagName();
        $previousVersionObject = new Version($previousTag);
        $changes = $this->gitHelper->getChangeLogUntilPreviousTag($previousTag);

        $releaseNotesPath = $this->getReleaseNotesPath($version);
        $this->createReleaseNotesFile(
            $releaseNotesPath,
            [
                'version' => $versionObject,
                'previousVersion' => $previousVersionObject,
                'newsLink' => $newsLink,
                'releaseType' => $releaseType,
                'sprintRelease' => $sprintRelease,
                'changes' => $changes,
            ]
        );

        if ($interactive) {
            $this->io->note(
                'The current release notes file has been prepared for further '
                . ' adjustments at ' . $releaseNotesPath
            );
            $answer = $this->io->confirm(
                'All modified? Ready to continue?',
                true
            );
            if (!$answer) {
                return 1;
            }
        }

        $release = new Release(
            $version,
            $releaseType,
            $signatureDate,
            new HashCollection($hashes['tar_package']),
            new HashCollection($hashes['zip_package']),
            $this->parseReleaseNotesFile($releaseNotesPath),
            $elts
        );

        if (empty($existingRelease)) {
            $announceApiService->addRelease($release);
        } else {
            $announceApiService->updateRelease($version, $release);
        }

        $this->io->success(
            sprintf('Release %s announce', $release->getVersion())
        );
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

    private function getReleaseNotesPath(string $version): string
    {
        $announceDirectory = $this->getApplication()->getAnnounceDirectory();
        $versionDirectory = $announceDirectory . '/' . $version;
        if (!is_dir($versionDirectory)) {
            mkdir($versionDirectory);
        }
        return $versionDirectory . '/RELEASE_NOTES.md';
    }

    private function createReleaseNotesFile(string $releaseNotesPath, array $variables)
    {
        $template = $this->getApplication()->getConfigurationFileName(
            'RELEASE_NOTES.md'
        );
        $view = new TemplateView();
        $view->getTemplatePaths()->setTemplatePathAndFilename($template);
        $view->assignMultiple($variables);
        file_put_contents($releaseNotesPath, $view->render());
    }

    private function parseReleaseNotesFile(string $releaseNotesPath): ReleaseNotes
    {
        $content = file_get_contents($releaseNotesPath);
        if (!preg_match_all('#(\[/(?P<identifier>[^/\]]+)/\]\s+\<\>)(?P<content>.+)\1#mis', $content, $matches)) {
            throw new \RuntimeException(
                'Did not find any content blocks',
                1522937252
            );
        }

        $data = array_combine(
            $matches['identifier'],
            array_map(
                function (string $content) {
                    return trim($content, "\r\n");
                },
                $matches['content']
            )
        );
        return new ReleaseNotes(
            $data['newsLink'],
            $data['news'],
            $data['upgradingInstructions'],
            $data['changes']
        );
    }

    private function readSignatureDate(string $version)
    {
        $process = new Process(
            'gpg --verify README.md',
            $this->getApplication()->getArtefactsDirectory($version)
        );
        $process->run();

        $output = $process->getOutput();
        if ($output === '') {
            $output = $process->getErrorOutput();
        }
        if (preg_match('#^gpg:.*(\b[a-z]{3}\s[a-z]{3}\s(\s*\d|\d{2})\s\d{2}\:\d{2}\:\d{2}\s\d{4}\s[a-z]+\b).*$#mis', $output, $matches)) {
            return new \DateTime($matches[1]);
        }
        return null;
    }

    private function getAnnounceApiClient(): ClientInterface
    {
        $username = getenv('ANNOUNCE_API_AUTH_USERNAME');
        $password = getenv('ANNOUNCE_API_AUTH_PASSWORD');

        $settings = [
            'base_uri' => getenv('ANNOUNCE_API_BASE_URL')
        ];

        if (!empty($username) && !empty($password)) {
            $settings['auth'] = [$username, $password];
        }

        return new Client($settings);
    }

    private function getConfiguration()
    {
        $configuration = $this->getApplication()->getConfiguration('announce');
        return $configuration;
    }

    /**
     * @param string $version
     * @param array $configuration
     * @param string $path
     * @return array
     * @deprecated Not used anymore
     */
    private function inferenceVersionConstraint(string $version, array $configuration, string $path)
    {
        $subjectReference = &$this->referencePath($configuration, $path);
        $candidate = $this->evaluate(array_keys($subjectReference), $version);

        $subjectReference = array_merge(
            $subjectReference['default'] ?? [],
            $subjectReference[$candidate] ?? []
        );

        unset($subjectReference);
        return $configuration;
    }

    private function &referencePath(array &$subject, string $path, $delimiter = '.')
    {
        $steps = explode($delimiter, $path);
        $result = &$subject;
        foreach ($steps as $step) {
            $result = &$result[$step];
        }
        return $result;
    }

    private function evaluate(array $candidates, string $version)
    {
        $matches = array_filter(
            $candidates,
            function (string $candidate) use ($version) {
                return $candidate !== 'default'
                    && Semver::satisfies($version, $candidate);
            }
        );
        if (count($matches) > 1) {
            throw new \LogicException(
                sprintf(
                    'Found more than one match for "%s": "%s"',
                    $version,
                    implode('", "', $matches)
                ),
                1522877130
            );
        }
        $matches = array_values($matches);
        return $matches[0] ?? null;
    }
}

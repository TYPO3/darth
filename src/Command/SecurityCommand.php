<?php
declare(strict_types = 1);
namespace TYPO3\Darth\Command;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use TYPO3\Darth\Application;
use TYPO3\Darth\GitHelper;
use TYPO3\Darth\Model\SecurityAdvisory\Advisory;
use TYPO3\Darth\Model\SecurityAdvisory\Branch;
use TYPO3\Darth\Model\SecurityAdvisory\Collection;
use TYPO3\Darth\Model\Version;

/**
 * Announces (or updates) are release to get.typo3.org.
 */
class SecurityCommand extends Command
{
    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Collection
     */
    private $collection;

    /**
     * @var GitHelper
     */
    private $gitHelper;

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this->setDescription('This command prepares security related data')
            ->addArgument(
                'versions',
                InputArgument::REQUIRED,
                'The version numbers to use (separated by comma)'
            );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->collection = new Collection();
        $this->io = new SymfonyStyle($input, $output);
        $this->client = new Client();

        $this->gitHelper = new GitHelper(
            $this->getApplication()->getWorkingDirectory(),
            $this->io->isVerbose()
        );

        $versions = array_filter(
            array_map('trim', explode(',', $input->getArgument('versions')))
        );
        sort($versions);
        foreach ($versions as $version) {
            $this->processVersion($version);
        }

        $this->io->section('Creating files');

        foreach (['typo3/cms', 'typo3/cms-core'] as $packageName) {
            $dates = [];
            $path = rtrim($this->getSecurityPath($packageName), '/');

            $branchClosure = null;
            if ($packageName === 'typo3/cms-core') {
                $branchClosure = function (Branch $branch) {
                    if ($branch->getVersion()->getAsMajor() === '7') {
                        return null;
                    }
                    return $branch;
                };
            }

            foreach ($this->collection->getAdvisories() as $advisory) {
                $payload = $advisory->export([
                    Advisory::class => [
                        'reference' => sprintf('composer://%s', $packageName)
                    ],
                ], [
                    Branch::class => $branchClosure,
                ]);
                $date = $advisory->getFirstDate()->format('Y-m-d');
                $dates[$date] = ($dates[$date] ?? 0) + 1;
                $fileName = sprintf('%s/%s-%d.yaml', $path, $date, $dates[$date]);
                file_put_contents(
                    $fileName,
                    Yaml::dump($payload, 3, 4)
                );
                $this->io->writeln('Created ' . $fileName);
            }
        }

        $this->io->success('Done');
    }

    /**
     * @param string $version
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function processVersion(string $version)
    {
        $utc = new \DateTimeZone('UTC');

        $versionObject = new Version($version);

        $tag = 'v' . $version;
        $this->gitHelper->initializeCleanWorkingCopy($tag);
        $previousTag = $this->gitHelper->getPreviousTagName();

        $this->io->section(
            sprintf(
                'Preparing structured security data between %s and %s',
                $previousTag,
                $tag
            )
        );

        $changes = array_reverse(
            $this->gitHelper->getChangeItemsUntilPreviousTag($previousTag, 'Security-Bulletin:')
        );

        $this->io->write(
            sprintf('Processing %d changes... ', count($changes))
        );

        foreach ($changes as $change) {
            if (!preg_match('#Security-Bulletin\:\s*(.+)\s*#', $change['body'], $matches)) {
                continue;
            }
            $advisoryId = $matches[1];
            $date = (new \DateTimeImmutable($change['date']))->setTimezone($utc);
            $url = sprintf(
                getenv('SECURITY_ADVISORY_URL_PATTERN'),
                strtolower($advisoryId)
            );

            if ($this->collection->has($advisoryId)) {
                $advisory = $this->collection->get($advisoryId);
            } else {
                $advisory = new Advisory(
                    $advisoryId,
                    $this->getTitleFromNewsLink($url) ?? '',
                    $url
                );
                $this->collection->addAdvisory($advisory);
            }

            $advisory->addBranch(
                new Branch(
                    $date,
                    $versionObject
                )
            );
        }

        $this->io->writeln('done');
    }

    /**
     * @param string $packageName
     * @return string
     */
    private function getSecurityPath(string $packageName): string
    {
        $securityDirectory = $this->getApplication()->getSecurityDirectory();
        $packageDirectory = $securityDirectory . '/' . $packageName;
        if (!is_dir($packageDirectory)) {
            mkdir($packageDirectory, 0755, true);
        }
        return $packageDirectory;
    }

    /**
     * @param string $url
     * @return string|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getTitleFromNewsLink(string $url)
    {
        $content = (string)$this->client->request('GET', $url)->getBody();
        if (preg_match('#<title>(.+)</title>#', $content, $matches)) {
            return $matches[1];
        }
        return null;
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

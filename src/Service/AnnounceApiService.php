<?php
declare(strict_types=1);
namespace TYPO3\Darth\Service;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use TYPO3\Darth\Model\AnnounceApi\HashCollection;
use TYPO3\Darth\Model\AnnounceApi\Release;
use TYPO3\Darth\Model\AnnounceApi\ReleaseNotes;

class AnnounceApiService
{
    /**
     * @var VariableResolveService
     */
    private $variableResolveService;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var array
     */
    private $configuration;

    public function __construct(
        VariableResolveService $variableResolveService,
        ClientInterface $client,
        array $configuration
    )
    {
        $this->variableResolveService = $variableResolveService;
        $this->client = $client;
        $this->configuration = $configuration;
    }

    public function getRelease(string $version)
    {
        try {
            $variables = ['version' => $version];
            $result = $this->request('getRelease', $variables);
        } catch (ClientException $exception) {
            return null;
        }
        return $this->buildRelease($result);
    }

    public function addRelease(Release $release)
    {
        $this->request(
            'addRelease',
            ['version' => $release->getVersion()],
            ['json' => $release]
        );
        if (!empty($release->getReleaseNotes())) {
            $this->setReleaseNotes(
                $release->getVersion(),
                $release->getReleaseNotes()
            );
        }
    }

    public function updateRelease(string $version, Release $release)
    {
        if ($version !== $release->getVersion()) {
            throw new \LogicException(
                sprintf(
                    'Cannot update release "%s" with date for "%s"',
                    $version,
                    $release->getVersion()
                ),
                1522939959
            );
        }
        $this->request(
            'updateRelease',
            ['version' => $version],
            ['json' => $release]
        );
    }

    public function setReleaseNotes(string $version, ReleaseNotes $releaseNotes)
    {
        $this->request(
            'setReleaseNotes',
            ['version' => $version],
            ['json' => $releaseNotes]
        );
    }

    private function substitute(string $path, array $variables = [])
    {
        $value = $this->variableResolveService->resolveDeep(
            $this->configuration,
            $path
        );

        $substitutions = [];
        if (preg_match_all('#\{([^}]+)\}#', $value, $matches)) {
            foreach ($matches[0] as $index => $search) {
                $replace = $this->variableResolveService->resolveDeep(
                    $variables,
                    $matches[1][$index]
                );
                $substitutions[$search] = $replace;
            }
            $value = str_replace(
                array_keys($substitutions),
                array_values($substitutions),
                $value
            );
        }

        return $value;
    }

    private function request(string $scope, array $variables = [], array $options = [])
    {
        try {
            $response = $this->client->request(
                $this->substitute('endpoints.' . $scope . '.method', $variables),
                $this->substitute('endpoints.' . $scope . '.uri', $variables),
                $options
            );
        } catch (GuzzleException $exception) {
            throw $exception;
        }
        return $this->json($response);
    }

    private function json(ResponseInterface $response)
    {
        return json_decode((string)$response->getBody(), true);
    }

    private function buildRelease(array $json = null)
    {
        if (empty($json)) {
            return null;
        }

        return new Release(
            $json['version'],
            $json['type'],
            new \DateTimeImmutable($json['date']),
            $this->buildHashCollection($json['tar_package']),
            $this->buildHashCollection($json['zip_package']),
            $this->buildReleaseNotes($json['release_notes'] ?? null)
        );
    }

    private function buildHashCollection(array $json)
    {
        return new HashCollection($json);
    }

    private function buildReleaseNotes(array $json = null)
    {
        if (empty($json)) {
            return null;
        }

        return new ReleaseNotes(
            $json['news_link'],
            $json['news'],
            $json['upgrade_instructions'],
            $json['changes']
        );
    }
}

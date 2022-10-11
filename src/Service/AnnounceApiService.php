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

    public function getRelease(string $version): ?Release
    {
        try {
            $variables = ['version' => $version];
            $result = $this->request('getRelease', $variables);
        } catch (ClientException $exception) {
            return null;
        } catch (GuzzleException $exception) {
            throw new \RuntimeException(
                sprintf(
                    "API.getRelease failed due to:\n%s",
                    $exception->getMessage()
                ),
                1522943643
            );
        }
        return $this->buildRelease($result);
    }

    public function addRelease(Release $release): void
    {
        try {
            $this->request(
                'addRelease',
                ['version' => $release->getVersion()],
                ['json' => $release]
            );
        } catch (GuzzleException $exception) {
            throw new \RuntimeException(
                sprintf(
                    "API.addRelease failed due to:\n%s",
                    $exception->getMessage()
                ),
                1522943644
            );
        }
        $this->deleteReleaseCache($release->getVersion());
    }

    public function updateRelease(string $version, Release $release): void
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
        try {
            $this->request(
                'updateRelease',
                ['version' => $version],
                ['json' => $release]
            );
        } catch (GuzzleException $exception) {
            throw new \RuntimeException(
                sprintf(
                    "API.updateRelease failed due to:\n%s",
                    $exception->getMessage()
                ),
                1522943645
            );
        }
        $this->deleteReleaseCache($release->getVersion());
    }

    public function setReleaseNotes(string $version, ReleaseNotes $releaseNotes): void
    {
        try {
            $this->request(
                'setReleaseNotes',
                ['version' => $version],
                ['json' => $releaseNotes]
            );
        } catch (GuzzleException $exception) {
            throw new \RuntimeException(
                sprintf(
                    "API.setReleaseNotes failed due to:\n%s",
                    $exception->getMessage()
                ),
                1522943646
            );
        }
    }

    public function deleteReleaseCache(string $version): void
    {
        try {
            $variables = ['version' => $version];
            $this->request('deleteReleaseCache', $variables);
        } catch (GuzzleException $exception) {
            throw new \RuntimeException(
                sprintf(
                    "API.deleteReleaseCache failed due to:\n%s",
                    $exception->getMessage()
                ),
                1522943647
            );
        }
    }

    private function substitute(string $path, array $variables = []): string
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

    private function request(string $scope, array $variables = [], array $options = []): ?array
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

    private function json(ResponseInterface $response): array
    {
        return json_decode((string)$response->getBody(), true);
    }

    private function buildRelease(array $json = null): ?Release
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

    private function buildHashCollection(array $json): HashCollection
    {
        return new HashCollection($json);
    }

    private function buildReleaseNotes(array $json = null): ?ReleaseNotes
    {
        if (empty($json)) {
            return null;
        }

        return new ReleaseNotes(
            $json['news_link'],
            $json['news'],
            $json['upgrading_instructions'],
            $json['changes']
        );
    }
}

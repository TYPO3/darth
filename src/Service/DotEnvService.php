<?php
declare(strict_types = 1);
namespace TYPO3\Darth\Service;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Dotenv\Dotenv;

class DotEnvService
{
    /**
     * @var string
     */
    private $paths = [];

    /**
     * @var Dotenv
     */
    private $dotEnv;

    public function __construct()
    {
        $this->paths = [
            dirname(dirname(__DIR__)) . '/.env.dist',
            dirname(dirname(__DIR__)) . '/.env',
        ];
        $this->dotEnv = new Dotenv();
    }

    public function load()
    {
        $this->dotEnv->load(...$this->paths);
    }

    public function parse(): array
    {
        $settings = [];
        foreach ($this->paths as $path) {
            $settings = array_merge(
                $settings,
                $this->dotEnv->parse(file_get_contents($path))
            );
        }
        return $settings;
    }
}

<?php
declare(strict_types = 1);
namespace TYPO3\Darth\Command;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\Darth\Application;
use TYPO3\Darth\Service\DotEnvService;

/**
 * Outputs current configuration.
 */
class ConfigurationCommand extends Command
{
    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this->setDescription('This command shows the current configuration to verify according settings.');
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Configuraton - Let\'s see whether everthing is configured.');

        $dotEnvService = new DotEnvService();
        $dotEnvAssignments = $dotEnvService->parse();

        $table = $this->createTable()
            ->setColumnWidths([20, 50])
            ->setHeaders(['key', 'getenv($key)'])
            ->setRows(
                array_map(
                    function (string $key) use ($dotEnvAssignments) {
                        return [
                            $key,
                            $this->stringBreak(getenv($key), 50),
                        ];
                    },
                    array_keys($dotEnvAssignments)
                )
            );

        $table->render();
        $this->io->newLine();
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

    private function createTable(): Table
    {
        $tableStyle = clone Table::getStyleDefinition('symfony-style-guide');
        $tableStyle->setCellHeaderFormat('<info>%s</info>');

        $table = new Table($this->io);
        $table->setStyle($tableStyle);

        return $table;
    }

    private function stringBreak(string $value, int $length, bool $indent = true): string
    {
        $glue = '<' . md5(uniqid()) . '>';
        $width = $length - ($indent ? 2 : 0);
        $lines = explode($glue, wordwrap($value, $width, $glue, true));

        if ($indent) {
            array_walk(
                $lines,
                function (string &$line, int $index) {
                    if ($index > 0) {
                        $line = '<info>\ </info>' . $line;
                    }
                }
            );
        }

        return implode("\n", $lines);
    }
}

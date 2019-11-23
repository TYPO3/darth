<?php

declare(strict_types = 1);

namespace TYPO3\Darth\Tests\Unit;

/*
 * This file is part of the TYPO3 project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
//

use PHPUnit\Framework\TestCase;
use TYPO3\Darth\Application;

/**
 * @covers Application
 */
class ApplicationTest extends TestCase
{
    public function testIfApplicationThrowsExceptionWhenNoWorkingDirectoryEnvVariableIsSet(): void
    {
        putenv('WORKING_DIRECTORY=');
        $subject = new Application();
        $this->expectExceptionCode(1498581320);
        $subject->getWorkingDirectory();
    }

    public function testIfApplicationThrowsExceptionWhenWorkingDirectoryContainsTrailingSlash(): void
    {
        putenv('WORKING_DIRECTORY=blabla/');
        $subject = new Application();
        $this->expectExceptionCode(1498728445);
        $subject->getWorkingDirectory();
    }

    public function testIfApplicationThrowsExceptionWhenWorkingDirectoryDoesNotExist(): void
    {
        putenv('WORKING_DIRECTORY=non-existent-folder');
        $subject = new Application();
        $this->expectExceptionCode(1498581383);
        $subject->getWorkingDirectory();
    }
}

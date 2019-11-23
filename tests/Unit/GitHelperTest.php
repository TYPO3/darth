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
use TYPO3\Darth\GitHelper;

/**
 * @covers GitHelper
 */
class GitHelperTest extends TestCase
{
    public function testIfSpecificVersionIsResolvedCorrectly()
    {
        $subject = $this->getMockBuilder(GitHelper::class)->setMethods(['getVersionTags'])->getMock();
        $subject
            ->expects(self::once())
            ->method('getVersionTags')
            ->willReturn(
                ['8.7.3', '8.7.2']
            );
        self::assertEquals($subject->findNextVersion('8.7.4'), '8.7.4');
    }

    public function testIfSpecificVersionDoesNotExistYet()
    {
        $subject = $this->getMockBuilder(GitHelper::class)->setMethods(['getVersionTags'])->getMock();
        $subject
            ->expects(self::once())
            ->method('getVersionTags')
            ->willReturn(
                ['8.7.3', '8.7.2']
            );
        $this->expectExceptionCode(1498742777);
        $subject->findNextVersion('8.7.3');
    }

    public function testIfMinorVersionIsResolvedCorrectly()
    {
        $subject = $this->getMockBuilder(GitHelper::class)->setMethods(['getVersionTags'])->getMock();
        $subject
            ->expects(self::once())
            ->method('getVersionTags')
            ->willReturn(
                ['8.7.3', '8.7.2']
            );
        self::assertEquals($subject->findNextVersion('8.7'), '8.7.4');
    }
}

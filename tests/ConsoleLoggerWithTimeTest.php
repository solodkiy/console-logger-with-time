<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Solodkiy\ConsoleLoggerWithTime\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Solodkiy\ConsoleLoggerWithTime\ConsoleLoggerWithTime;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console logger test.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class ConsoleLoggerWithTimeTest extends TestCase
{
    private const MOCK_DATE = '2000-01-01 12:12:12';

    /**
     * @var DummyOutput
     */
    protected $output;

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        $this->output = new DummyOutput(OutputInterface::VERBOSITY_VERBOSE);

        return new ConsoleLoggerWithTime($this->output, $this->createVerbosityNormalMap());
    }

    private function createVerbosityNormalMap(): array
    {
        return [
            LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ALERT => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::CRITICAL => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::DEBUG => OutputInterface::VERBOSITY_NORMAL,
        ];
    }

    /**
     * @dataProvider provideOutputMappingParams
     */
    public function testOutputMapping($logLevel, $outputVerbosity, $isOutput, $addVerbosityLevelMap = array())
    {
        $out = new BufferedOutput($outputVerbosity);
        $logger = $this->createLoggerMock($out, $addVerbosityLevelMap);
        $logger->log($logLevel, 'foo bar');
        $logs = $out->fetch();
        $this->assertEquals($isOutput ? "[" . self::MOCK_DATE . "] [$logLevel] foo bar".PHP_EOL : '', $logs);
    }

    private function createLoggerMock(
        OutputInterface $output,
        array $verbosityLevelMap = array(),
        array $formatLevelMap = array()
    ): LoggerInterface {
        $mockBuilder = $this->getMockBuilder(ConsoleLoggerWithTime::class);
        $mockBuilder->setConstructorArgs([$output, $verbosityLevelMap, $formatLevelMap]);
        $mockBuilder->enableOriginalConstructor();
        $mockBuilder->onlyMethods(['currentDate']);
        $mock = $mockBuilder->getMock();

        $mock->method('currentDate')->willReturn(self::MOCK_DATE);
        /** @var $mock LoggerInterface */

        return $mock;
    }

    public function provideOutputMappingParams()
    {
        $quietMap = array(LogLevel::EMERGENCY => OutputInterface::VERBOSITY_QUIET);

        return array(
            array(LogLevel::EMERGENCY, OutputInterface::VERBOSITY_NORMAL, true),
            array(LogLevel::WARNING, OutputInterface::VERBOSITY_NORMAL, true),
            array(LogLevel::INFO, OutputInterface::VERBOSITY_NORMAL, false),
            array(LogLevel::DEBUG, OutputInterface::VERBOSITY_NORMAL, false),
            array(LogLevel::INFO, OutputInterface::VERBOSITY_VERBOSE, false),
            array(LogLevel::INFO, OutputInterface::VERBOSITY_VERY_VERBOSE, true),
            array(LogLevel::DEBUG, OutputInterface::VERBOSITY_VERY_VERBOSE, false),
            array(LogLevel::DEBUG, OutputInterface::VERBOSITY_DEBUG, true),
            array(LogLevel::ALERT, OutputInterface::VERBOSITY_QUIET, false),
            array(LogLevel::EMERGENCY, OutputInterface::VERBOSITY_QUIET, false),
            array(LogLevel::ALERT, OutputInterface::VERBOSITY_QUIET, false, $quietMap),
            array(LogLevel::EMERGENCY, OutputInterface::VERBOSITY_QUIET, true, $quietMap),
        );
    }

    public function testHasErrored()
    {
        $logger = new ConsoleLoggerWithTime(new BufferedOutput());

        $this->assertFalse($logger->hasErrored());

        $logger->warning('foo');
        $this->assertFalse($logger->hasErrored());

        $logger->error('bar');
        $this->assertTrue($logger->hasErrored());
    }

    public function testImplements()
    {
        $output = new DummyOutput(OutputInterface::VERBOSITY_VERBOSE);
        $logger = $this->createLoggerMock($output);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    /**
     * @dataProvider provideLevelsAndMessages
     */
    public function testLogsAtAllLevels($level, $message)
    {
        $output = new DummyOutput(OutputInterface::VERBOSITY_VERBOSE);
        $logger = $this->createLoggerMock($output, $this->createVerbosityNormalMap());
        $logger->{$level}($message, array('user' => 'Bob'));
        $logger->log($level, $message, array('user' => 'Bob'));

        $expected = array(
            '[2000-01-01 12:12:12] ['.$level.'] message of level '.$level.' with context: Bob',
            '[2000-01-01 12:12:12] ['.$level.'] message of level '.$level.' with context: Bob',
        );
        $this->assertEquals($expected, $output->getLogs());
    }

    public function provideLevelsAndMessages()
    {
        return array(
            LogLevel::EMERGENCY => array(LogLevel::EMERGENCY, 'message of level emergency with context: {user}'),
            LogLevel::ALERT => array(LogLevel::ALERT, 'message of level alert with context: {user}'),
            LogLevel::CRITICAL => array(LogLevel::CRITICAL, 'message of level critical with context: {user}'),
            LogLevel::ERROR => array(LogLevel::ERROR, 'message of level error with context: {user}'),
            LogLevel::WARNING => array(LogLevel::WARNING, 'message of level warning with context: {user}'),
            LogLevel::NOTICE => array(LogLevel::NOTICE, 'message of level notice with context: {user}'),
            LogLevel::INFO => array(LogLevel::INFO, 'message of level info with context: {user}'),
            LogLevel::DEBUG => array(LogLevel::DEBUG, 'message of level debug with context: {user}'),
        );
    }

    public function testThrowsOnInvalidLevel()
    {
        $this->expectException(\Psr\Log\InvalidArgumentException::class);
        $output = new DummyOutput(OutputInterface::VERBOSITY_VERBOSE);
        $logger = $this->createLoggerMock($output);
        $logger->log('invalid level', 'Foo');
    }

    public function testContextReplacement()
    {
        $output = new DummyOutput(OutputInterface::VERBOSITY_VERBOSE);
        $logger = $this->createLoggerMock($output, $this->createVerbosityNormalMap());
        $logger->info('{Message {nothing} {user} {foo.bar} a}', array('user' => 'Bob', 'foo.bar' => 'Bar'));

        $expected = array('[2000-01-01 12:12:12] [info] {Message {nothing} Bob Bar a}');
        $this->assertEquals($expected, $output->getLogs());
    }

    public function testObjectCastToString()
    {
        if (method_exists($this, 'createPartialMock')) {
            $dummy = $this->createPartialMock(DummyTest::class, array('__toString'));
        } else {
            $dummy = $this->getMock(DummyTest::class, array('__toString'));
        }
        $dummy->method('__toString')->will($this->returnValue('DUMMY'));

        $output = new DummyOutput(OutputInterface::VERBOSITY_VERBOSE);
        $logger = $this->createLoggerMock($output, $this->createVerbosityNormalMap());
        $logger->warning($dummy);

        $expected = array('[2000-01-01 12:12:12] [warning] DUMMY');
        $this->assertEquals($expected, $output->getLogs());
    }

    public function testContextCanContainAnything()
    {
        $output = new DummyOutput(OutputInterface::VERBOSITY_VERBOSE);
        $logger = $this->createLoggerMock($output, $this->createVerbosityNormalMap());

        $context = array(
            'bool' => true,
            'null' => null,
            'string' => 'Foo',
            'int' => 0,
            'float' => 0.5,
            'nested' => array('with object' => new DummyTest()),
            'object' => new \DateTime(),
            'resource' => fopen('php://memory', 'r'),
        );

        $logger->warning('Crazy context data', $context);

        $expected = array('[2000-01-01 12:12:12] [warning] Crazy context data');
        $this->assertEquals($expected, $output->getLogs());
    }

    public function testContextExceptionKeyCanBeExceptionOrOtherValues()
    {
        $output = new DummyOutput(OutputInterface::VERBOSITY_VERBOSE);
        $logger = $this->createLoggerMock($output, $this->createVerbosityNormalMap());
        $logger->warning('Random message', array('exception' => 'oops'));
        $logger->critical('Uncaught Exception!', array('exception' => new \LogicException('Fail')));

        $expected = array(
            '[2000-01-01 12:12:12] [warning] Random message',
            '[2000-01-01 12:12:12] [critical] Uncaught Exception!',
        );
        $this->assertEquals($expected, $output->getLogs());
    }
}

class DummyTest
{
    public function __toString()
    {
    }
}

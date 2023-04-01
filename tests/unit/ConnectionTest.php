<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector\GoneAwayDetector;
use Prophecy\Argument;

class ConnectionTest extends BaseUnitTestCase
{
    /**
     * @dataProvider invalidAttemptsDataProvider
     *
     * @param mixed $invalidValue
     */
    public function testDriverOptionsValidation($invalidValue): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Connection(
            [
                'driverOptions' => [
                    'x_reconnect_attempts' => $invalidValue,
                ],
            ],
            $this->prophesize(Driver::class)->reveal(),
            $this->prophesize(Configuration::class)->reveal(),
            $this->prophesize(EventManager::class)->reveal()
        );
    }

    public function testConstructor(): void
    {
        $params = [
            'driverOptions' => [
                'x_reconnect_attempts' => 999,
            ],
            'platform' => $this->prophesize(AbstractPlatform::class)->reveal(),
        ];

        $connection = new Connection(
            $params,
            $this->prophesize(Driver::class)->reveal(),
            $this->mockConfiguration(),
            $this->prophesize(EventManager::class)->reveal()
        );

        static::assertInstanceOf(Connection::class, $connection);
    }

    public function testPrepareShouldThrowWhenItsNotRetriable(): void
    {
        $driver = $this->prophesize(Driver::class);
        $driver->connect(Argument::cetera())
            ->willThrow(new \LogicException('This cannot be retried'));

        $connection = new Connection(
            [],
            $driver->reveal(),
            $this->mockConfiguration(),
            $this->prophesize(EventManager::class)->reveal()
        );

        $goneAwayDetector = $this->prophesize(GoneAwayDetector::class);
        $goneAwayDetector->isGoneAwayException(Argument::cetera())
            ->willReturn(false);

        $connection->setGoneAwayDetector($goneAwayDetector->reveal());

        $this->expectException(\LogicException::class);

        $connection->prepare('THIS SHOULD FAIL');
    }
}

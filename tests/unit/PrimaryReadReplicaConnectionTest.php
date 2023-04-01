<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector\GoneAwayDetector;
use Prophecy\Argument;

class PrimaryReadReplicaConnectionTest extends BaseUnitTestCase
{
    /**
     * @dataProvider invalidAttemptsDataProvider
     *
     * @param mixed $invalidValue
     */
    public function testDriverOptionsValidation($invalidValue): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PrimaryReadReplicaConnection(
            [
                'primary' => [
                    'driverOptions' => [
                        'x_reconnect_attempts' => $invalidValue,
                    ],
                ],
            ],
            $this->prophesize(Driver::class)->reveal(),
            $this->prophesize(Configuration::class)->reveal(),
            $this->prophesize(EventManager::class)->reveal()
        );
    }

    public function testPrimaryReceivesAttemptOption(): void
    {
        $driver = $this->prophesize(Driver::class);
        $goneAwayDetector = $this->prophesize(GoneAwayDetector::class);
        $connection = $this->createConnection($driver->reveal());

        $connection->setGoneAwayDetector($goneAwayDetector->reveal());
        $driver->connect(Argument::cetera())
            ->willThrow(new \LogicException('This failure should be retried'));
        $goneAwayDetector->isGoneAwayException(Argument::cetera())
            ->shouldBeCalledOnce()
            ->willReturn(true);

        $this->expectException(\LogicException::class);

        $connection->prepare('SELECT 1');
    }

    private function createConnection(Driver $driver): PrimaryReadReplicaConnection
    {
        $replicaConfig = [
            'platform' => $this->prophesize(AbstractPlatform::class)->reveal(),
        ];
        $primaryConfig = $replicaConfig;
        $primaryConfig['driverOptions'] = [
            'x_reconnect_attempts' => 1,
        ];

        return new PrimaryReadReplicaConnection(
            [
                'primary' => $primaryConfig,
                'replica' => [
                    $replicaConfig,
                ],
            ],
            $driver,
            $this->mockConfiguration(),
            $this->prophesize(EventManager::class)->reveal()
        );
    }
}
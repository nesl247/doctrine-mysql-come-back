<?php

namespace Facile\DoctrineMySQLComeBack\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\Connection;
use Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\PrimaryReadReplicaConnection;

class PrimaryReadReplicaConnectionTest extends ConnectionTraitTest
{
    /**
     * @param string $driver
     * @param int $attempts
     * @param bool $enableSavepoints
     * @param int $delay
     */
    protected function createConnection(string $driver, int $attempts, bool $enableSavepoints, int $delay): PrimaryReadReplicaConnection
    {
        $connection = DriverManager::getConnection(['primary' => $this->getConnectionParams(), 'replica' => [$this->getConnectionParams()], 'driverOptions' => [
            'x_reconnect_attempts' => $attempts,
        ], 'wrapperClass' => PrimaryReadReplicaConnection::class, 'driverClass' => $driver]);

        $this->assertInstanceOf(PrimaryReadReplicaConnection::class, $connection);
        $connection->setNestTransactionsWithSavepoints($enableSavepoints);
        $connection->connect();

        return $connection;
    }

    /**
     *
     * @param string $driver
     * @param int $attempts
     * @param bool $enableSavepoints
     * @param int $delay *
     *
* @return Connection|PrimaryReadReplicaConnection
     */
    protected function getConnectedConnection(string $driver, int $attempts, bool $enableSavepoints, int $delay = 0): DBALConnection
    {
        $connection = parent::getConnectedConnection($driver, $attempts, $enableSavepoints);
        $this->assertInstanceOf(PrimaryReadReplicaConnection::class, $connection);
        $connection->ensureConnectedToPrimary();

        return $connection;
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testBeginTransactionShouldNotInterfereWhenSwitchingToPrimary(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->createConnection($driver, 0, $enableSavepoints, 0);
        $this->assertFalse($connection->isConnectedToPrimary());
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $connection->beginTransaction();

        /** @psalm-suppress RedundantConditionGivenDocblockType */
        $this->assertSame(1, $connection->connectCount);
        $this->assertTrue($connection->isConnectedToPrimary());
    }
}

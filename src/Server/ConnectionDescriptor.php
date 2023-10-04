<?php

namespace Waveman\Server;

final class ConnectionDescriptor implements \JsonSerializable
{
    private string $localAddress;
    private string $remoteAddress;
    private int $aliveSeconds;

    /**
     * @param string $localAddress
     * @param string $remoteAddress
     * @param int $aliveSeconds
     */
    public function __construct(string $localAddress, string $remoteAddress, int $aliveSeconds)
    {
        $this->localAddress = $localAddress;
        $this->remoteAddress = $remoteAddress;
        $this->aliveSeconds = $aliveSeconds;
    }

    public function getLocalAddress(): string
    {
        return $this->localAddress;
    }

    public function setLocalAddress(string $localAddress): void
    {
        $this->localAddress = $localAddress;
    }

    public function getRemoteAddress(): string
    {
        return $this->remoteAddress;
    }

    public function setRemoteAddress(string $remoteAddress): void
    {
        $this->remoteAddress = $remoteAddress;
    }

    public function getAliveSeconds(): int
    {
        return $this->aliveSeconds;
    }

    public function setAliveSeconds(int $aliveSeconds): void
    {
        $this->aliveSeconds = $aliveSeconds;
    }

    public static function fromConnectionPool(ConnectionPool $connections): array
    {
        $descriptors = [];
        foreach ($connections as $metadata) {
            $descriptors[] = self::fromConnectionMetadata($metadata);
        }
        return $descriptors;
    }

    public static function fromConnectionMetadata(ConnectionMetadata $metadata): ConnectionDescriptor
    {
        $connection = $metadata->getConnection();
        return new ConnectionDescriptor(
            $connection->getLocalAddress(),
            $connection->getRemoteAddress(),
            $metadata->getAliveSeconds(),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize(): array
    {
        return [
            'localAddress' => $this->localAddress,
            'remoteAddress' => $this->remoteAddress,
            'aliveSeconds' => $this->aliveSeconds
        ];
    }
}
<?php

declare(strict_types=1);

/*
 * This file is part of the waveman/waveman package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Viso\Cluster;

final class ClusterAggregator
{
    /**
     * @var Cluster[]
     */
    private array $clusters;

    public function __construct(array $clusters)
    {
        $this->clusters = $clusters;
    }

    /**
     * Run all clusters.
     *
     * @return void
     */
    public function run(): void
    {
        foreach ($this->clusters as $cluster){
            $cluster->run();
        }
    }

    /**
     * Create one instance.
     *
     * @param ...$clusters
     * @return ClusterAggregator
     */
    public static function aggregate(...$clusters): ClusterAggregator
    {
        return new ClusterAggregator($clusters);
    }
}
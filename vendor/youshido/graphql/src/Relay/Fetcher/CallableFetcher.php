<?php
/**
 * Date: 17.05.16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Relay\Fetcher;


class CallableFetcher implements FetcherInterface
{

    /** @var  callable */
    protected $resolveNodeCallable;

    /** @var  callable */
    protected $resolveTypeCallable;

    public function __construct(callable $resolveNode, callable $resolveType)
    {
        $this->resolveNodeCallable = $resolveNode;
        $this->resolveTypeCallable = $resolveType;
    }

    /**
     * @inheritdoc
     */
    public function resolveNode($type, $id)
    {
        $callable = $this->resolveNodeCallable;

        return $callable($type, $id);
    }

    /**
     * @inheritdoc
     */
    public function resolveType($object)
    {
        $callable = $this->resolveTypeCallable;

        return $callable($object);
    }
}
<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/10/16 11:32 PM
*/

namespace Youshido\GraphQL\Relay;


use Youshido\GraphQL\Relay\Fetcher\FetcherInterface;
use Youshido\GraphQL\Relay\Field\GlobalIdField;
use Youshido\GraphQL\Type\InterfaceType\AbstractInterfaceType;

class NodeInterfaceType extends AbstractInterfaceType
{

    /** @var  FetcherInterface */ //todo: maybe there are better solution
    protected $fetcher;

    public function getName()
    {
        return 'NodeInterface';
    }

    public function build($config)
    {
        $config->addField(new GlobalIdField('NodeInterface'));
    }

    public function resolveType($object)
    {
        if ($this->fetcher) {
            return $this->fetcher->resolveType($object);
        }

        return null;
    }

    /**
     * @return FetcherInterface
     */
    public function getFetcher()
    {
        return $this->fetcher;
    }

    /**
     * @param FetcherInterface $fetcher
     *
     * @return NodeInterfaceType
     */
    public function setFetcher($fetcher)
    {
        $this->fetcher = $fetcher;

        return $this;
    }
}

<?php
/**
 * Date: 17.05.16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Relay\Fetcher;


interface FetcherInterface
{

    /**
     * Resolve node
     *
     * @param $type string
     * @param $id   string
     *
     * @return mixed
     */
    public function resolveNode($type, $id);

    /**
     * @param $object
     * @return mixed
     */
    public function resolveType($object);

}

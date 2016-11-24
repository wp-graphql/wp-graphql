<?php
/*
 * This file is a part of GraphQL project.
 *
 * @author Alexandr Viniychuk <a@viniychuk.com>
 * created: 11:54 AM 5/5/16
 */

namespace Youshido\GraphQL\Type\Union;

final class UnionType extends AbstractUnionType
{

    protected $isFinal = true;

    public function resolveType($object)
    {
        $callable = $this->getConfigValue('resolveType');

        return call_user_func_array($callable, [$object]);
    }

    public function getTypes()
    {
        return $this->getConfig()->get('types', []);
    }

}

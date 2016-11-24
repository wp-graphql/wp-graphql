<?php
/**
 * ContentBlockUnion.php
 */

namespace Examples\Blog\Schema;


use Youshido\GraphQL\Type\Union\AbstractUnionType;

class ContentBlockUnion extends AbstractUnionType
{
    public function getTypes()
    {
        return [new PostType(), new BannerType()];
    }

    public function resolveType($object)
    {
        return empty($object['id']) ? null : (strpos($object['id'], 'post') !== false ? new PostType() : new BannerType());
    }

}

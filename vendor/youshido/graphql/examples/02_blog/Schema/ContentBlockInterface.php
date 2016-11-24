<?php
/**
 * ContentBlockInterface.php
 */

namespace Examples\Blog\Schema;


use Youshido\GraphQL\Type\InterfaceType\AbstractInterfaceType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Scalar\StringType;

class ContentBlockInterface extends AbstractInterfaceType
{

    public function build($config)
    {
        $config->addField('title', new NonNullType(new StringType()));
        $config->addField('summary', new StringType());
    }

    public function resolveType($object)
    {
        return empty($object['id']) ? null : (strpos($object['id'], 'post') !== false ? new PostType() : new BannerType());
    }
}

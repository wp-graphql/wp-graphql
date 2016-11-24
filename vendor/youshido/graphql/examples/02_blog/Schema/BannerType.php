<?php
/**
 * BannerType.php
 */

namespace Examples\Blog\Schema;

use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\StringType;

class BannerType extends AbstractObjectType
{
    public function build($config)
    {
        $config
            ->addField('title', new NonNullType(new StringType()))
            ->addField('summary', new StringType())
            ->addField('imageLink', new StringType());
    }

    public function getInterfaces()
    {
        return [new ContentBlockInterface()];
    }
}

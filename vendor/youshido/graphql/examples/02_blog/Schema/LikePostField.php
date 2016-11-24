<?php
/**
 * LikePost.php
 */

namespace Examples\Blog\Schema;


use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Scalar\IntType;

class LikePostField extends AbstractField
{

    /**
     * @param null        $value
     * @param array       $args
     * @param ResolveInfo $info
     * @return mixed
     */
    public function resolve($value, array $args, ResolveInfo $info)
    {
        return $info->getReturnType()->getOne($args['id']);
    }

    public function getType()
    {
        return new PostType();
    }

    public function build(FieldConfig $config)
    {
        $config->addArgument('id', new NonNullType(new IntType()));
    }


}

<?php
/**
 * BlogSchema.php
 */

namespace Examples\Blog\Schema;

use Youshido\GraphQL\Config\Schema\SchemaConfig;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Schema\AbstractSchema;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Scalar\StringType;

class BlogSchema extends AbstractSchema
{
    public function build(SchemaConfig $config)
    {
        $config->getQuery()->addFields([
            'latestPost'           => [
                'type'    => new PostType(),
                'resolve' => function ($value, array $args, ResolveInfo $info) {
                    return $info->getReturnType()->getOne(empty($args['id']) ? 1 : $args['id']);
                }
            ],
            'randomBanner'         => [
                'type'    => new BannerType(),
                'resolve' => function () {
                    return DataProvider::getBanner(rand(1, 10));
                }
            ],
            'pageContentUnion'     => [
                'type'    => new ListType(new ContentBlockUnion()),
                'resolve' => function () {
                    return [DataProvider::getPost(1), DataProvider::getBanner(1)];
                }
            ],
            'pageContentInterface' => [
                'type'    => new ListType(new ContentBlockInterface()),
                'resolve' => function () {
                    return [DataProvider::getPost(2), DataProvider::getBanner(3)];
                }
            ]
        ]);
        $config->getMutation()->addFields([
            new LikePostField(),
            'createPost' => [
                'type'    => new PostType(),
                'args'    => [
                    'post'   => new PostInputType(),
                    'author' => new StringType()
                ],
                'resolve' => function ($value, array $args, ResolveInfo $info) {
                    // code for creating a new post goes here
                    // we simple use our DataProvider for now
                    $post = DataProvider::getPost(10);
                    if (!empty($args['post']['title'])) $post['title'] = $args['post']['title'];

                    return $post;
                }
            ]
        ]);
    }

}

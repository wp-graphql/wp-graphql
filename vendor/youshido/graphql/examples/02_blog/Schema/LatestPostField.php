<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/20/16 12:07 AM
*/

namespace Examples\Blog\Schema;


use BlogTest\PostType;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;

class LatestPostField extends AbstractField
{
    public function getType()
    {
        return new PostType();
    }

    public function resolve($value, array $args, ResolveInfo $info)
    {
        return [
            "title"   => "New approach in API has been revealed",
            "summary" => "This post describes a new approach to create and maintain APIs",
        ];
    }


}

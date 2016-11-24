<?php
/**
 * Date: 17.05.16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Field;


use Youshido\GraphQL\Execution\ResolveInfo;

interface FieldInterface extends InputFieldInterface
{
    public function resolve($value, array $args, ResolveInfo $info);

    public function getResolveFunction();
}

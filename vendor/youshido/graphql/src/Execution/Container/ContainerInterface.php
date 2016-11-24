<?php
namespace Youshido\GraphQL\Execution\Container;

interface ContainerInterface
{
    public function get($id);

    public function set($id, $value);

    public function remove($id);

    public function has($id);

}
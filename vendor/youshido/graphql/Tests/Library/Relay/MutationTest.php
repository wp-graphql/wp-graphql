<?php
/*
 * This file is a part of GraphQL project.
 *
 * @author Alexandr Viniychuk <a@viniychuk.com>
 * created: 2:11 PM 5/19/16
 */

namespace Youshido\Tests\Library\Relay;


use Youshido\GraphQL\Relay\RelayMutation;
use Youshido\GraphQL\Type\Scalar\IdType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

class MutationTest extends \PHPUnit_Framework_TestCase
{

    public function testCreation()
    {
        $mutation = RelayMutation::buildMutation('ship', [
            'name' => new StringType()
        ],[
            'id' => new IdType(),
            'name' => new StringType()
        ], function($source, $args, $info) {

        });
        $this->assertEquals('ship', $mutation->getName());
    }

    /**
     * @expectedException \Exception
     */
    public function testInvalidType()
    {
        RelayMutation::buildMutation('ship', [
            'name' => new StringType()
        ], new IntType(), function($source, $args, $info) {});

    }

}

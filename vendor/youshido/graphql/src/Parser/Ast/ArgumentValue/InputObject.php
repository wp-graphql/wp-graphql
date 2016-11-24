<?php
/**
 * Date: 01.12.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Parser\Ast\ArgumentValue;


class InputObject implements ValueInterface
{

    protected $object = [];

    /**
     * InputList constructor.
     *
     * @param array $object
     */
    public function __construct(array $object)
    {
        $this->object = $object;
    }

    /**
     * @return array
     */
    public function getValue()
    {
        return $this->object;
    }

    /**
     * @param array $value
     */
    public function setValue($value)
    {
        $this->object = $value;
    }

}
<?php
/**
 * Date: 01.12.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Parser\Ast\ArgumentValue;


class InputList implements ValueInterface
{

    protected $list = [];

    /**
     * InputList constructor.
     *
     * @param array $list
     */
    public function __construct(array $list)
    {
        $this->list = $list;
    }

    /**
     * @return array
     */
    public function getValue()
    {
        return $this->list;
    }

    /**
     * @param array $value
     */
    public function setValue($value)
    {
        $this->list = $value;
    }
}
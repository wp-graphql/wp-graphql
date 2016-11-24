<?php
/**
 * Date: 10/24/16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Parser\Ast\ArgumentValue;


class VariableReference implements ValueInterface
{

    /** @var  string */
    private $name;

    /** @var  Variable */
    private $variable;

    /** @var  mixed */
    private $value;

    /**
     * VariableReference constructor.
     *
     * @param string        $name
     * @param Variable|null $variable
     */
    public function __construct($name, Variable $variable = null)
    {
        $this->name     = $name;
        $this->variable = $variable;
    }

    public function getVariable()
    {
        return $this->variable;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}

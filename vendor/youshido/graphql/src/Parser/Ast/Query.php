<?php
/**
 * Date: 23.11.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Parser\Ast;

use Youshido\GraphQL\Parser\Ast\Interfaces\FieldInterface;

class Query implements FieldInterface
{

    /** @var string */
    protected $name;

    /** @var string */
    protected $alias;

    /** @var Argument[] */
    protected $arguments;

    /** @var Field[]|Query[] */
    protected $fields;

    public function __construct($name, $alias = null, $arguments = [], $fields = [])
    {
        $this->name      = $name;
        $this->alias     = $alias;
        $this->arguments = $arguments;
        $this->fields    = $fields;
    }

    public function getName()
    {
        return $this->name;
    }

    public function hasArguments()
    {
        return (bool)count($this->arguments);
    }

    /**
     * @return Argument[]
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param $arguments Argument[]
     */
    public function setArguments($arguments)
    {
        $this->arguments = $arguments;
    }

    public function addArgument(Argument $argument)
    {
        $this->arguments[$argument->getName()] = $argument;
    }

    public function getKeyValueArguments()
    {
        $arguments = [];

        foreach ($this->getArguments() as $argument) {
            $arguments[$argument->getName()] = $argument->getValue()->getValue();
        }

        return $arguments;
    }

    /**
     * @return Field[]|Query[]|FragmentInterface[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @return bool
     */
    public function hasFields()
    {
        return (bool)count($this->fields);
    }

    /**
     * @param Field[]|Query[] $fields
     */
    public function setFields($fields)
    {
        $this->fields = $fields;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function hasField($name, $deep = false)
    {
        foreach ($this->getFields() as $field) {
            if ($field->getName() == $name) {
                return true;
            }

            if ($deep && $field instanceof Query) {
                if ($field->hasField($name)) {
                    return true;
                }
            }
        }

        return false;
    }

}

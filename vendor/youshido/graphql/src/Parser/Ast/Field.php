<?php
/**
 * Date: 23.11.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Parser\Ast;


use Youshido\GraphQL\Parser\Ast\Interfaces\FieldInterface;

class Field implements FieldInterface
{
    /** @var string */
    private $name;

    /** @var null|string */
    private $alias = null;

    /** @var Argument[] */
    protected $arguments;

    private $argumentsCache = null;

    public function __construct($name, $alias = null, $arguments = [])
    {
        $this->name      = $name;
        $this->alias     = $alias;
        $this->arguments = $arguments;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return null|string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @param null|string $alias
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;
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

    public function addArgument(Argument $argument)
    {
        $this->arguments[$argument->getName()] = $argument;
    }

    public function getKeyValueArguments()
    {
        if ($this->argumentsCache !== null) {
            return $this->argumentsCache;
        }

        $this->argumentsCache = [];

        foreach ($this->getArguments() as $argument) {
            $this->argumentsCache[$argument->getName()] = $argument->getValue()->getValue();
        }

        return $this->argumentsCache;
    }
}
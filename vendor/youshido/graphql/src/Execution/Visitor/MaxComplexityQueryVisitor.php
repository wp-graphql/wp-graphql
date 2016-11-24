<?php
/*
* Concrete implementation of query visitor.
*
* Enforces maximum complexity on a query, computed from "cost" functions on
* the fields touched by that query.
*
* @author Ben Roberts <bjr.roberts@gmail.com>
* created: 7/11/16 11:05 AM
*/

namespace Youshido\GraphQL\Execution\Visitor;


use Youshido\GraphQL\Config\Field\FieldConfig;

class MaxComplexityQueryVisitor extends AbstractQueryVisitor
{

    /**
     * @var int max score allowed before throwing an exception (causing processing to stop)
     */
    public $maxScore;

    /**
     * @var int default score for nodes without explicit cost functions
     */
    protected $defaultScore = 1;

    /**
     * MaxComplexityQueryVisitor constructor.
     *
     * @param int $max max allowed complexity score
     */
    public function __construct($max)
    {
        parent::__construct();

        $this->maxScore = $max;
    }

    /**
     * {@inheritdoc}
     */
    public function visit(array $args, FieldConfig $fieldConfig, $childScore = 0)
    {
        $cost = $fieldConfig->get('cost');
        if (is_callable($cost)) {
            $cost = $cost($args, $fieldConfig, $childScore);
        }

        $cost = $cost ?: $this->defaultScore;
        $this->memo += $cost;

        if ($this->memo > $this->maxScore) {
            throw new \Exception('query exceeded max allowed complexity of ' . $this->maxScore);
        }

        return $cost;
    }
}
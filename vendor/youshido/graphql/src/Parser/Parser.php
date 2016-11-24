<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Portey Vasil <portey@gmail.com>
* created: 11/23/15 1:22 AM
*/

namespace Youshido\GraphQL\Parser;


use Youshido\GraphQL\Parser\Ast\Argument;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\InputList;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\InputObject;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\Literal;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\Variable;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\VariableReference;
use Youshido\GraphQL\Parser\Ast\Field;
use Youshido\GraphQL\Parser\Ast\Fragment;
use Youshido\GraphQL\Parser\Ast\FragmentReference;
use Youshido\GraphQL\Parser\Ast\Mutation;
use Youshido\GraphQL\Parser\Ast\Query;
use Youshido\GraphQL\Parser\Ast\TypedFragmentReference;
use Youshido\GraphQL\Parser\Exception\SyntaxErrorException;
use Youshido\GraphQL\Parser\Exception\VariableTypeNotDefined;

class Parser extends Tokenizer
{

    /** @var array */
    private $data = [];

    public function parse($source = null)
    {
        $this->init($source);

        while (!$this->end()) {
            $tokenType = $this->peek()->getType();

            switch ($tokenType) {
                case Token::TYPE_LBRACE:
                case Token::TYPE_QUERY:
                    foreach ($this->parseBody() as $query) {
                        $this->data['queries'][] = $query;
                    }

                    break;

                case Token::TYPE_MUTATION:
                    foreach ($this->parseBody(Token::TYPE_MUTATION) as $query) {
                        $this->data['mutations'][] = $query;
                    }

                    break;

                case Token::TYPE_FRAGMENT:
                    $this->data['fragments'][] = $this->parseFragment();

                    break;

                default:
                    throw new SyntaxErrorException('Incorrect request syntax');
            }
        }

        return $this->data;
    }

    private function init($source = null)
    {
        $this->initTokenizer($source);

        $this->data = [
            'queries'            => [],
            'mutations'          => [],
            'fragments'          => [],
            'fragmentReferences' => [],
            'variables'          => [],
            'variableReferences' => []
        ];
    }

    protected function parseBody($token = Token::TYPE_QUERY, $highLevel = true)
    {
        $fields = [];
        $first  = true;

        if ($this->peek()->getType() == $token && $highLevel) {
            $this->lex();
            $this->eat(Token::TYPE_IDENTIFIER);

            if ($this->match(Token::TYPE_LPAREN)) {
                $this->parseVariables();
            }
        }

        $this->lex();

        while (!$this->match(Token::TYPE_RBRACE) && !$this->end()) {
            if ($first) {
                $first = false;
            } else {
                $this->eatMulti([Token::TYPE_COMMA]);
            }

            if ($this->match(Token::TYPE_FRAGMENT_REFERENCE)) {
                $this->lex();

                if ($this->eat(Token::TYPE_ON)) {
                    $fields[] = $this->parseBodyItem(Token::TYPE_TYPED_FRAGMENT, $highLevel);
                } else {
                    $fields[] = $this->parseFragmentReference();
                }
            } else {
                $fields[] = $this->parseBodyItem($token, $highLevel);
            }
        }

        $this->expect(Token::TYPE_RBRACE);

        return $fields;
    }

    protected function parseVariables()
    {
        $first = true;
        $this->eat(Token::TYPE_LPAREN);

        while (!$this->match(Token::TYPE_RPAREN) && !$this->end()) {
            if ($first) {
                $first = false;
            } else {
                $this->expect(Token::TYPE_COMMA);
            }

            $this->eat(Token::TYPE_VARIABLE);
            $name = $this->parseIdentifier();
            $this->eat(Token::TYPE_COLON);

            if ($this->match(Token::TYPE_LSQUARE_BRACE)) {
                $isArray = true;

                $this->eat(Token::TYPE_LSQUARE_BRACE);
                $type = $this->parseIdentifier();
                $this->eat(Token::TYPE_RSQUARE_BRACE);
            } else {
                $isArray = false;
                $type    = $this->parseIdentifier();
            }

            $required = false;
            if ($this->match(Token::TYPE_REQUIRED)) {
                $required = true;
                $this->eat(Token::TYPE_REQUIRED);
            }

            $this->data['variables'][] = new Variable($name, $type, $required, $isArray);
        }

        $this->expect(Token::TYPE_RPAREN);
    }

    protected function expectMulti($types)
    {
        if ($this->matchMulti($types)) {
            return $this->lex();
        }

        throw $this->createUnexpectedException($this->peek());
    }

    protected function parseVariableReference()
    {
        $this->expectMulti([Token::TYPE_VARIABLE]);

        if ($this->match(Token::TYPE_NUMBER) || $this->match(Token::TYPE_IDENTIFIER) || $this->match(Token::TYPE_QUERY)) {
            $name = $this->lex()->getData();

            $variable = $this->findVariable($name);
            if ($variable) {
                $variable->setUsed(true);
            }

            $variableReference = new VariableReference($name, $variable);

            $this->data['variableReferences'][] = $variableReference;

            return $variableReference;
        }

        throw $this->createUnexpectedException($this->peek());
    }

    protected function findVariable($name)
    {
        foreach ($this->data['variables'] as $variable) {
            /** @var $variable Variable */
            if ($variable->getName() == $name) {
                return $variable;
            }
        }

        return null;
    }

    protected function parseFragmentReference()
    {
        $name              = $this->parseIdentifier();
        $fragmentReference = new FragmentReference($name);

        $this->data['fragmentReferences'][] = $fragmentReference;

        return $fragmentReference;
    }

    protected function parseIdentifier()
    {
        return $this->expectMulti([
            Token::TYPE_IDENTIFIER,
            Token::TYPE_MUTATION,
            Token::TYPE_QUERY,
            Token::TYPE_FRAGMENT,
        ])->getData();
    }

    protected function parseBodyItem($type = Token::TYPE_QUERY, $highLevel = true)
    {
        $name  = $this->parseIdentifier();
        $alias = null;

        if ($this->eat(Token::TYPE_COLON)) {
            $alias = $name;
            $name  = $this->parseIdentifier();
        }

        $arguments = $this->match(Token::TYPE_LPAREN) ? $this->parseArgumentList() : [];

        if ($this->match(Token::TYPE_LBRACE)) {
            $fields = $this->parseBody($type == Token::TYPE_TYPED_FRAGMENT ? Token::TYPE_QUERY : $type, false);

            if (!$fields) {
                throw $this->createUnexpectedTokenTypeException($this->lookAhead->getType());
            }

            if ($type == Token::TYPE_QUERY) {
                return new Query($name, $alias, $arguments, $fields);
            } elseif ($type == Token::TYPE_TYPED_FRAGMENT) {
                return new TypedFragmentReference($name, $fields);
            } else {
                return new Mutation($name, $alias, $arguments, $fields);
            }
        } else {
            if ($highLevel && $type == Token::TYPE_MUTATION) {
                return new Mutation($name, $alias, $arguments);
            } elseif ($highLevel && $type == Token::TYPE_QUERY) {
                return new Query($name, $alias, $arguments, []);
            }

            return new Field($name, $alias, $arguments);
        }
    }

    protected function parseArgumentList()
    {
        $args  = [];
        $first = true;

        $this->expect(Token::TYPE_LPAREN);

        while (!$this->match(Token::TYPE_RPAREN) && !$this->end()) {
            if ($first) {
                $first = false;
            } else {
                $this->expect(Token::TYPE_COMMA);
            }

            $args[] = $this->parseArgument();
        }

        $this->expect(Token::TYPE_RPAREN);

        return $args;
    }

    protected function parseArgument()
    {
        $name = $this->parseIdentifier();
        $this->expect(Token::TYPE_COLON);
        $value = $this->parseValue();

        return new Argument($name, $value);
    }

    /**
     * @return array|InputList|InputObject|Literal|Variable
     *
     * @throws VariableTypeNotDefined
     */
    protected function parseValue()
    {
        switch ($this->lookAhead->getType()) {
            case Token::TYPE_LSQUARE_BRACE:
                return $this->parseList();

            case Token::TYPE_LBRACE:
                return $this->parseObject();

            case Token::TYPE_VARIABLE:
                return $this->parseVariableReference();

            case Token::TYPE_NUMBER:
            case Token::TYPE_STRING:
            case Token::TYPE_IDENTIFIER:
                return new Literal($this->lex()->getData());

            case Token::TYPE_NULL:
            case Token::TYPE_TRUE:
            case Token::TYPE_FALSE:
                return new Literal($this->lex()->getData());
        }

        throw $this->createUnexpectedException($this->lookAhead);
    }

    protected function parseList($createType = true)
    {
        $this->eat(Token::TYPE_LSQUARE_BRACE);

        $list = [];
        while (!$this->match(Token::TYPE_RSQUARE_BRACE) && !$this->end()) {
            $list[] = $this->parseListValue();

            if ($this->lookAhead->getType() != Token::TYPE_RSQUARE_BRACE) {
                $this->expect(Token::TYPE_COMMA);
            }
        }

        $this->expect(Token::TYPE_RSQUARE_BRACE);

        return $createType ? new InputList($list) : $list;
    }

    protected function parseListValue()
    {
        switch ($this->lookAhead->getType()) {
            case Token::TYPE_NUMBER:
            case Token::TYPE_STRING:
            case Token::TYPE_TRUE:
            case Token::TYPE_FALSE:
            case Token::TYPE_NULL:
            case Token::TYPE_IDENTIFIER:
                return $this->expect($this->lookAhead->getType())->getData();

            case Token::TYPE_VARIABLE:
                return $this->parseVariableReference();

            case Token::TYPE_LBRACE:
                return $this->parseObject(true);

            case Token::TYPE_LSQUARE_BRACE:
                return $this->parseList(false);
        }

        throw new SyntaxErrorException('Can\'t parse argument');
    }

    protected function parseObject($createType = true)
    {
        $this->eat(Token::TYPE_LBRACE);

        $object = [];
        while (!$this->match(Token::TYPE_RBRACE) && !$this->end()) {
            $key = $this->expectMulti([Token::TYPE_STRING, Token::TYPE_IDENTIFIER])->getData();
            $this->expect(Token::TYPE_COLON);
            $value = $this->parseListValue();

            if ($this->peek()->getType() != Token::TYPE_RBRACE) {
                $this->expect(Token::TYPE_COMMA);
            }

            $object[$key] = $value;
        }

        $this->eat(Token::TYPE_RBRACE);

        return $createType ? new InputObject($object) : $object;
    }

    protected function parseFragment()
    {
        $this->lex();
        $name = $this->parseIdentifier();

        $this->eat(Token::TYPE_ON);

        $model  = $this->parseIdentifier();
        $fields = $this->parseBody(Token::TYPE_QUERY, false);

        return new Fragment($name, $model, $fields);
    }

    protected function eat($type)
    {
        if ($this->match($type)) {
            return $this->lex();
        }

        return null;
    }

    protected function eatMulti($types)
    {
        if ($this->matchMulti($types)) {
            return $this->lex();
        }

        return null;
    }

    protected function matchMulti($types)
    {
        foreach ($types as $type) {
            if ($this->peek()->getType() == $type) {
                return true;
            }
        }

        return false;
    }
}

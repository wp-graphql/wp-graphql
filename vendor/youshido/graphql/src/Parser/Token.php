<?php
/**
 * Date: 23.11.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Parser;

class Token
{

    const TYPE_END        = 'end';
    const TYPE_IDENTIFIER = 'identifier';
    const TYPE_NUMBER     = 'number';
    const TYPE_STRING     = 'string';
    const TYPE_ON         = 'on';

    const TYPE_QUERY              = 'query';
    const TYPE_MUTATION           = 'mutation';
    const TYPE_FRAGMENT           = 'fragment';
    const TYPE_FRAGMENT_REFERENCE = '...';
    const TYPE_TYPED_FRAGMENT     = 'typed fragment';

    const TYPE_LBRACE        = '{';
    const TYPE_RBRACE        = '}';
    const TYPE_LPAREN        = '(';
    const TYPE_RPAREN        = ')';
    const TYPE_LSQUARE_BRACE = '[';
    const TYPE_RSQUARE_BRACE = ']';
    const TYPE_COLON         = ':';
    const TYPE_COMMA         = ',';
    const TYPE_VARIABLE      = '$';
    const TYPE_POINT         = '.';
    const TYPE_REQUIRED      = '!';

    const TYPE_NULL  = 'null';
    const TYPE_TRUE  = 'true';
    const TYPE_FALSE = 'false';


    /** @var mixed */
    private $data;
    /** @var  string */
    private $type;

    public function __construct($type, $data = null)
    {
        $this->type = $type;
        $this->data = $data;

        if ($this->getType() == self::TYPE_TRUE) {
            $this->data = true;
        }

        if ($this->getType() == self::TYPE_FALSE) {
            $this->data = false;
        }

        if ($this->getType() == self::TYPE_NULL) {
            $this->data = null;
        }

    }

    public static function tokenName($tokenType)
    {
        return [
            self::TYPE_END                => 'END',
            self::TYPE_IDENTIFIER         => 'IDENTIFIER',
            self::TYPE_NUMBER             => 'NUMBER',
            self::TYPE_STRING             => 'STRING',
            self::TYPE_ON                 => 'ON',
            self::TYPE_QUERY              => 'QUERY',
            self::TYPE_MUTATION           => 'MUTATION',
            self::TYPE_FRAGMENT           => 'FRAGMENT',
            self::TYPE_FRAGMENT_REFERENCE => 'FRAGMENT_REFERENCE',
            self::TYPE_TYPED_FRAGMENT     => 'TYPED_FRAGMENT',
            self::TYPE_LBRACE             => 'LBRACE',
            self::TYPE_RBRACE             => 'RBRACE',
            self::TYPE_LPAREN             => 'LPAREN',
            self::TYPE_RPAREN             => 'RPAREN',
            self::TYPE_LSQUARE_BRACE      => 'LSQUARE_BRACE',
            self::TYPE_RSQUARE_BRACE      => 'RSQUARE_BRACE',
            self::TYPE_COLON              => 'COLON',
            self::TYPE_COMMA              => 'COMMA',
            self::TYPE_VARIABLE           => 'VARIABLE',
            self::TYPE_POINT              => 'POINT',
            self::TYPE_NULL               => 'NULL',
            self::TYPE_TRUE               => 'TRUE',
            self::TYPE_FALSE              => 'FALSE',
            self::TYPE_REQUIRED           => 'REQUIRED',
        ][$tokenType];
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

}

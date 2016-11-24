<?php
/**
 * Date: 13.05.16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\Tests\Parser;


use Youshido\GraphQL\Parser\Ast\Argument;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\InputList;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\InputObject;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\Literal;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\Variable;
use Youshido\GraphQL\Parser\Ast\Field;
use Youshido\GraphQL\Parser\Ast\Fragment;
use Youshido\GraphQL\Parser\Ast\FragmentReference;
use Youshido\GraphQL\Parser\Ast\Query;
use Youshido\GraphQL\Parser\Ast\TypedFragmentReference;

class AstTest extends \PHPUnit_Framework_TestCase
{

    public function testArgument()
    {
        $argument = new Argument('test', new Literal('test'));

        $this->assertNotNull($argument->getValue());
        $this->assertEquals($argument->getName(), 'test');

        $argument->setName('test2');
        $argument->setValue('some value');

        $this->assertEquals($argument->getName(), 'test2');
        $this->assertEquals($argument->getValue(), 'some value');
    }

    public function testField()
    {
        $field = new Field('field');

        $this->assertEquals($field->getName(), 'field');
        $this->assertEmpty($field->getArguments());
        $this->assertFalse($field->hasArguments());

        $field->setAlias('alias');
        $field->setName('alias');
        $this->assertEquals($field->getAlias(), 'alias');
        $this->assertEquals($field->getName(), 'alias');

        $field->addArgument(new Argument('argument', new Literal('argument value')));
        $this->assertTrue($field->hasArguments());
        $this->assertEquals(['argument' => 'argument value'], $field->getKeyValueArguments());
    }

    public function testFragment()
    {
        $fields = [
            new Field('field')
        ];

        $fragment = new Fragment('shipInfo', 'Ship', $fields);

        $this->assertEquals('shipInfo', $fragment->getName());
        $this->assertEquals('Ship', $fragment->getModel());
        $this->assertEquals($fields, $fragment->getFields());

        $fragment->setName('largeShipInfo');
        $this->assertEquals('largeShipInfo', $fragment->getName());

        $fragment->setModel('Boat');
        $this->assertEquals('Boat', $fragment->getModel());

        $newField = [
            new Field('id')
        ];
        $fragment->setFields($newField);
        $this->assertEquals($newField, $fragment->getFields());

        $this->assertFalse($fragment->isUsed());
        $fragment->setUsed(true);
        $this->assertTrue($fragment->isUsed());
    }

    public function testFragmentReference()
    {
        $reference = new FragmentReference('shipInfo');

        $this->assertEquals('shipInfo', $reference->getName());

        $reference->setName('largeShipInfo');
        $this->assertEquals('largeShipInfo', $reference->getName());
    }

    public function testTypedFragmentReference()
    {
        $fields = [
            new Field('id')
        ];

        $reference = new TypedFragmentReference('Ship', $fields);

        $this->assertEquals('Ship', $reference->getTypeName());
        $this->assertEquals($fields, $reference->getFields());

        $reference->setTypeName('BigBoat');
        $this->assertEquals('BigBoat', $reference->getTypeName());

        $newFields = [
            new Field('name'),
            new Field('id')
        ];

        $reference->setFields($newFields);
        $this->assertEquals($newFields, $reference->getFields());
    }

    public function testQuery()
    {
        $arguments = [
            new Argument('limit', new Literal('10'))
        ];

        $fields = [
            new Field('id')
        ];

        $query = new Query('ships', 'lastShips', $arguments, $fields);

        $this->assertEquals('ships', $query->getName());
        $this->assertEquals('lastShips', $query->getAlias());
        $this->assertEquals($arguments, $query->getArguments());
        $this->assertEquals(['limit' => '10'], $query->getKeyValueArguments());
        $this->assertEquals($fields, $query->getFields());
        $this->assertTrue($query->hasArguments());
        $this->assertTrue($query->hasFields());

        $query->setFields([]);
        $query->setArguments([]);

        $this->assertEmpty($query->getArguments());
        $this->assertEmpty($query->getFields());
        $this->assertEmpty($query->getKeyValueArguments());

        $this->assertFalse($query->hasArguments());
        $this->assertFalse($query->hasFields());

        $query->addArgument(new Argument('offset', new Literal(10)));
        $this->assertTrue($query->hasArguments());
    }

    public function testArgumentValues()
    {
        $list = new InputList(['a', 'b']);
        $this->assertEquals(['a', 'b'], $list->getValue());
        $list->setValue(['a']);
        $this->assertEquals(['a'], $list->getValue());

        $inputObject = new InputObject(['a', 'b']);
        $this->assertEquals(['a', 'b'], $inputObject->getValue());
        $inputObject->setValue(['a']);
        $this->assertEquals(['a'], $inputObject->getValue());

        $literal = new Literal('text');
        $this->assertEquals('text', $literal->getValue());
        $literal->setValue('new text');
        $this->assertEquals('new text', $literal->getValue());
    }

    public function testVariable()
    {
        $variable = new Variable('id', 'int');

        $this->assertEquals('id', $variable->getName());
        $this->assertEquals('int', $variable->getTypeName());
        $this->assertFalse($variable->isNullable());
        $this->assertFalse($variable->isArray());

        $variable->setTypeName('string');
        $this->assertEquals('string', $variable->getTypeName());

        $variable->setName('limit');
        $this->assertEquals('limit', $variable->getName());

        $variable->setIsArray(true);
        $variable->setNullable(true);

        $this->assertTrue($variable->isNullable());
        $this->assertTrue($variable->isArray());

        $variable->setValue(new Literal('text'));
        $this->assertEquals(new Literal('text'), $variable->getValue());
    }

    /**
     * @expectedException \LogicException
     */
    public function testVariableLogicException()
    {
        $variable = new Variable('id', 'int');
        $variable->getValue();
    }
}

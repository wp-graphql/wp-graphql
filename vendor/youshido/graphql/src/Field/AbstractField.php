<?php
/**
 * Date: 13.05.16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Field;

use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Config\Traits\ResolvableObjectTrait;
use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Traits\AutoNameTrait;
use Youshido\GraphQL\Type\Traits\FieldsArgumentsAwareObjectTrait;
use Youshido\GraphQL\Type\TypeFactory;
use Youshido\GraphQL\Type\TypeService;

abstract class AbstractField implements FieldInterface
{

    use FieldsArgumentsAwareObjectTrait;
    use ResolvableObjectTrait;
    use AutoNameTrait {
        getName as getAutoName;
    }
    protected $isFinal = false;

    private $resolveFunctionCache = null;
    private $nameCache            = null;

    public function __construct(array $config = [])
    {
        if (empty($config['type'])) {
            $config['type'] = $this->getType();
            $config['name'] = $this->getName();
            if (empty($config['name'])) {
                $config['name'] =$this->getAutoName();
            }
        }

        if (TypeService::isScalarType($config['type'])) {
            $config['type'] = TypeFactory::getScalarType($config['type']);
        }
        $this->nameCache = isset($config['name']) ? $config['name'] : $this->getAutoName();

        $this->config = new FieldConfig($config, $this, $this->isFinal);
        $this->build($this->config);
    }

    /**
     * @return AbstractObjectType|AbstractType
     */
    abstract public function getType();

    public function build(FieldConfig $config)
    {
    }

    public function setType($type)
    {
        $this->getConfig()->set('type', $type);
    }

    public function getName()
    {
        return $this->nameCache;
    }

    public function isDeprecated()
    {
        return $this->getConfigValue('isDeprecated', false);
    }

    public function getDeprecationReason()
    {
        return $this->getConfigValue('deprecationReason');
    }
}

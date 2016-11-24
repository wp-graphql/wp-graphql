<?php
/**
 * Date: 07.12.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Type\Enum;

use Youshido\GraphQL\Config\Object\EnumTypeConfig;

final class EnumType extends AbstractEnumType
{

    public function __construct(array $config)
    {
        $this->config = new EnumTypeConfig($config, $this, true);
    }

    public function getValues()
    {
        return $this->getConfig()->getValues();
    }

}

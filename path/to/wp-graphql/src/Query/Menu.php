# complete code
namespace WPGraphQL\Query;

use WPGraphQL\Types\WPObject;
use WPGraphQL\Type\WPObject\Menu;

class MenuQuery extends WPObject
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var int
     */
    public $databaseId;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $slug;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $menuLocation;

    /**
     * @var string
     */
    public $menuLocationEnum;

    /**
     * @var string
     */
    public $menuLocationName;

    /**
     * @var string
     */
    public $menuLocationSlug;

    /**
     * @var string
     */
    public $menuLocationNameSlug;

    /**
     * @var string
     */
    public $menuLocationSlug;

    /**
     * @var string
     */
    public $menuLocationName;

    /**
     * @var string
     */
    public $menuLocationEnum;

    /**
     * @var string
     */
    public $menuLocation;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $slug;

    /**
     * @var int
     */
    public $databaseId;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $id;

    /**
     * @param array $args
     * @return array
     */
    public static function getFields(array $args = []): array
    {
        $fields = [
            'id' => [
                'type' => 'ID',
                'description' => __( 'The ID of the menu', 'wp-graphql' ),
            ],
            'databaseId' => [
                'type' => 'Int',
                'description' => __( 'The database ID of the menu', 'wp-graphql' ),
            ],
            'name' => [
                'type' => 'String',
                'description' => __( 'The name of the menu', 'wp-graphql' ),
            ],
            'slug' => [
                'type' => 'String',
                'description' => __( 'The slug of the menu', 'wp-graphql' ),
            ],
            'description' => [
                'type' => 'String',
                'description' => __( 'The description of the menu', 'wp-graphql' ),
            ],
            'type' => [
                'type' => 'String',
                'description' => __( 'The type of the menu', 'wp-graphql' ),
            ],
            'menuLocation' => [
                'type' => 'String',
                'description' => __( 'The location of the menu', 'wp-graphql' ),
            ],
            'menuLocationEnum' => [
                'type' => 'String',
                'description' => __( 'The location of the menu', 'wp-graphql' ),
            ],
            'menuLocationName' => [
                'type' => 'String',
                'description' => __( 'The name of the menu location', 'wp-graphql' ),
            ],
            'menuLocationSlug' => [
                'type' => 'String',
                'description' => __( 'The slug of the menu location', 'wp-graphql' ),
            ],
            'menuLocationNameSlug' => [
                'type' => 'String',
                'description' => __( 'The slug of the menu location name', 'wp-graphql' ),
            ],
        ];

        return $fields;
    }

    /**
     * @param array $args
     * @return array
     */
    public static function resolve(array $args = []): array
    {
        $idType = $args['idType'] ?? null;

        if (empty($idType)) {
            return [];
        }

        switch ($idType) {
            case 'LOCATION':
                $menuId = $args['id'] ?? null;

                if (empty($menuId)) {
                    return [];
                }

                $menu = wp_get_menu_object($menuId);

                if (!$menu) {
                    return [];
                }

                return [
                    'id' => $menu->ID,
                    'databaseId' => $menu->db_id,
                    'name' => $menu->name,
                    'slug' => $menu->slug,
                    'description' => $menu->description,
                    'type' => $menu->type,
                    'menuLocation' => $menu->menu_location,
                    'menuLocationEnum' => $menu->menu_location_enum,
                    'menuLocationName' => $menu->menu_location_name,
                    'menuLocationSlug' => $menu->menu_location_slug,
                    'menuLocationNameSlug' => $menu->menu_location_name_slug,
                ];
            default:
                return [];
        }
    }
}
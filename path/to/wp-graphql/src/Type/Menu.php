# complete code
namespace WPGraphQL\Type\WPObject;

use WPGraphQL\Types\WPObject;

class Menu extends WPObject
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
}
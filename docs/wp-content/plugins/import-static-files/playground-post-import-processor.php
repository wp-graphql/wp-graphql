<?php
/**
 * A copy of the WP_Interactivity_API_Directives_Processor class
 * from the Gutenberg plugin.
 *
 * @package WordPress
 * @subpackage Interactivity API
 * @since 6.5.0
 */

/**
 * Class used to iterate over the tags of an HTML string and help process the
 * directive attributes.
 *
 * @since 6.5.0
 *
 * @access private
 */
final class Playground_Post_Import_Processor extends WP_HTML_Tag_Processor {
    /**
     * List of tags whose closer tag is not visited by the WP_HTML_Tag_Processor.
     *
     * @since 6.5.0
     * @var string[]
     */
    const TAGS_THAT_DONT_VISIT_CLOSER_TAG = array(
        'SCRIPT',
        'IFRAME',
        'NOEMBED',
        'NOFRAMES',
        'STYLE',
        'TEXTAREA',
        'TITLE',
        'XMP',
    );

    /**
     * Returns the content between two balanced template tags.
     *
     * It positions the cursor in the closer tag of the balanced template tag,
     * if it exists.
     *
     * @since 6.5.0
     *
     * @access private
     *
     * @return string|null The content between the current opener template tag and its matching closer tag or null if it
     *                     doesn't find the matching closing tag or the current tag is not a template opener tag.
     */
    private function get_content_between_balanced_template_tags() {
        $positions = $this->get_after_opener_tag_and_before_closer_tag_positions();
        if ( ! $positions ) {
            return null;
        }

        return substr( $this->html, $positions['after_opener_tag'], $positions['before_closer_tag'] - $positions['after_opener_tag'] );
    }

    public function remove_balanced_tag()
    {
        $positions = $this->get_after_opener_tag_and_before_closer_tag_positions();
        if ( ! $positions ) {
            return null;
        }

        $this->lexical_updates[] = new WP_HTML_Text_Replacement(
            $positions['before_opener_tag'],
            $positions['after_closer_tag'] - $positions['before_opener_tag'],
            ''
        );

        return true;	
    }

    public function get_token_indices()
    {
        $name = '_current_token';
        $this->set_bookmark($name);
        $bookmark = $this->bookmarks[$name];
        $this->release_bookmark($name);
        return $bookmark;
    }

    /**
     * Sets the content between two balanced tags.
     *
     * @since 6.5.0
     *
     * @access private
     *
     * @param string $new_content The string to replace the content between the matching tags.
     * @return bool Whether the content was successfully replaced.
     */
    private function set_content_between_balanced_tags( string $new_content ): bool {
        $positions = $this->get_after_opener_tag_and_before_closer_tag_positions( true );
        if ( ! $positions ) {
            return false;
        }
        list( $after_opener_tag, $before_closer_tag ) = $positions;

        $this->lexical_updates[] = new WP_HTML_Text_Replacement(
            $after_opener_tag,
            $before_closer_tag - $after_opener_tag,
            esc_html( $new_content )
        );

        return true;
    }

    /**
     * Gets the positions right after the opener tag and right before the closer
     * tag in a balanced tag.
     *
     * By default, it positions the cursor in the closer tag of the balanced tag.
     * If $rewind is true, it seeks back to the opener tag.
     *
     * @since 6.5.0
     *
     * @access private
     *
     * @param bool $rewind Optional. Whether to seek back to the opener tag after finding the positions. Defaults to false.
     * @return array|null Start and end byte position, or null when no balanced tag bookmarks.
     */
    private function get_after_opener_tag_and_before_closer_tag_positions( bool $rewind = false ) {
        // Flushes any changes.
        $this->get_updated_html();

        $bookmarks = $this->get_balanced_tag_bookmarks();
        if ( ! $bookmarks ) {
            return null;
        }
        list( $opener_tag, $closer_tag ) = $bookmarks;

        $positions = array(
            'before_opener_tag' => $this->bookmarks[$opener_tag]->start,
            'after_opener_tag' => $this->bookmarks[$opener_tag]->start + $this->bookmarks[$opener_tag]->length + 1,
            'before_closer_tag' => $this->bookmarks[$closer_tag]->start,
            'after_closer_tag' => $this->bookmarks[$closer_tag]->start + $this->bookmarks[$closer_tag]->length + 1,
        );

        if ( $rewind ) {
            $this->seek( $opener_tag );
        }

        $this->release_bookmark( $opener_tag );
        $this->release_bookmark( $closer_tag );

        return $positions;
    }

    /**
     * Returns a pair of bookmarks for the current opener tag and the matching
     * closer tag.
     *
     * It positions the cursor in the closer tag of the balanced tag, if it
     * exists.
     *
     * @since 6.5.0
     *
     * @return array|null A pair of bookmarks, or null if there's no matching closing tag.
     */
    private function get_balanced_tag_bookmarks() {
        static $i   = 0;
        $opener_tag = 'opener_tag_of_balanced_tag_' . ++$i;

        $this->set_bookmark( $opener_tag );
        if ( ! $this->next_balanced_tag_closer_tag() ) {
            $this->release_bookmark( $opener_tag );
            return null;
        }

        $closer_tag = 'closer_tag_of_balanced_tag_' . ++$i;
        $this->set_bookmark( $closer_tag );

        return array( $opener_tag, $closer_tag );
    }

    /**
     * Finds the matching closing tag for an opening tag.
     *
     * When called while the processor is on an open tag, it traverses the HTML
     * until it finds the matching closer tag, respecting any in-between content,
     * including nested tags of the same name. Returns false when called on a
     * closer tag, a tag that doesn't have a closer tag (void), a tag that
     * doesn't visit the closer tag, or if no matching closing tag was found.
     *
     * @since 6.5.0
     *
     * @access private
     *
     * @return bool Whether a matching closing tag was found.
     */
    private function next_balanced_tag_closer_tag(): bool {
        $depth    = 0;
        $tag_name = $this->get_tag();

        if ( ! $this->has_and_visits_its_closer_tag() ) {
            return false;
        }

        while ( $this->next_tag(
            array(
                'tag_name'    => $tag_name,
                'tag_closers' => 'visit',
            )
        ) ) {
            if ( ! $this->is_tag_closer() ) {
                ++$depth;
                continue;
            }

            if ( 0 === $depth ) {
                return true;
            }

            --$depth;
        }

        return false;
    }

    /**
     * Checks whether the current tag has and will visit its matching closer tag.
     *
     * @since 6.5.0
     *
     * @access private
     *
     * @return bool Whether the current tag has a closer tag.
     */
    private function has_and_visits_its_closer_tag(): bool {
        $tag_name = $this->get_tag();

        return null !== $tag_name && (
            // @TODO: Backport the 6.5 is_void method
            ! in_array( $tag_name, self::TAGS_THAT_DONT_VISIT_CLOSER_TAG, true )
        );
    }
}

<?php
/**
 * Rank Math Integration
 *
 * @package CreatorCore
 */

namespace CreatorCore\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class RankMathIntegration
 *
 * Handles Rank Math SEO operations
 */
class RankMathIntegration {

    /**
     * Check if Rank Math is available
     *
     * @return bool
     */
    public function is_available(): bool {
        return function_exists( 'rank_math' );
    }

    /**
     * Get Rank Math version
     *
     * @return string|null
     */
    public function get_version(): ?string {
        if ( ! $this->is_available() ) {
            return null;
        }

        return defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : null;
    }

    /**
     * Get SEO data for a post
     *
     * @param int $post_id Post ID.
     * @return array
     */
    public function get_seo_data( int $post_id ): array {
        if ( ! $this->is_available() ) {
            return [];
        }

        return [
            'title'            => get_post_meta( $post_id, 'rank_math_title', true ),
            'description'      => get_post_meta( $post_id, 'rank_math_description', true ),
            'focus_keyword'    => get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
            'robots'           => get_post_meta( $post_id, 'rank_math_robots', true ),
            'canonical_url'    => get_post_meta( $post_id, 'rank_math_canonical_url', true ),
            'og_title'         => get_post_meta( $post_id, 'rank_math_facebook_title', true ),
            'og_description'   => get_post_meta( $post_id, 'rank_math_facebook_description', true ),
            'og_image'         => get_post_meta( $post_id, 'rank_math_facebook_image', true ),
            'twitter_title'    => get_post_meta( $post_id, 'rank_math_twitter_title', true ),
            'twitter_description' => get_post_meta( $post_id, 'rank_math_twitter_description', true ),
            'seo_score'        => get_post_meta( $post_id, 'rank_math_seo_score', true ),
        ];
    }

    /**
     * Update SEO data for a post
     *
     * @param int   $post_id Post ID.
     * @param array $data    SEO data.
     * @return bool
     */
    public function update_seo_data( int $post_id, array $data ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        $meta_map = [
            'title'            => 'rank_math_title',
            'description'      => 'rank_math_description',
            'focus_keyword'    => 'rank_math_focus_keyword',
            'robots'           => 'rank_math_robots',
            'canonical_url'    => 'rank_math_canonical_url',
            'og_title'         => 'rank_math_facebook_title',
            'og_description'   => 'rank_math_facebook_description',
            'og_image'         => 'rank_math_facebook_image',
            'twitter_title'    => 'rank_math_twitter_title',
            'twitter_description' => 'rank_math_twitter_description',
        ];

        foreach ( $data as $key => $value ) {
            if ( isset( $meta_map[ $key ] ) ) {
                update_post_meta( $post_id, $meta_map[ $key ], sanitize_text_field( $value ) );
            }
        }

        return true;
    }

    /**
     * Set focus keyword
     *
     * @param int    $post_id Post ID.
     * @param string $keyword Focus keyword.
     * @return bool
     */
    public function set_focus_keyword( int $post_id, string $keyword ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        return update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( $keyword ) );
    }

    /**
     * Set meta title
     *
     * @param int    $post_id Post ID.
     * @param string $title   Meta title.
     * @return bool
     */
    public function set_meta_title( int $post_id, string $title ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        return update_post_meta( $post_id, 'rank_math_title', sanitize_text_field( $title ) );
    }

    /**
     * Set meta description
     *
     * @param int    $post_id     Post ID.
     * @param string $description Meta description.
     * @return bool
     */
    public function set_meta_description( int $post_id, string $description ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        return update_post_meta( $post_id, 'rank_math_description', sanitize_textarea_field( $description ) );
    }

    /**
     * Get SEO score
     *
     * @param int $post_id Post ID.
     * @return int|null
     */
    public function get_seo_score( int $post_id ): ?int {
        if ( ! $this->is_available() ) {
            return null;
        }

        $score = get_post_meta( $post_id, 'rank_math_seo_score', true );

        return $score ? (int) $score : null;
    }

    /**
     * Set robots meta
     *
     * @param int   $post_id Post ID.
     * @param array $robots  Robots directives.
     * @return bool
     */
    public function set_robots( int $post_id, array $robots ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        return update_post_meta( $post_id, 'rank_math_robots', $robots );
    }

    /**
     * Set Open Graph data
     *
     * @param int   $post_id Post ID.
     * @param array $og_data Open Graph data.
     * @return bool
     */
    public function set_open_graph( int $post_id, array $og_data ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        if ( isset( $og_data['title'] ) ) {
            update_post_meta( $post_id, 'rank_math_facebook_title', sanitize_text_field( $og_data['title'] ) );
        }

        if ( isset( $og_data['description'] ) ) {
            update_post_meta( $post_id, 'rank_math_facebook_description', sanitize_textarea_field( $og_data['description'] ) );
        }

        if ( isset( $og_data['image'] ) ) {
            update_post_meta( $post_id, 'rank_math_facebook_image', esc_url_raw( $og_data['image'] ) );
        }

        return true;
    }

    /**
     * Set Twitter Card data
     *
     * @param int   $post_id      Post ID.
     * @param array $twitter_data Twitter card data.
     * @return bool
     */
    public function set_twitter_card( int $post_id, array $twitter_data ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        if ( isset( $twitter_data['title'] ) ) {
            update_post_meta( $post_id, 'rank_math_twitter_title', sanitize_text_field( $twitter_data['title'] ) );
        }

        if ( isset( $twitter_data['description'] ) ) {
            update_post_meta( $post_id, 'rank_math_twitter_description', sanitize_textarea_field( $twitter_data['description'] ) );
        }

        return true;
    }

    /**
     * Set canonical URL
     *
     * @param int    $post_id Post ID.
     * @param string $url     Canonical URL.
     * @return bool
     */
    public function set_canonical_url( int $post_id, string $url ): bool {
        if ( ! $this->is_available() ) {
            return false;
        }

        return update_post_meta( $post_id, 'rank_math_canonical_url', esc_url_raw( $url ) );
    }

    /**
     * Get schema data
     *
     * @param int $post_id Post ID.
     * @return array
     */
    public function get_schema( int $post_id ): array {
        if ( ! $this->is_available() ) {
            return [];
        }

        $schema = get_post_meta( $post_id, 'rank_math_schema_' . get_post_type( $post_id ), true );

        return is_array( $schema ) ? $schema : [];
    }

    /**
     * Get global SEO settings
     *
     * @return array
     */
    public function get_settings(): array {
        if ( ! $this->is_available() ) {
            return [];
        }

        return [
            'titles'    => get_option( 'rank-math-options-titles', [] ),
            'general'   => get_option( 'rank-math-options-general', [] ),
            'sitemap'   => get_option( 'rank-math-options-sitemap', [] ),
        ];
    }
}

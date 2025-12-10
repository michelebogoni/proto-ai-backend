<?php
/**
 * Elementor Page Builder
 *
 * Generates complete Elementor pages from freeform AI specifications.
 * Uses an AI-first approach where AI generates any JSON structure and
 * the builder converts it to Elementor format with validation and fallbacks.
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\Integrations;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Context\ThinkingLogger;

/**
 * Class ElementorPageBuilder
 *
 * Creates production-ready Elementor pages from freeform AI specifications.
 * Handles page structure, styling, responsive design, SEO metadata, and snapshots.
 */
class ElementorPageBuilder {

	/**
	 * Thinking logger instance
	 *
	 * @var ThinkingLogger|null
	 */
	private ?ThinkingLogger $logger;

	/**
	 * Elementor version
	 *
	 * @var string
	 */
	private string $elementor_version = '0.0.0';

	/**
	 * Whether Elementor Pro is available
	 *
	 * @var bool
	 */
	private bool $is_pro = false;

	/**
	 * Available breakpoints for current Elementor version
	 *
	 * @var array
	 */
	private array $breakpoints = [];

	/**
	 * Supported widget types mapping
	 *
	 * @var array
	 */
	private array $supported_widgets = [
		'heading'     => 'heading',
		'title'       => 'heading',
		'h1'          => 'heading',
		'h2'          => 'heading',
		'h3'          => 'heading',
		'text'        => 'text-editor',
		'paragraph'   => 'text-editor',
		'content'     => 'text-editor',
		'button'      => 'button',
		'cta'         => 'button',
		'image'       => 'image',
		'img'         => 'image',
		'photo'       => 'image',
		'spacer'      => 'spacer',
		'space'       => 'spacer',
		'divider'     => 'divider',
		'separator'   => 'divider',
		'icon'        => 'icon',
		'icon-box'    => 'icon-box',
		'icon_box'    => 'icon-box',
		'feature'     => 'icon-box',
		'feature-box' => 'icon-box',
		'video'       => 'video',
		'html'        => 'html',
		'shortcode'   => 'shortcode',
	];

	/**
	 * Count of widgets that fell back to text-editor
	 *
	 * @var int
	 */
	private int $fallback_count = 0;

	/**
	 * List of unknown widget types encountered
	 *
	 * @var array
	 */
	private array $unknown_widget_types = [];

	/**
	 * Constructor
	 *
	 * @param ThinkingLogger|null $logger Optional thinking logger.
	 * @throws \Exception If Elementor is not installed.
	 */
	public function __construct( ?ThinkingLogger $logger = null ) {
		$this->logger = $logger;
		$this->detect_elementor_setup();
		$this->detect_breakpoints();
	}

	/**
	 * Detect Elementor version and Pro status
	 *
	 * @throws \Exception If Elementor is not available.
	 */
	private function detect_elementor_setup(): void {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			throw new \Exception( 'Elementor is not installed or activated' );
		}

		$this->elementor_version = defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '0.0.0';
		$this->is_pro = defined( 'ELEMENTOR_PRO_VERSION' );

		$this->log(
			'Elementor ' . $this->elementor_version . ( $this->is_pro ? ' Pro' : '' ) . ' detected',
			'info'
		);
	}

	/**
	 * Detect available breakpoints based on Elementor version
	 *
	 * Elementor 3.4+ has additional breakpoints (widescreen, laptop, tablet_extra, mobile_extra)
	 */
	private function detect_breakpoints(): void {
		$this->breakpoints = [
			'desktop' => [ 'min' => 1025, 'max' => null ],
			'tablet'  => [ 'min' => 768, 'max' => 1024 ],
			'mobile'  => [ 'min' => 0, 'max' => 767 ],
		];

		// Elementor 3.4+ has additional breakpoints.
		if ( version_compare( $this->elementor_version, '3.4.0', '>=' ) ) {
			$this->breakpoints = [
				'widescreen'   => [ 'min' => 2400, 'max' => null ],
				'desktop'      => [ 'min' => 1025, 'max' => 2399 ],
				'laptop'       => [ 'min' => 1025, 'max' => 1366 ],
				'tablet_extra' => [ 'min' => 1025, 'max' => 1200 ],
				'tablet'       => [ 'min' => 768, 'max' => 1024 ],
				'mobile_extra' => [ 'min' => 481, 'max' => 767 ],
				'mobile'       => [ 'min' => 0, 'max' => 480 ],
			];

			$this->log( 'Extended breakpoints available (Elementor 3.4+)', 'debug' );
		}
	}

	/**
	 * Generate a page from freeform AI specification
	 *
	 * This is the main entry point for AI-generated pages. The AI can describe
	 * any layout structure and this method will convert it to valid Elementor JSON.
	 *
	 * @param array $spec Freeform page specification from AI.
	 * @return array Result with page_id, url, edit_url, snapshot_id.
	 * @throws \Exception On validation or creation failure.
	 */
	public function generate_page_from_freeform_spec( array $spec ): array {
		$this->log( 'Starting freeform page generation...', 'info' );

		// Reset fallback tracking for this generation.
		$this->reset_fallback_tracking();

		// Validate basic requirements.
		$this->validate_freeform_spec( $spec );
		$this->log( 'Freeform specification validated', 'debug' );

		// Convert freeform spec to Elementor structure.
		$this->log( 'Converting freeform spec to Elementor structure...', 'info' );
		$elementor_sections = $this->convert_freeform_to_elementor( $spec );
		$this->log( count( $elementor_sections ) . ' section(s) converted', 'debug' );

		// Log fallback summary if any unknown widgets were encountered.
		$this->log_fallback_summary();

		// Validate the generated Elementor JSON.
		$this->log( 'Validating Elementor JSON structure...', 'info' );
		$validation = $this->validate_elementor_json( $elementor_sections );
		if ( ! $validation['valid'] ) {
			$this->log( 'JSON validation failed: ' . implode( ', ', $validation['errors'] ), 'error' );
			throw new \Exception( 'Invalid Elementor JSON: ' . implode( ', ', $validation['errors'] ) );
		}
		$this->log( 'Elementor JSON validation passed', 'debug' );

		// Serialize Elementor data.
		$elementor_data = wp_json_encode( $elementor_sections );
		$this->log( 'Elementor data ready (' . strlen( $elementor_data ) . ' bytes)', 'debug' );

		// Create snapshot before page creation (for undo).
		$snapshot_id = null;
		if ( class_exists( '\CreatorCore\Snapshot\SnapshotManager' ) ) {
			$this->log( 'Creating pre-creation snapshot...', 'info' );
			$snapshot_id = $this->create_snapshot( 'pre_elementor_page_creation', $spec );
			$this->log( 'Snapshot created (ID: ' . $snapshot_id . ')', 'debug' );
		}

		// Create WordPress page.
		$this->log( 'Creating WordPress page...', 'info' );
		$page_id = $this->create_page( $spec, $elementor_data );
		$this->log( 'Page created (ID: ' . $page_id . ')', 'info' );

		// Add SEO metadata with cascade (RankMath -> Yoast -> Basic).
		$this->log( 'Adding SEO metadata...', 'info' );
		$seo_result = $this->add_seo_metadata_cascade( $page_id, $spec['seo'] ?? [] );
		$this->log( 'SEO metadata added via ' . $seo_result['provider'], 'debug' );

		// Set featured image if provided.
		if ( ! empty( $spec['featured_image'] ) ) {
			$this->log( 'Setting featured image...', 'debug' );
			$this->set_featured_image( $page_id, $spec['featured_image'] );
		}

		// Update snapshot with page_id for rollback.
		if ( $snapshot_id && class_exists( '\CreatorCore\Snapshot\SnapshotManager' ) ) {
			$this->update_snapshot( $snapshot_id, [ 'page_id' => $page_id ] );
		}

		// Verify rendering.
		$this->log( 'Verifying page rendering...', 'debug' );
		$verified = $this->verify_rendering( $page_id );
		$this->log( 'Verification: ' . ( $verified ? 'PASS' : 'WARNING' ), $verified ? 'info' : 'warning' );

		// Clear Elementor cache.
		$this->clear_elementor_cache();

		// Check fallback ratio - reject if more than 50% of widgets are unsupported.
		$total_widgets = $this->count_total_widgets( $elementor_sections );
		if ( $total_widgets > 0 && $this->fallback_count > 0 ) {
			$fallback_ratio = $this->fallback_count / $total_widgets;
			if ( $fallback_ratio > 0.5 ) {
				// Rollback: delete the created page.
				wp_delete_post( $page_id, true );
				$this->log(
					'Page rejected: ' . round( $fallback_ratio * 100 ) . '% unsupported widgets',
					'error'
				);
				throw new \Exception(
					'Specification rejected: ' . round( $fallback_ratio * 100 ) .
					'% unsupported widgets (max 50% allowed). Unknown types: ' .
					implode( ', ', $this->unknown_widget_types ) .
					'. Use supported widget types: heading, text, button, image, spacer, divider, icon, icon-box, video, html, shortcode.'
				);
			}
		}

		$result = [
			'success'     => true,
			'page_id'     => $page_id,
			'url'         => get_permalink( $page_id ),
			'edit_url'    => $this->get_elementor_edit_url( $page_id ),
			'wp_edit'     => get_edit_post_link( $page_id, 'raw' ),
			'snapshot_id' => $snapshot_id,
		];

		$this->log( 'Page generation complete: ' . $result['url'], 'success' );

		return $result;
	}

	/**
	 * Convert freeform AI specification to Elementor structure
	 *
	 * @param array $spec Freeform specification.
	 * @return array Elementor-compatible sections array.
	 */
	private function convert_freeform_to_elementor( array $spec ): array {
		$sections = [];

		// Handle sections from spec.
		$spec_sections = $spec['sections'] ?? $spec['layout'] ?? $spec['content'] ?? [];

		foreach ( $spec_sections as $section_spec ) {
			$section = $this->convert_freeform_section( $section_spec );
			if ( $section ) {
				// Handle pre-built sections that return multiple sections.
				if ( isset( $section[0] ) && is_array( $section[0] ) && isset( $section[0]['elType'] ) ) {
					$sections = array_merge( $sections, $section );
				} else {
					$sections[] = $section;
				}
			}
		}

		return $sections;
	}

	/**
	 * Convert a freeform section specification to Elementor format
	 *
	 * @param array $section_spec Section specification.
	 * @return array|null Elementor section structure or null if invalid.
	 */
	private function convert_freeform_section( array $section_spec ): ?array {
		// Check for pre-built section types first.
		$section_type = $section_spec['type'] ?? $section_spec['section_type'] ?? 'custom';

		switch ( strtolower( $section_type ) ) {
			case 'hero':
				return ElementorSchemaLearner::build_hero_section( $section_spec );

			case 'features':
			case 'feature-grid':
			case 'services':
				return ElementorSchemaLearner::build_features_section( $section_spec );

			case 'cta':
			case 'call-to-action':
				return ElementorSchemaLearner::build_cta_section( $section_spec );

			default:
				return $this->build_custom_freeform_section( $section_spec );
		}
	}

	/**
	 * Build a custom section from freeform specification
	 *
	 * @param array $spec Section specification.
	 * @return array Elementor section structure.
	 */
	private function build_custom_freeform_section( array $spec ): array {
		// Extract section-level settings.
		$section_options = [
			'background_color' => $spec['background_color'] ?? $spec['bg_color'] ?? $spec['background'] ?? '',
			'background_image' => $spec['background_image'] ?? $spec['bg_image'] ?? '',
			'min_height'       => $spec['min_height'] ?? $spec['height'] ?? 0,
			'padding'          => $this->normalize_padding( $spec['padding'] ?? [] ),
		];

		$section = ElementorSchemaLearner::get_section_template( $section_options );

		// Build columns/elements.
		$elements = $spec['columns'] ?? $spec['elements'] ?? $spec['content'] ?? $spec['widgets'] ?? [];

		if ( ! empty( $elements ) ) {
			// Check if elements are columns or widgets directly.
			$first_element = reset( $elements );
			$is_columns = isset( $first_element['widgets'] ) || isset( $first_element['elements'] ) ||
			              ( isset( $first_element['type'] ) && 'column' === strtolower( $first_element['type'] ) );

			if ( $is_columns ) {
				$section['elements'] = $this->build_columns_from_freeform( $elements );
			} else {
				// Single column with widgets.
				$column = ElementorSchemaLearner::get_column_template( 100 );
				$column['elements'] = $this->convert_freeform_elements( $elements );
				$section['elements'][] = $column;
			}
		}

		return $section;
	}

	/**
	 * Build columns from freeform specification
	 *
	 * @param array $columns_spec Columns specification.
	 * @return array Elementor-compatible columns array.
	 */
	private function build_columns_from_freeform( array $columns_spec ): array {
		$columns = [];
		$column_count = count( $columns_spec );
		$default_width = $column_count > 0 ? (int) floor( 100 / $column_count ) : 100;

		foreach ( $columns_spec as $col_spec ) {
			$width = $col_spec['width'] ?? $col_spec['size'] ?? $default_width;

			$column_options = [
				'background_color' => $col_spec['background_color'] ?? $col_spec['bg_color'] ?? '',
				'padding'          => $this->normalize_padding( $col_spec['padding'] ?? [] ),
				'vertical_align'   => $col_spec['vertical_align'] ?? $col_spec['align_v'] ?? 'top',
			];

			$column = ElementorSchemaLearner::get_column_template( $width, $column_options );

			// Get widgets from column.
			$widgets = $col_spec['widgets'] ?? $col_spec['elements'] ?? $col_spec['content'] ?? [];
			if ( ! empty( $widgets ) ) {
				$column['elements'] = $this->convert_freeform_elements( $widgets );
			}

			$columns[] = $column;
		}

		return $columns;
	}

	/**
	 * Convert freeform elements/widgets to Elementor widgets
	 *
	 * @param array $elements Elements specification.
	 * @return array Elementor widgets array.
	 */
	private function convert_freeform_elements( array $elements ): array {
		$widgets = [];

		foreach ( $elements as $element ) {
			$widget = $this->convert_single_element( $element );
			if ( $widget ) {
				$widgets[] = $widget;
			}
		}

		return $widgets;
	}

	/**
	 * Convert a single freeform element to Elementor widget
	 *
	 * Includes fallback logic for unknown widget types.
	 *
	 * @param array $element Element specification.
	 * @return array|null Elementor widget or null if cannot convert.
	 */
	private function convert_single_element( array $element ): ?array {
		$type = strtolower( $element['type'] ?? $element['widget'] ?? 'text' );

		// Map to supported widget type.
		$mapped_type = $this->supported_widgets[ $type ] ?? null;

		// Fallback for unknown types - track for summary.
		if ( ! $mapped_type ) {
			$this->fallback_count++;
			if ( ! in_array( $type, $this->unknown_widget_types, true ) ) {
				$this->unknown_widget_types[] = $type;
			}
			$this->log( "Fallback #{$this->fallback_count}: Unknown widget type '{$type}' → text-editor", 'warning' );
			$mapped_type = 'text-editor';
		}

		switch ( $mapped_type ) {
			case 'heading':
				return ElementorSchemaLearner::get_heading_widget(
					$element['text'] ?? $element['title'] ?? $element['content'] ?? 'Heading',
					$element['level'] ?? $element['tag'] ?? 'h2',
					[
						'color'       => $element['color'] ?? $element['text_color'] ?? '',
						'align'       => $element['align'] ?? $element['alignment'] ?? 'left',
						'font_size'   => $element['font_size'] ?? $element['size'] ?? 0,
						'font_weight' => $element['font_weight'] ?? $element['weight'] ?? '',
					]
				);

			case 'text-editor':
				$content = $element['text'] ?? $element['content'] ?? $element['html'] ?? '';
				// Wrap in paragraph if plain text.
				if ( strpos( $content, '<' ) === false ) {
					$content = '<p>' . nl2br( esc_html( $content ) ) . '</p>';
				}
				return ElementorSchemaLearner::get_text_widget( $content, [
					'color'     => $element['color'] ?? $element['text_color'] ?? '',
					'align'     => $element['align'] ?? 'left',
					'font_size' => $element['font_size'] ?? 16,
				] );

			case 'button':
				return ElementorSchemaLearner::get_button_widget(
					$element['text'] ?? $element['label'] ?? 'Click Here',
					$element['url'] ?? $element['link'] ?? $element['href'] ?? '#',
					[
						'bg_color'      => $element['bg_color'] ?? $element['background_color'] ?? $element['color'] ?? '#2563EB',
						'text_color'    => $element['text_color'] ?? $element['label_color'] ?? '#ffffff',
						'align'         => $element['align'] ?? 'left',
						'size'          => $element['size'] ?? 'md',
						'border_radius' => $element['border_radius'] ?? $element['radius'] ?? 4,
					]
				);

			case 'image':
				return ElementorSchemaLearner::get_image_widget(
					$element['url'] ?? $element['src'] ?? $element['image'] ?? '',
					[
						'alt'        => $element['alt'] ?? $element['alt_text'] ?? '',
						'caption'    => $element['caption'] ?? '',
						'align'      => $element['align'] ?? 'center',
						'width'      => $element['width'] ?? 100,
						'width_unit' => $element['width_unit'] ?? '%',
					]
				);

			case 'spacer':
				return ElementorSchemaLearner::get_spacer_widget(
					$element['height'] ?? $element['size'] ?? $element['space'] ?? 50
				);

			case 'divider':
				return ElementorSchemaLearner::get_divider_widget( [
					'style'  => $element['style'] ?? 'solid',
					'weight' => $element['weight'] ?? $element['thickness'] ?? 1,
					'color'  => $element['color'] ?? '#e0e0e0',
					'width'  => $element['width'] ?? 100,
				] );

			case 'icon':
				return ElementorSchemaLearner::get_icon_widget(
					$element['icon'] ?? $element['class'] ?? 'fas fa-star',
					[
						'color' => $element['color'] ?? '#2563EB',
						'size'  => $element['size'] ?? 50,
						'align' => $element['align'] ?? 'center',
					]
				);

			case 'icon-box':
				return ElementorSchemaLearner::get_icon_box_widget(
					$element['icon'] ?? 'fas fa-check',
					$element['title'] ?? $element['heading'] ?? 'Feature',
					$element['description'] ?? $element['text'] ?? '',
					[
						'icon_color' => $element['icon_color'] ?? $element['color'] ?? '#2563EB',
						'position'   => $element['position'] ?? $element['icon_position'] ?? 'top',
					]
				);

			case 'video':
				return $this->get_video_widget( $element );

			case 'html':
				return $this->get_html_widget( $element );

			case 'shortcode':
				return $this->get_shortcode_widget( $element );

			default:
				// Ultimate fallback - convert to text.
				$this->log( "No handler for widget type '{$mapped_type}', using text fallback", 'warning' );
				return ElementorSchemaLearner::get_text_widget(
					'<p>' . esc_html( wp_json_encode( $element ) ) . '</p>',
					[ 'color' => '#666666' ]
				);
		}
	}

	/**
	 * Get video widget template
	 *
	 * @param array $element Element specification.
	 * @return array Widget structure.
	 */
	private function get_video_widget( array $element ): array {
		return [
			'id'         => ElementorSchemaLearner::generate_id(),
			'elType'     => 'widget',
			'widgetType' => 'video',
			'settings'   => [
				'video_type'   => $element['video_type'] ?? 'youtube',
				'youtube_url'  => $element['url'] ?? $element['youtube_url'] ?? '',
				'vimeo_url'    => $element['vimeo_url'] ?? '',
				'autoplay'     => $element['autoplay'] ?? 'no',
				'mute'         => $element['mute'] ?? 'no',
				'loop'         => $element['loop'] ?? 'no',
				'controls'     => $element['controls'] ?? 'yes',
				'aspect_ratio' => $element['aspect_ratio'] ?? '169',
			],
		];
	}

	/**
	 * Get HTML widget template
	 *
	 * @param array $element Element specification.
	 * @return array Widget structure.
	 */
	private function get_html_widget( array $element ): array {
		return [
			'id'         => ElementorSchemaLearner::generate_id(),
			'elType'     => 'widget',
			'widgetType' => 'html',
			'settings'   => [
				'html' => $element['html'] ?? $element['content'] ?? $element['code'] ?? '',
			],
		];
	}

	/**
	 * Get shortcode widget template
	 *
	 * @param array $element Element specification.
	 * @return array Widget structure.
	 */
	private function get_shortcode_widget( array $element ): array {
		return [
			'id'         => ElementorSchemaLearner::generate_id(),
			'elType'     => 'widget',
			'widgetType' => 'shortcode',
			'settings'   => [
				'shortcode' => $element['shortcode'] ?? $element['code'] ?? $element['content'] ?? '',
			],
		];
	}

	/**
	 * Validate Elementor JSON structure before page creation
	 *
	 * @param array $sections Elementor sections array.
	 * @return array Validation result with 'valid' boolean and 'errors' array.
	 */
	public function validate_elementor_json( array $sections ): array {
		$errors = [];

		if ( empty( $sections ) ) {
			$errors[] = 'No sections provided';
			return [ 'valid' => false, 'errors' => $errors ];
		}

		foreach ( $sections as $index => $section ) {
			$section_errors = $this->validate_section( $section, $index );
			$errors = array_merge( $errors, $section_errors );
		}

		return [
			'valid'  => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Validate a single section
	 *
	 * @param array $section Section to validate.
	 * @param int   $index   Section index.
	 * @return array Array of error messages.
	 */
	private function validate_section( array $section, int $index ): array {
		$errors = [];
		$prefix = "Section {$index}";

		// Required fields.
		if ( ! isset( $section['id'] ) || empty( $section['id'] ) ) {
			$errors[] = "{$prefix}: Missing 'id'";
		}

		if ( ! isset( $section['elType'] ) || 'section' !== $section['elType'] ) {
			$errors[] = "{$prefix}: Invalid 'elType' (expected 'section')";
		}

		if ( ! isset( $section['settings'] ) || ! is_array( $section['settings'] ) ) {
			$errors[] = "{$prefix}: Missing or invalid 'settings'";
		}

		if ( ! isset( $section['elements'] ) || ! is_array( $section['elements'] ) ) {
			$errors[] = "{$prefix}: Missing or invalid 'elements'";
		} else {
			// Validate columns.
			foreach ( $section['elements'] as $col_index => $column ) {
				$col_errors = $this->validate_column( $column, $index, $col_index );
				$errors = array_merge( $errors, $col_errors );
			}
		}

		return $errors;
	}

	/**
	 * Validate a column
	 *
	 * @param array $column       Column to validate.
	 * @param int   $section_idx  Section index.
	 * @param int   $column_idx   Column index.
	 * @return array Array of error messages.
	 */
	private function validate_column( array $column, int $section_idx, int $column_idx ): array {
		$errors = [];
		$prefix = "Section {$section_idx}, Column {$column_idx}";

		if ( ! isset( $column['id'] ) || empty( $column['id'] ) ) {
			$errors[] = "{$prefix}: Missing 'id'";
		}

		if ( ! isset( $column['elType'] ) || 'column' !== $column['elType'] ) {
			$errors[] = "{$prefix}: Invalid 'elType' (expected 'column')";
		}

		if ( ! isset( $column['settings'] ) || ! is_array( $column['settings'] ) ) {
			$errors[] = "{$prefix}: Missing or invalid 'settings'";
		}

		if ( isset( $column['elements'] ) && is_array( $column['elements'] ) ) {
			foreach ( $column['elements'] as $widget_idx => $widget ) {
				$widget_errors = $this->validate_widget( $widget, $section_idx, $column_idx, $widget_idx );
				$errors = array_merge( $errors, $widget_errors );
			}
		}

		return $errors;
	}

	/**
	 * Validate a widget
	 *
	 * @param array $widget      Widget to validate.
	 * @param int   $section_idx Section index.
	 * @param int   $column_idx  Column index.
	 * @param int   $widget_idx  Widget index.
	 * @return array Array of error messages.
	 */
	private function validate_widget( array $widget, int $section_idx, int $column_idx, int $widget_idx ): array {
		$errors = [];
		$prefix = "Section {$section_idx}, Column {$column_idx}, Widget {$widget_idx}";

		if ( ! isset( $widget['id'] ) || empty( $widget['id'] ) ) {
			$errors[] = "{$prefix}: Missing 'id'";
		}

		if ( ! isset( $widget['elType'] ) || 'widget' !== $widget['elType'] ) {
			$errors[] = "{$prefix}: Invalid 'elType' (expected 'widget')";
		}

		if ( ! isset( $widget['widgetType'] ) || empty( $widget['widgetType'] ) ) {
			$errors[] = "{$prefix}: Missing 'widgetType'";
		}

		if ( ! isset( $widget['settings'] ) || ! is_array( $widget['settings'] ) ) {
			$errors[] = "{$prefix}: Missing or invalid 'settings'";
		}

		return $errors;
	}

	/**
	 * Validate freeform specification
	 *
	 * Validates the AI-generated specification BEFORE any conversion.
	 * Catches errors early to provide meaningful feedback.
	 *
	 * @param array $spec Page specification.
	 * @throws \Exception On validation failure.
	 */
	private function validate_freeform_spec( array $spec ): void {
		// Title validation.
		if ( empty( $spec['title'] ) || ! is_string( $spec['title'] ) ) {
			throw new \Exception( 'Page title is required and must be a string' );
		}

		if ( strlen( $spec['title'] ) > 200 ) {
			throw new \Exception( 'Page title is too long (max 200 characters)' );
		}

		// Sections validation.
		$sections = $spec['sections'] ?? $spec['layout'] ?? $spec['content'] ?? [];
		if ( empty( $sections ) || ! is_array( $sections ) ) {
			throw new \Exception( 'At least one section is required' );
		}

		if ( count( $sections ) > 20 ) {
			throw new \Exception( 'Maximum 20 sections allowed per page for performance' );
		}

		// Warn for many sections (performance).
		if ( count( $sections ) > 5 ) {
			$this->log( 'Performance warning: ' . count( $sections ) . ' sections may slow page load', 'warning' );
		}

		// Validate each section has content.
		foreach ( $sections as $idx => $section ) {
			if ( ! is_array( $section ) ) {
				throw new \Exception( "Section {$idx}: must be an array" );
			}

			// Check section has some content (columns, elements, widgets, or is a typed section).
			$has_content = ! empty( $section['columns'] ) ||
			               ! empty( $section['elements'] ) ||
			               ! empty( $section['widgets'] ) ||
			               ! empty( $section['content'] ) ||
			               ! empty( $section['type'] ); // typed sections (hero, features, cta) are self-contained

			if ( ! $has_content ) {
				throw new \Exception( "Section {$idx}: must have columns, elements, widgets, or a type (hero/features/cta)" );
			}

			// Validate typed sections have required fields.
			if ( ! empty( $section['type'] ) ) {
				$this->validate_typed_section( $section, $idx );
			}
		}

		// Validate SEO if provided.
		if ( ! empty( $spec['seo'] ) && ! is_array( $spec['seo'] ) ) {
			throw new \Exception( 'SEO configuration must be an array' );
		}
	}

	/**
	 * Validate typed section (hero, features, cta)
	 *
	 * @param array $section Section specification.
	 * @param int   $idx     Section index.
	 * @throws \Exception On validation failure.
	 */
	private function validate_typed_section( array $section, int $idx ): void {
		$type = strtolower( $section['type'] );

		switch ( $type ) {
			case 'hero':
				// Hero should have at least a heading.
				if ( empty( $section['heading'] ) && empty( $section['title'] ) ) {
					$this->log( "Section {$idx} (hero): No heading provided, will use default", 'warning' );
				}
				break;

			case 'features':
			case 'feature-grid':
			case 'services':
				// Features should have features array.
				if ( empty( $section['features'] ) && empty( $section['items'] ) ) {
					$this->log( "Section {$idx} (features): No features array provided", 'warning' );
				}
				break;

			case 'cta':
			case 'call-to-action':
				// CTA should have button text.
				if ( empty( $section['cta_text'] ) && empty( $section['button_text'] ) ) {
					$this->log( "Section {$idx} (cta): No button text provided", 'warning' );
				}
				break;

			case 'custom':
				// Custom sections validated by general content check.
				break;

			default:
				// Unknown type - warn but don't fail (will be treated as custom).
				$this->log( "Section {$idx}: Unknown type '{$type}', treating as custom", 'warning' );
		}
	}

	/**
	 * Generate page using legacy template-based approach
	 *
	 * Kept for backwards compatibility.
	 *
	 * @param array $spec Page specification.
	 * @return array Result with page_id, url, edit_url.
	 * @throws \Exception On validation or creation failure.
	 */
	public function generate_page( array $spec ): array {
		// Route to freeform method for consistency.
		return $this->generate_page_from_freeform_spec( $spec );
	}

	/**
	 * Create WordPress page with Elementor data
	 *
	 * @param array  $spec           Page specification.
	 * @param string $elementor_data JSON-encoded Elementor data.
	 * @return int Page ID.
	 * @throws \Exception On page creation failure.
	 */
	private function create_page( array $spec, string $elementor_data ): int {
		$page_args = [
			'post_type'    => 'page',
			'post_title'   => $spec['title'] ?? 'New Page',
			'post_status'  => $spec['status'] ?? 'draft',
			'post_content' => '',
			'post_excerpt' => $spec['excerpt'] ?? '',
		];

		// Set parent page if specified.
		if ( ! empty( $spec['parent_id'] ) ) {
			$page_args['post_parent'] = absint( $spec['parent_id'] );
		}

		// Set page template if specified.
		if ( ! empty( $spec['template'] ) ) {
			$page_args['page_template'] = $spec['template'];
		}

		$page_id = wp_insert_post( $page_args, true );

		if ( is_wp_error( $page_id ) ) {
			throw new \Exception( 'Failed to create page: ' . $page_id->get_error_message() );
		}

		// Save Elementor data.
		update_post_meta( $page_id, '_elementor_data', $elementor_data );
		update_post_meta( $page_id, '_elementor_version', $this->elementor_version );
		update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );

		// Set Elementor page template for proper rendering.
		update_post_meta( $page_id, '_wp_page_template', $spec['template'] ?? 'elementor_canvas' );

		return $page_id;
	}

	/**
	 * Add SEO metadata with cascade: RankMath -> Yoast -> Basic meta
	 *
	 * @param int   $page_id Page ID.
	 * @param array $seo     SEO configuration.
	 * @return array Result with 'provider' indicating which method was used.
	 */
	private function add_seo_metadata_cascade( int $page_id, array $seo ): array {
		// Try RankMath first.
		if ( class_exists( '\RankMath' ) || function_exists( 'rank_math' ) ) {
			$this->add_rankmath_metadata( $page_id, $seo );
			return [ 'provider' => 'RankMath' ];
		}

		// Try Yoast SEO.
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Meta' ) ) {
			$this->add_yoast_metadata( $page_id, $seo );
			return [ 'provider' => 'Yoast SEO' ];
		}

		// Fallback to basic WordPress meta.
		$this->add_basic_seo_metadata( $page_id, $seo );
		return [ 'provider' => 'Basic Meta' ];
	}

	/**
	 * Add RankMath SEO metadata
	 *
	 * @param int   $page_id Page ID.
	 * @param array $seo     SEO configuration.
	 */
	private function add_rankmath_metadata( int $page_id, array $seo ): void {
		if ( ! empty( $seo['title'] ) ) {
			update_post_meta( $page_id, 'rank_math_title', $seo['title'] );
		}
		if ( ! empty( $seo['description'] ) ) {
			update_post_meta( $page_id, 'rank_math_description', $seo['description'] );
		}
		if ( ! empty( $seo['focus_keyword'] ) ) {
			update_post_meta( $page_id, 'rank_math_focus_keyword', $seo['focus_keyword'] );
		}
		if ( ! empty( $seo['robots'] ) ) {
			update_post_meta( $page_id, 'rank_math_robots', $seo['robots'] );
		}
		// Open Graph.
		if ( ! empty( $seo['og_title'] ) ) {
			update_post_meta( $page_id, 'rank_math_facebook_title', $seo['og_title'] );
		}
		if ( ! empty( $seo['og_description'] ) ) {
			update_post_meta( $page_id, 'rank_math_facebook_description', $seo['og_description'] );
		}
		// Twitter.
		if ( ! empty( $seo['twitter_title'] ) ) {
			update_post_meta( $page_id, 'rank_math_twitter_title', $seo['twitter_title'] );
		}
		if ( ! empty( $seo['twitter_description'] ) ) {
			update_post_meta( $page_id, 'rank_math_twitter_description', $seo['twitter_description'] );
		}
	}

	/**
	 * Add Yoast SEO metadata
	 *
	 * @param int   $page_id Page ID.
	 * @param array $seo     SEO configuration.
	 */
	private function add_yoast_metadata( int $page_id, array $seo ): void {
		if ( ! empty( $seo['title'] ) ) {
			update_post_meta( $page_id, '_yoast_wpseo_title', $seo['title'] );
		}
		if ( ! empty( $seo['description'] ) ) {
			update_post_meta( $page_id, '_yoast_wpseo_metadesc', $seo['description'] );
		}
		if ( ! empty( $seo['focus_keyword'] ) ) {
			update_post_meta( $page_id, '_yoast_wpseo_focuskw', $seo['focus_keyword'] );
		}
		// Canonical URL.
		if ( ! empty( $seo['canonical'] ) ) {
			update_post_meta( $page_id, '_yoast_wpseo_canonical', $seo['canonical'] );
		}
		// Open Graph.
		if ( ! empty( $seo['og_title'] ) ) {
			update_post_meta( $page_id, '_yoast_wpseo_opengraph-title', $seo['og_title'] );
		}
		if ( ! empty( $seo['og_description'] ) ) {
			update_post_meta( $page_id, '_yoast_wpseo_opengraph-description', $seo['og_description'] );
		}
		// Twitter.
		if ( ! empty( $seo['twitter_title'] ) ) {
			update_post_meta( $page_id, '_yoast_wpseo_twitter-title', $seo['twitter_title'] );
		}
		if ( ! empty( $seo['twitter_description'] ) ) {
			update_post_meta( $page_id, '_yoast_wpseo_twitter-description', $seo['twitter_description'] );
		}
	}

	/**
	 * Add basic SEO metadata (no plugin)
	 *
	 * @param int   $page_id Page ID.
	 * @param array $seo     SEO configuration.
	 */
	private function add_basic_seo_metadata( int $page_id, array $seo ): void {
		// Store in custom meta that can be used by theme or custom code.
		if ( ! empty( $seo['title'] ) ) {
			update_post_meta( $page_id, '_creator_seo_title', $seo['title'] );
		}
		if ( ! empty( $seo['description'] ) ) {
			update_post_meta( $page_id, '_creator_seo_description', $seo['description'] );
		}
		if ( ! empty( $seo['focus_keyword'] ) ) {
			update_post_meta( $page_id, '_creator_seo_keyword', $seo['focus_keyword'] );
		}

		// Log warning about missing SEO plugin.
		$this->log( 'No SEO plugin detected. Basic meta stored in _creator_seo_* fields.', 'warning' );
	}

	/**
	 * Set featured image from URL or attachment ID
	 *
	 * @param int        $page_id   Page ID.
	 * @param string|int $image     Image URL or attachment ID.
	 */
	private function set_featured_image( int $page_id, $image ): void {
		// If it's an attachment ID.
		if ( is_numeric( $image ) ) {
			set_post_thumbnail( $page_id, absint( $image ) );
			return;
		}

		// If it's a URL, try to find existing attachment.
		global $wpdb;
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment' LIMIT 1",
				$image
			)
		);

		if ( $attachment_id ) {
			set_post_thumbnail( $page_id, $attachment_id );
		} else {
			$this->log( 'Featured image not found in media library: ' . $image, 'warning' );
		}
	}

	/**
	 * Verify page rendering
	 *
	 * @param int $page_id Page ID.
	 * @return bool True if rendering appears valid.
	 */
	private function verify_rendering( int $page_id ): bool {
		$elementor_data = get_post_meta( $page_id, '_elementor_data', true );

		if ( empty( $elementor_data ) ) {
			return false;
		}

		$data = json_decode( $elementor_data, true );

		if ( ! is_array( $data ) || empty( $data ) ) {
			return false;
		}

		// Check that each section has the required structure.
		foreach ( $data as $section ) {
			if ( ! isset( $section['elType'] ) || 'section' !== $section['elType'] ) {
				return false;
			}
			if ( ! isset( $section['elements'] ) || ! is_array( $section['elements'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get Elementor edit URL
	 *
	 * @param int $page_id Page ID.
	 * @return string Edit URL.
	 */
	private function get_elementor_edit_url( int $page_id ): string {
		return add_query_arg(
			[
				'post'   => $page_id,
				'action' => 'elementor',
			],
			admin_url( 'post.php' )
		);
	}

	/**
	 * Clear Elementor cache
	 */
	private function clear_elementor_cache(): void {
		if ( class_exists( '\Elementor\Plugin' ) && method_exists( \Elementor\Plugin::$instance, 'files_manager' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	/**
	 * Create snapshot for undo capability
	 *
	 * @param string $action Action description.
	 * @param array  $data   Additional data.
	 * @return int|null Snapshot ID or null.
	 */
	private function create_snapshot( string $action, array $data ): ?int {
		if ( ! class_exists( '\CreatorCore\Snapshot\SnapshotManager' ) ) {
			return null;
		}

		try {
			$snapshot_manager = new \CreatorCore\Snapshot\SnapshotManager();
			return $snapshot_manager->create_snapshot( $action, $data );
		} catch ( \Exception $e ) {
			$this->log( 'Snapshot creation failed: ' . $e->getMessage(), 'warning' );
			return null;
		}
	}

	/**
	 * Update snapshot with additional data
	 *
	 * @param int   $snapshot_id Snapshot ID.
	 * @param array $data        Additional data.
	 */
	private function update_snapshot( int $snapshot_id, array $data ): void {
		if ( ! class_exists( '\CreatorCore\Snapshot\SnapshotManager' ) ) {
			return;
		}

		try {
			$snapshot_manager = new \CreatorCore\Snapshot\SnapshotManager();
			$snapshot_manager->update_snapshot( $snapshot_id, $data );
		} catch ( \Exception $e ) {
			$this->log( 'Snapshot update failed: ' . $e->getMessage(), 'warning' );
		}
	}

	/**
	 * Normalize padding from various input formats
	 *
	 * @param array|int|string $padding Padding specification.
	 * @return array Normalized padding array.
	 */
	private function normalize_padding( $padding ): array {
		$defaults = [ 'top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20 ];

		if ( empty( $padding ) ) {
			return $defaults;
		}

		// Single value for all sides.
		if ( is_numeric( $padding ) ) {
			return [
				'top'    => (int) $padding,
				'right'  => (int) $padding,
				'bottom' => (int) $padding,
				'left'   => (int) $padding,
			];
		}

		// Array with named keys.
		if ( is_array( $padding ) ) {
			return array_merge( $defaults, $padding );
		}

		return $defaults;
	}

	/**
	 * Log a message
	 *
	 * @param string $message Message to log.
	 * @param string $level   Log level (info, debug, warning, error, success).
	 */
	private function log( string $message, string $level = 'info' ): void {
		if ( $this->logger ) {
			$this->logger->log( '[Elementor] ' . $message, $level );
		}
	}

	/**
	 * Get Elementor version
	 *
	 * @return string
	 */
	public function get_elementor_version(): string {
		return $this->elementor_version;
	}

	/**
	 * Check if Elementor Pro is available
	 *
	 * @return bool
	 */
	public function has_pro(): bool {
		return $this->is_pro;
	}

	/**
	 * Get available breakpoints
	 *
	 * @return array
	 */
	public function get_breakpoints(): array {
		return $this->breakpoints;
	}

	/**
	 * Get supported widget types
	 *
	 * @return array
	 */
	public function get_supported_widgets(): array {
		return array_unique( array_values( $this->supported_widgets ) );
	}

	/**
	 * Get available page templates
	 *
	 * @return array Template options.
	 */
	public static function get_available_templates(): array {
		return [
			'default'                 => 'Default Template',
			'elementor_canvas'        => 'Elementor Canvas (Full Width)',
			'elementor_header_footer' => 'Elementor Header & Footer',
		];
	}

	/**
	 * Get builder status information
	 *
	 * @return array Status information.
	 */
	public function get_status(): array {
		return [
			'elementor_version'   => $this->elementor_version,
			'is_pro'              => $this->is_pro,
			'breakpoints'         => array_keys( $this->breakpoints ),
			'supported_widgets'   => $this->get_supported_widgets(),
			'available_templates' => self::get_available_templates(),
			'seo_providers'       => $this->get_available_seo_providers(),
		];
	}

	/**
	 * Get available SEO providers
	 *
	 * @return array
	 */
	private function get_available_seo_providers(): array {
		$providers = [];

		if ( class_exists( '\RankMath' ) || function_exists( 'rank_math' ) ) {
			$providers[] = 'RankMath';
		}

		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Meta' ) ) {
			$providers[] = 'Yoast SEO';
		}

		if ( empty( $providers ) ) {
			$providers[] = 'Basic Meta (no plugin)';
		}

		return $providers;
	}

	// =========================================================================
	// FALLBACK TRACKING
	// =========================================================================

	/**
	 * Reset fallback tracking for a new generation
	 *
	 * @return void
	 */
	private function reset_fallback_tracking(): void {
		$this->fallback_count = 0;
		$this->unknown_widget_types = [];
	}

	/**
	 * Log fallback summary if any unknown widgets were encountered
	 *
	 * @return void
	 */
	private function log_fallback_summary(): void {
		if ( $this->fallback_count === 0 ) {
			return;
		}

		$types_list = implode( ', ', $this->unknown_widget_types );
		$this->log(
			"Widget fallback summary: {$this->fallback_count} widget(s) converted to text-editor. Unknown types: {$types_list}",
			'warning'
		);
	}

	/**
	 * Get the number of fallbacks that occurred in the last generation
	 *
	 * @return int
	 */
	public function get_fallback_count(): int {
		return $this->fallback_count;
	}

	/**
	 * Get the list of unknown widget types encountered in the last generation
	 *
	 * @return array
	 */
	public function get_unknown_widget_types(): array {
		return $this->unknown_widget_types;
	}

	/**
	 * Count total widgets in Elementor sections
	 *
	 * Recursively counts all widgets in the structure.
	 *
	 * @param array $sections Elementor sections array.
	 * @return int Total widget count.
	 */
	private function count_total_widgets( array $sections ): int {
		$count = 0;

		foreach ( $sections as $section ) {
			$count += $this->count_widgets_in_element( $section );
		}

		return $count;
	}

	/**
	 * Recursively count widgets in an element
	 *
	 * @param array $element Element to count widgets in.
	 * @return int Widget count.
	 */
	private function count_widgets_in_element( array $element ): int {
		$count = 0;

		// If this is a widget, count it.
		if ( ( $element['elType'] ?? '' ) === 'widget' ) {
			$count++;
		}

		// Recursively count in child elements.
		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			foreach ( $element['elements'] as $child ) {
				$count += $this->count_widgets_in_element( $child );
			}
		}

		return $count;
	}
}

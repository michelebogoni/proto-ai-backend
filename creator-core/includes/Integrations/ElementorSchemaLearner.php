<?php
/**
 * Elementor Schema Learner
 *
 * Provides JSON structure templates for Elementor sections, columns, and widgets.
 * Used by ElementorPageBuilder to generate production-ready Elementor pages.
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class ElementorSchemaLearner
 *
 * Static utility class that provides JSON templates for Elementor elements.
 * Templates follow Elementor's native JSON structure for maximum compatibility.
 */
class ElementorSchemaLearner {

	/**
	 * Generate unique element ID
	 *
	 * @return string 8-character alphanumeric ID.
	 */
	public static function generate_id(): string {
		return substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyz0123456789' ), 0, 8 );
	}

	/**
	 * Get base section template
	 *
	 * @param array $options Optional section options.
	 * @return array Section structure.
	 */
	public static function get_section_template( array $options = [] ): array {
		$defaults = [
			'background_color' => '',
			'background_image' => '',
			'min_height'       => 0,
			'padding'          => [ 'top' => 50, 'right' => 0, 'bottom' => 50, 'left' => 0 ],
			'margin'           => [ 'top' => 0, 'bottom' => 0 ],
			'content_width'    => 'boxed',
			'stretch_section'  => '',
		];

		$opts = array_merge( $defaults, $options );

		$settings = [
			'layout'           => 'boxed',
			'content_width'    => [ 'size' => 1140, 'unit' => 'px' ],
			'gap'              => 'default',
			'height'           => 'default',
			'column_position'  => 'middle',
			'padding'          => [
				'unit'   => 'px',
				'top'    => (string) $opts['padding']['top'],
				'right'  => (string) $opts['padding']['right'],
				'bottom' => (string) $opts['padding']['bottom'],
				'left'   => (string) $opts['padding']['left'],
				'isLinked' => false,
			],
			'margin'           => [
				'unit'   => 'px',
				'top'    => (string) $opts['margin']['top'],
				'bottom' => (string) $opts['margin']['bottom'],
				'isLinked' => false,
			],
		];

		// Background color.
		if ( ! empty( $opts['background_color'] ) ) {
			$settings['background_background'] = 'classic';
			$settings['background_color']      = $opts['background_color'];
		}

		// Background image.
		if ( ! empty( $opts['background_image'] ) ) {
			$settings['background_background'] = 'classic';
			$settings['background_image']      = [
				'url' => $opts['background_image'],
				'id'  => '',
			];
			$settings['background_position']   = 'center center';
			$settings['background_size']       = 'cover';
		}

		// Minimum height.
		if ( $opts['min_height'] > 0 ) {
			$settings['height']     = 'min-height';
			$settings['min_height'] = [
				'unit' => 'px',
				'size' => $opts['min_height'],
			];
			// Responsive heights.
			$settings['min_height_tablet'] = [
				'unit' => 'px',
				'size' => round( $opts['min_height'] * 0.75 ),
			];
			$settings['min_height_mobile'] = [
				'unit' => 'px',
				'size' => round( $opts['min_height'] * 0.5 ),
			];
		}

		// Full width stretch.
		if ( ! empty( $opts['stretch_section'] ) ) {
			$settings['stretch_section'] = 'section-stretched';
		}

		return [
			'id'       => self::generate_id(),
			'elType'   => 'section',
			'settings' => $settings,
			'elements' => [],
		];
	}

	/**
	 * Get base column template
	 *
	 * @param int   $width   Column width percentage (100, 50, 33, 25, etc.).
	 * @param array $options Optional column options.
	 * @return array Column structure.
	 */
	public static function get_column_template( int $width = 100, array $options = [] ): array {
		$defaults = [
			'background_color' => '',
			'padding'          => [ 'top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10 ],
			'vertical_align'   => 'top',
		];

		$opts = array_merge( $defaults, $options );

		$settings = [
			'_column_size' => $width,
			'_inline_size' => null,
			'padding'      => [
				'unit'     => 'px',
				'top'      => (string) $opts['padding']['top'],
				'right'    => (string) $opts['padding']['right'],
				'bottom'   => (string) $opts['padding']['bottom'],
				'left'     => (string) $opts['padding']['left'],
				'isLinked' => false,
			],
			'vertical_align' => $opts['vertical_align'],
		];

		// Background color.
		if ( ! empty( $opts['background_color'] ) ) {
			$settings['background_background'] = 'classic';
			$settings['background_color']      = $opts['background_color'];
		}

		return [
			'id'       => self::generate_id(),
			'elType'   => 'column',
			'settings' => $settings,
			'elements' => [],
		];
	}

	/**
	 * Get heading widget template
	 *
	 * @param string $text    Heading text.
	 * @param string $level   HTML tag (h1, h2, h3, h4, h5, h6).
	 * @param array  $options Optional styling options.
	 * @return array Widget structure.
	 */
	public static function get_heading_widget( string $text, string $level = 'h2', array $options = [] ): array {
		$defaults = [
			'color'       => '',
			'align'       => 'left',
			'font_size'   => 0,
			'font_weight' => '',
			'font_family' => '',
		];

		$opts = array_merge( $defaults, $options );

		// Default font sizes by heading level.
		$default_sizes = [
			'h1' => 48,
			'h2' => 36,
			'h3' => 28,
			'h4' => 24,
			'h5' => 20,
			'h6' => 16,
		];

		$font_size = $opts['font_size'] > 0 ? $opts['font_size'] : ( $default_sizes[ $level ] ?? 24 );

		$settings = [
			'title'        => $text,
			'header_size'  => $level,
			'align'        => $opts['align'],
			'title_color'  => $opts['color'] ?: '',
			'typography_typography' => 'custom',
			'typography_font_size'  => [
				'unit' => 'px',
				'size' => $font_size,
			],
			'typography_font_size_tablet' => [
				'unit' => 'px',
				'size' => round( $font_size * 0.85 ),
			],
			'typography_font_size_mobile' => [
				'unit' => 'px',
				'size' => round( $font_size * 0.7 ),
			],
			'typography_line_height' => [
				'unit' => 'em',
				'size' => 1.2,
			],
		];

		if ( ! empty( $opts['font_weight'] ) ) {
			$settings['typography_font_weight'] = $opts['font_weight'];
		}

		if ( ! empty( $opts['font_family'] ) ) {
			$settings['typography_font_family'] = $opts['font_family'];
		}

		return [
			'id'         => self::generate_id(),
			'elType'     => 'widget',
			'widgetType' => 'heading',
			'settings'   => $settings,
		];
	}

	/**
	 * Get text editor (paragraph) widget template
	 *
	 * @param string $content HTML content.
	 * @param array  $options Optional styling options.
	 * @return array Widget structure.
	 */
	public static function get_text_widget( string $content, array $options = [] ): array {
		$defaults = [
			'color'     => '',
			'align'     => 'left',
			'font_size' => 16,
		];

		$opts = array_merge( $defaults, $options );

		$settings = [
			'editor'     => $content,
			'align'      => $opts['align'],
			'text_color' => $opts['color'] ?: '',
			'typography_typography' => 'custom',
			'typography_font_size'  => [
				'unit' => 'px',
				'size' => $opts['font_size'],
			],
			'typography_line_height' => [
				'unit' => 'em',
				'size' => 1.6,
			],
		];

		return [
			'id'         => self::generate_id(),
			'elType'     => 'widget',
			'widgetType' => 'text-editor',
			'settings'   => $settings,
		];
	}

	/**
	 * Get button widget template
	 *
	 * @param string $text    Button text.
	 * @param string $url     Button URL.
	 * @param array  $options Optional styling options.
	 * @return array Widget structure.
	 */
	public static function get_button_widget( string $text, string $url = '#', array $options = [] ): array {
		$defaults = [
			'bg_color'      => '#2563EB',
			'text_color'    => '#ffffff',
			'align'         => 'left',
			'size'          => 'md',
			'border_radius' => 4,
			'is_external'   => false,
			'nofollow'      => false,
		];

		$opts = array_merge( $defaults, $options );

		$settings = [
			'text'             => $text,
			'link'             => [
				'url'         => $url,
				'is_external' => $opts['is_external'] ? 'on' : '',
				'nofollow'    => $opts['nofollow'] ? 'on' : '',
			],
			'align'            => $opts['align'],
			'size'             => $opts['size'],
			'button_type'      => 'default',
			'button_text_color' => $opts['text_color'],
			'background_color' => $opts['bg_color'],
			'border_radius'    => [
				'unit'     => 'px',
				'top'      => (string) $opts['border_radius'],
				'right'    => (string) $opts['border_radius'],
				'bottom'   => (string) $opts['border_radius'],
				'left'     => (string) $opts['border_radius'],
				'isLinked' => true,
			],
			'text_padding'     => [
				'unit'     => 'px',
				'top'      => '15',
				'right'    => '30',
				'bottom'   => '15',
				'left'     => '30',
				'isLinked' => false,
			],
			'typography_typography' => 'custom',
			'typography_font_size'  => [
				'unit' => 'px',
				'size' => 16,
			],
			'typography_font_weight' => '600',
		];

		// Hover styles.
		$settings['button_text_color_hover'] = $opts['text_color'];
		$settings['button_background_hover_color'] = self::adjust_brightness( $opts['bg_color'], -20 );

		return [
			'id'         => self::generate_id(),
			'elType'     => 'widget',
			'widgetType' => 'button',
			'settings'   => $settings,
		];
	}

	/**
	 * Get image widget template
	 *
	 * @param string $url     Image URL.
	 * @param array  $options Optional styling options.
	 * @return array Widget structure.
	 */
	public static function get_image_widget( string $url, array $options = [] ): array {
		$defaults = [
			'alt'        => '',
			'caption'    => '',
			'align'      => 'center',
			'width'      => 100,
			'width_unit' => '%',
			'link_to'    => 'none',
			'link_url'   => '',
		];

		$opts = array_merge( $defaults, $options );

		$settings = [
			'image'        => [
				'url' => $url,
				'id'  => '',
				'alt' => $opts['alt'],
			],
			'image_size'   => 'full',
			'align'        => $opts['align'],
			'caption'      => $opts['caption'],
			'link_to'      => $opts['link_to'],
			'width'        => [
				'unit' => $opts['width_unit'],
				'size' => $opts['width'],
			],
		];

		if ( 'custom' === $opts['link_to'] && ! empty( $opts['link_url'] ) ) {
			$settings['link'] = [
				'url'         => $opts['link_url'],
				'is_external' => '',
				'nofollow'    => '',
			];
		}

		return [
			'id'         => self::generate_id(),
			'elType'     => 'widget',
			'widgetType' => 'image',
			'settings'   => $settings,
		];
	}

	/**
	 * Get spacer widget template
	 *
	 * @param int $height Height in pixels.
	 * @return array Widget structure.
	 */
	public static function get_spacer_widget( int $height = 50 ): array {
		return [
			'id'         => self::generate_id(),
			'elType'     => 'widget',
			'widgetType' => 'spacer',
			'settings'   => [
				'space' => [
					'unit' => 'px',
					'size' => $height,
				],
				'space_tablet' => [
					'unit' => 'px',
					'size' => round( $height * 0.75 ),
				],
				'space_mobile' => [
					'unit' => 'px',
					'size' => round( $height * 0.5 ),
				],
			],
		];
	}

	/**
	 * Get divider widget template
	 *
	 * @param array $options Optional styling options.
	 * @return array Widget structure.
	 */
	public static function get_divider_widget( array $options = [] ): array {
		$defaults = [
			'style'  => 'solid',
			'weight' => 1,
			'color'  => '#e0e0e0',
			'width'  => 100,
			'align'  => 'center',
		];

		$opts = array_merge( $defaults, $options );

		return [
			'id'         => self::generate_id(),
			'elType'     => 'widget',
			'widgetType' => 'divider',
			'settings'   => [
				'style'  => $opts['style'],
				'weight' => [
					'unit' => 'px',
					'size' => $opts['weight'],
				],
				'color'  => $opts['color'],
				'width'  => [
					'unit' => '%',
					'size' => $opts['width'],
				],
				'align'  => $opts['align'],
				'gap'    => [
					'unit' => 'px',
					'size' => 15,
				],
			],
		];
	}

	/**
	 * Get icon widget template
	 *
	 * @param string $icon    Icon class (e.g., 'fas fa-star').
	 * @param array  $options Optional styling options.
	 * @return array Widget structure.
	 */
	public static function get_icon_widget( string $icon, array $options = [] ): array {
		$defaults = [
			'color'   => '#2563EB',
			'size'    => 50,
			'align'   => 'center',
			'view'    => 'default',
			'shape'   => 'circle',
			'link_to' => '',
		];

		$opts = array_merge( $defaults, $options );

		$settings = [
			'selected_icon' => [
				'value'   => $icon,
				'library' => 'fa-solid',
			],
			'view'          => $opts['view'],
			'shape'         => $opts['shape'],
			'align'         => $opts['align'],
			'primary_color' => $opts['color'],
			'icon_size'     => [
				'unit' => 'px',
				'size' => $opts['size'],
			],
		];

		if ( ! empty( $opts['link_to'] ) ) {
			$settings['link'] = [
				'url'         => $opts['link_to'],
				'is_external' => '',
				'nofollow'    => '',
			];
		}

		return [
			'id'         => self::generate_id(),
			'elType'     => 'widget',
			'widgetType' => 'icon',
			'settings'   => $settings,
		];
	}

	/**
	 * Get icon box widget template (icon + heading + text)
	 *
	 * @param string $icon    Icon class.
	 * @param string $title   Title text.
	 * @param string $text    Description text.
	 * @param array  $options Optional styling options.
	 * @return array Widget structure.
	 */
	public static function get_icon_box_widget( string $icon, string $title, string $text = '', array $options = [] ): array {
		$defaults = [
			'icon_color'  => '#2563EB',
			'title_color' => '#1a1a1a',
			'text_color'  => '#666666',
			'icon_size'   => 50,
			'position'    => 'top',
			'link_to'     => '',
		];

		$opts = array_merge( $defaults, $options );

		$settings = [
			'selected_icon' => [
				'value'   => $icon,
				'library' => 'fa-solid',
			],
			'title_text'        => $title,
			'description_text'  => $text,
			'position'          => $opts['position'],
			'primary_color'     => $opts['icon_color'],
			'title_color'       => $opts['title_color'],
			'description_color' => $opts['text_color'],
			'icon_size'         => [
				'unit' => 'px',
				'size' => $opts['icon_size'],
			],
			'title_typography_typography' => 'custom',
			'title_typography_font_size'  => [
				'unit' => 'px',
				'size' => 20,
			],
			'title_typography_font_weight' => '600',
		];

		if ( ! empty( $opts['link_to'] ) ) {
			$settings['link'] = [
				'url'         => $opts['link_to'],
				'is_external' => '',
				'nofollow'    => '',
			];
		}

		return [
			'id'         => self::generate_id(),
			'elType'     => 'widget',
			'widgetType' => 'icon-box',
			'settings'   => $settings,
		];
	}

	/**
	 * Build a complete hero section
	 *
	 * @param array $config Hero configuration.
	 * @return array Complete section with elements.
	 */
	public static function build_hero_section( array $config ): array {
		$defaults = [
			'heading'          => 'Welcome',
			'subheading'       => '',
			'cta_text'         => '',
			'cta_url'          => '#',
			'background_color' => '#1a1a2e',
			'text_color'       => '#ffffff',
			'cta_bg_color'     => '#2563EB',
			'min_height'       => 500,
			'align'            => 'center',
		];

		$opts = array_merge( $defaults, $config );

		// Create section.
		$section = self::get_section_template( [
			'background_color' => $opts['background_color'],
			'min_height'       => $opts['min_height'],
			'padding'          => [ 'top' => 80, 'right' => 20, 'bottom' => 80, 'left' => 20 ],
		] );

		// Create single column.
		$column = self::get_column_template( 100, [
			'vertical_align' => 'middle',
			'padding'        => [ 'top' => 0, 'right' => 20, 'bottom' => 0, 'left' => 20 ],
		] );

		// Add heading.
		$column['elements'][] = self::get_heading_widget( $opts['heading'], 'h1', [
			'color'       => $opts['text_color'],
			'align'       => $opts['align'],
			'font_weight' => '700',
		] );

		// Add subheading if present.
		if ( ! empty( $opts['subheading'] ) ) {
			$column['elements'][] = self::get_text_widget( '<p>' . $opts['subheading'] . '</p>', [
				'color' => $opts['text_color'],
				'align' => $opts['align'],
			] );
		}

		// Add spacer before CTA.
		if ( ! empty( $opts['cta_text'] ) ) {
			$column['elements'][] = self::get_spacer_widget( 30 );
			$column['elements'][] = self::get_button_widget( $opts['cta_text'], $opts['cta_url'], [
				'bg_color' => $opts['cta_bg_color'],
				'align'    => $opts['align'],
				'size'     => 'lg',
			] );
		}

		$section['elements'][] = $column;

		return $section;
	}

	/**
	 * Build a features section with icon boxes
	 *
	 * @param array $config Features configuration.
	 * @return array Complete section with elements.
	 */
	public static function build_features_section( array $config ): array {
		$defaults = [
			'heading'          => 'Our Features',
			'features'         => [],
			'columns'          => 3,
			'background_color' => '#ffffff',
			'icon_color'       => '#2563EB',
		];

		$opts = array_merge( $defaults, $config );

		// Create section.
		$section = self::get_section_template( [
			'background_color' => $opts['background_color'],
			'padding'          => [ 'top' => 60, 'right' => 20, 'bottom' => 60, 'left' => 20 ],
		] );

		// Add heading in full-width column if present.
		if ( ! empty( $opts['heading'] ) ) {
			$header_column = self::get_column_template( 100 );
			$header_column['elements'][] = self::get_heading_widget( $opts['heading'], 'h2', [
				'align' => 'center',
			] );
			$header_column['elements'][] = self::get_spacer_widget( 40 );

			// Create header section.
			$header_section = self::get_section_template( [
				'padding' => [ 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 ],
			] );
			$header_section['elements'][] = $header_column;
			$section = $header_section;

			// Create features section.
			$features_section = self::get_section_template( [
				'background_color' => $opts['background_color'],
				'padding'          => [ 'top' => 0, 'right' => 20, 'bottom' => 60, 'left' => 20 ],
			] );
		} else {
			$features_section = $section;
		}

		// Calculate column width.
		$column_width = (int) floor( 100 / max( 1, min( 6, $opts['columns'] ) ) );

		// Add feature columns.
		foreach ( $opts['features'] as $feature ) {
			$column = self::get_column_template( $column_width, [
				'padding' => [ 'top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20 ],
			] );

			$column['elements'][] = self::get_icon_box_widget(
				$feature['icon'] ?? 'fas fa-check',
				$feature['title'] ?? 'Feature',
				$feature['description'] ?? '',
				[
					'icon_color' => $opts['icon_color'],
					'position'   => 'top',
				]
			);

			$features_section['elements'][] = $column;
		}

		// If we have header, return array of sections.
		if ( ! empty( $opts['heading'] ) ) {
			return [ $section, $features_section ];
		}

		return $features_section;
	}

	/**
	 * Build a CTA (Call to Action) section
	 *
	 * @param array $config CTA configuration.
	 * @return array Complete section with elements.
	 */
	public static function build_cta_section( array $config ): array {
		$defaults = [
			'heading'          => 'Ready to Get Started?',
			'subheading'       => '',
			'cta_text'         => 'Contact Us',
			'cta_url'          => '#',
			'background_color' => '#2563EB',
			'text_color'       => '#ffffff',
			'cta_bg_color'     => '#ffffff',
			'cta_text_color'   => '#2563EB',
		];

		$opts = array_merge( $defaults, $config );

		// Create section.
		$section = self::get_section_template( [
			'background_color' => $opts['background_color'],
			'padding'          => [ 'top' => 60, 'right' => 20, 'bottom' => 60, 'left' => 20 ],
		] );

		// Create column.
		$column = self::get_column_template( 100 );

		// Add heading.
		$column['elements'][] = self::get_heading_widget( $opts['heading'], 'h2', [
			'color' => $opts['text_color'],
			'align' => 'center',
		] );

		// Add subheading if present.
		if ( ! empty( $opts['subheading'] ) ) {
			$column['elements'][] = self::get_text_widget( '<p>' . $opts['subheading'] . '</p>', [
				'color' => $opts['text_color'],
				'align' => 'center',
			] );
		}

		// Add spacer and button.
		$column['elements'][] = self::get_spacer_widget( 20 );
		$column['elements'][] = self::get_button_widget( $opts['cta_text'], $opts['cta_url'], [
			'bg_color'   => $opts['cta_bg_color'],
			'text_color' => $opts['cta_text_color'],
			'align'      => 'center',
			'size'       => 'lg',
		] );

		$section['elements'][] = $column;

		return $section;
	}

	/**
	 * Adjust color brightness
	 *
	 * @param string $hex    Hex color code.
	 * @param int    $amount Amount to adjust (-255 to 255).
	 * @return string Adjusted hex color.
	 */
	private static function adjust_brightness( string $hex, int $amount ): string {
		$hex = ltrim( $hex, '#' );

		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$r = max( 0, min( 255, hexdec( substr( $hex, 0, 2 ) ) + $amount ) );
		$g = max( 0, min( 255, hexdec( substr( $hex, 2, 2 ) ) + $amount ) );
		$b = max( 0, min( 255, hexdec( substr( $hex, 4, 2 ) ) + $amount ) );

		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}
}

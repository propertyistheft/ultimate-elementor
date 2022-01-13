<?php
/**
 * UAEL Posts Module.
 *
 * @package UAEL
 */

namespace UltimateElementor\Modules\Posts;

use UltimateElementor\Base\Module_Base;
use UltimateElementor\Classes\UAEL_Helper;
use UltimateElementor\Modules\Posts\TemplateBlocks\Build_Post_Query;
use Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Module.
 */
class Module extends Module_Base {

	/**
	 * Module should load or not.
	 *
	 * @since 1.7.0
	 * @access public
	 *
	 * @return bool true|false.
	 */
	public static function is_enable() {
		return true;
	}

	/**
	 * Get Module Name.
	 *
	 * @since 1.7.0
	 * @access public
	 *
	 * @return string Module name.
	 */
	public function get_name() {
		return 'posts';
	}

	/**
	 * All sections.
	 *
	 * @since 1.36.0
	 * @var all_sections
	 */
	private static $all_sections = array();

	/**
	 * Video Widgets.
	 *
	 * @since 1.36.0
	 * @var all_posts_widgets
	 */
	private static $all_posts_widgets = array();

	/**
	 * Get Widgets.
	 *
	 * @since 1.7.0
	 * @access public
	 *
	 * @return array Widgets.
	 */
	public function get_widgets() {
		return array(
			'Posts',
		);
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		/**
		 * Pagination Break.
		 *
		 * @see https://codex.wordpress.org/Making_Custom_Queries_using_Offset_and_Pagination
		 */
		add_action( 'pre_get_posts', array( $this, 'fix_query_offset' ), 1 );
		add_filter( 'found_posts', array( $this, 'fix_query_found_posts' ), 1, 2 );

		add_action( 'wp_ajax_uael_get_post', array( $this, 'get_post_data' ) );
		add_action( 'wp_ajax_nopriv_uael_get_post', array( $this, 'get_post_data' ) );

		if ( UAEL_Helper::is_widget_active( 'Posts' ) ) {

			add_action( 'elementor/frontend/before_render', array( $this, 'get_widget_name' ) );
			add_action( 'wp_footer', array( $this, 'render_posts_schema' ) );
		}
	}

	/**
	 * Render the Posts Schema.
	 *
	 * @since 1.36.0
	 *
	 * @access public
	 */
	public function render_posts_schema() {
		if ( ! empty( self::$all_posts_widgets ) ) {
			$elementor  = \Elementor\Plugin::$instance;
			$data       = self::$all_sections;
			$widget_ids = self::$all_posts_widgets;

			foreach ( $widget_ids as $widget_id ) {

				$widget_data    = $this->find_element_recursive( $data, $widget_id );
				$widget         = $elementor->elements_manager->create_element_instance( $widget_data );
				$settings       = $widget->get_settings();
				$skin           = $widget->get_current_skin_id();
				$select_article = $settings[ $skin . '_select_article' ];
				$schema_support = $settings[ $skin . '_schema_support' ];
				$publisher_name = $settings[ $skin . '_publisher_name' ];
				$publisher_logo = isset( $settings[ $skin . '_publisher_logo' ]['url'] ) ? $settings[ $skin . '_publisher_logo' ]['url'] : 0;
				$query_obj      = new Build_Post_Query( $skin, $settings, '' );
				$query_obj->query_posts();
				$query = $query_obj->get_query();

				if ( $query->have_posts() ) {
						$this->schema_generation( $query, $select_article, $schema_support, $publisher_logo, $publisher_name );
				}
			}
		}
	}

	/**
	 * Render the Posts Schema.
	 *
	 * @since 1.36.0
	 *
	 * @param object $query object.
	 * @param string $select_article string.
	 * @param string $schema_support string.
	 * @param string $publisher_logo string.
	 * @param string $publisher_name string.
	 * @access public
	 */
	public function schema_generation( $query, $select_article, $schema_support, $publisher_logo, $publisher_name ) {
		$object_data            = array();
		$content_schema_warning = false;
		while ( $query->have_posts() ) {
			$query->the_post();
			$headline     = get_the_title();
			$image        = get_the_post_thumbnail_url();
			$publishdate  = get_the_date( 'Y-m-d' );
			$modifieddate = get_the_modified_date( 'Y-m-d' );
			$text         = get_the_excerpt();
			$description  = wp_strip_all_tags( $text );
			$author_id    = get_the_author_meta( 'ID' );
			$author_name  = get_the_author_meta( 'display_name' );
			$author_url   = get_author_posts_url( $author_id );

			if ( 'yes' === $schema_support && ( ( '' === $headline || '' === $publishdate || '' === $modifieddate ) || ( ! $image ) ) ) {
				$content_schema_warning = true;
			}
			if ( 'yes' === $schema_support && false === $content_schema_warning ) {
				$new_data = array(
					'@type'         => $select_article,
					'headline'      => $headline,
					'image'         => $image,
					'datePublished' => $publishdate,
					'dateModified'  => $modifieddate,
					'description'   => $description,
					'author'        => array(
						'@type' => 'Person',
						'name'  => $author_name,
						'url'   => $author_url,
					),
					'publisher'     => array(
						'@type' => 'Organization',
						'name'  => $publisher_name,
						'logo'  => array(
							'@type' => 'ImageObject',
							'url'   => $publisher_logo,
						),
					),
				);
				array_push( $object_data, $new_data );
			}
		}
		if ( $object_data ) {
			$schema_data = array(
				'@context' => 'https://schema.org',
				$object_data,
			);
			UAEL_Helper::print_json_schema( $schema_data );
		}
	}

	/**
	 * Get Post Data via AJAX call.
	 *
	 * @since 1.7.0
	 * @access public
	 */
	public function get_post_data() {

		check_ajax_referer( 'uael-posts-widget-nonce', 'nonce' );

		$post_id   = $_POST['page_id'];
		$widget_id = $_POST['widget_id'];
		$style_id  = $_POST['skin'];

		$elementor = \Elementor\Plugin::$instance;
		$meta      = $elementor->documents->get( $post_id )->get_elements_data();

		$widget_data = $this->find_element_recursive( $meta, $widget_id );

		$data = array(
			'message'    => __( 'Saved', 'uael' ),
			'ID'         => '',
			'skin_id'    => '',
			'html'       => '',
			'pagination' => '',
		);

		if ( null !== $widget_data ) {
			// Restore default values.
			$widget = $elementor->elements_manager->create_element_instance( $widget_data );

			// Return data and call your function according to your need for ajax call.
			// You will have access to settings variable as well as some widget functions.
			$skin = TemplateBlocks\Skin_Init::get_instance( $style_id );

			// Here you will just need posts based on ajax requst to attache in layout.
			$html = $skin->inner_render( $style_id, $widget );

			$pagination = $skin->page_render( $style_id, $widget );

			$data['ID']         = $widget->get_id();
			$data['skin_id']    = $widget->get_current_skin_id();
			$data['html']       = $html;
			$data['pagination'] = $pagination;
		}

		wp_send_json_success( $data );
	}

	/**
	 * Get Widget Setting data.
	 *
	 * @since 1.7.0
	 * @access public
	 * @param array  $elements Element array.
	 * @param string $form_id Element ID.
	 * @return Boolean True/False.
	 */
	public function find_element_recursive( $elements, $form_id ) {

		foreach ( $elements as $element ) {
			if ( $form_id === $element['id'] ) {
				return $element;
			}

			if ( ! empty( $element['elements'] ) ) {
				$element = $this->find_element_recursive( $element['elements'], $form_id );

				if ( $element ) {
					return $element;
				}
			}
		}

		return false;
	}

	/**
	 * Query Offset Fix.
	 *
	 * @since 1.8.4
	 * @access public
	 * @param object $query query object.
	 */
	public function fix_query_offset( &$query ) {
		if ( ! empty( $query->query_vars['offset_to_fix'] ) ) {
			if ( $query->is_paged ) {
				$query->query_vars['offset'] = $query->query_vars['offset_to_fix'] + ( ( $query->query_vars['paged'] - 1 ) * $query->query_vars['posts_per_page'] );
			} else {
				$query->query_vars['offset'] = $query->query_vars['offset_to_fix'];
			}
		}
	}

	/**
	 * Query Found Posts Fix.
	 *
	 * @since 1.8.4
	 * @access public
	 * @param int    $found_posts found posts.
	 * @param object $query query object.
	 * @return int string
	 */
	public function fix_query_found_posts( $found_posts, $query ) {
		$offset_to_fix = $query->get( 'offset_to_fix' );

		if ( $offset_to_fix ) {
			$found_posts -= $offset_to_fix;
		}

		return $found_posts;
	}

	/**
	 * Get widget name.
	 *
	 * @since 1.36.0
	 * @access public
	 * @param object $obj widget data.
	 */
	public function get_widget_name( $obj ) {
		$current_widget = $obj->get_data();
		// If multiple times widget used on a page add all the video elements on a page in a single array.
		if ( isset( $current_widget['elType'] ) && 'section' === $current_widget['elType'] ) {
			array_push( self::$all_sections, $current_widget );
		}
		// If multiple times widget used on a page add all the main widget elements on a page in a single array.
		if ( isset( $current_widget['widgetType'] ) && 'uael-posts' === $current_widget['widgetType'] ) {
			if ( ! in_array( $current_widget['id'], self::$all_posts_widgets, true ) ) {
				array_push( self::$all_posts_widgets, $current_widget['id'] );
			}
		}
	}
}

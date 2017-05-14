<?php


if ( ! defined( 'ABSPATH' ) )
	die('Nope.');


if ( ! class_exists( 'ACFToQuickEdit' ) ) :
class ACFToQuickEdit {
	private static $_instance = null;
	private $post_field_prefix = 'acf_qed_';

	private $column_fields = array();	
	private $quickedit_fields = array();	
	private $bulkedit_fields = array();	

	private $_wp_column_weights = array();	

	/**
	 * Getting a singleton.
	 *
	 * @return object single instance of SteinPostTypePerson
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) )
			self::$_instance = new self();
		return self::$_instance;
	}

	/**
	 * Private constructor
	 */
	private function __construct() {
		add_action( 'plugins_loaded' , array( $this , 'load_textdomain' ) );
		add_action( 'after_setup_theme' , array( $this , 'setup' ) );
	}

	/**
	 * Hooked on 'plugins_loaded' 
	 * Load text domain
	 *
	 * @action plugins_loaded
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acf-quick-edit-fields', false, dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/' );
	}
	/**
	 * Setup plugin
	 *
	 * @action plugins_loaded
	 */
	public function setup() {

		if ( class_exists( 'acf' ) && function_exists( 'acf_get_field_groups' ) ) {
			add_action( 'admin_init' , array( $this, 'admin_init' ) );
			add_action( 'admin_init' , array( $this, 'init_columns' ) );
			add_action( 'load-admin-ajax.php' , array( $this, 'init_columns' ) );
			add_action( 'wp_ajax_get_acf_post_meta' , array( $this, 'ajax_get_acf_post_meta' ) );
			add_action( 'load-edit.php' , array( $this, 'enqueue_assets' ) );
		} else if ( class_exists( 'acf' ) && current_user_can( 'activate_plugins' ) ) {
			add_action( 'admin_notices', array( $this, 'print_acf_free_notice' ) );
		}
	}
	
	/**
	 * @action admin_notices
	 */
	function print_acf_free_notice() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php 
				printf( 
					_x( 'The ACF QuickEdit Fields plugin only provies support for <a href="%1$s">ACF Pro</a>. You can disable and uninstall it on the <a href="%2$s">plugins page</a>.', 
						'1: ACF Pro URL, 2: plugins page url',
						'acf-quick-edit-fields' 
					),
					'http://www.advancedcustomfields.com/pro/',
					admin_url('plugins.php' )
					
				); 
			?></p>
		</div>
		<?php
	}

	/**
	 * @action admin_init
	 */
	function admin_init() {
		

		// Suported ACF Fields
		$types = array( 
			// basic
			'text'				=> array( 'column' => true,		'quickedit' => true,	'bulkedit' => true ), 
			'textarea'			=> array( 'column' => true,		'quickedit' => true,	'bulkedit' => true ), 
			'number'			=> array( 'column' => true,		'quickedit' => true,	'bulkedit' => true ), 
			'email'				=> array( 'column' => true,		'quickedit' => true,	'bulkedit' => true ), 
			'url'				=> array( 'column' => true,		'quickedit' => true,	'bulkedit' => true ), 
			'password'			=> array( 'column' => true,		'quickedit' => true,	'bulkedit' => false ),

			// Content
			'wysiwyg'			=> array( 'column' => false,	'quickedit' => false,	'bulkedit' => false ),
			'oembed'			=> array( 'column' => false,	'quickedit' => false,	'bulkedit' => false ),
			'image'				=> array( 'column' => true,		'quickedit' => false,	'bulkedit' => false ), 
			'file'				=> array( 'column' => true,		'quickedit' => false,	'bulkedit' => false ), 
			'gallery'			=> array( 'column' => true,		'quickedit' => false,	'bulkedit' => false ),

			// Choice
			'select'			=> array( 'column' => true,		'quickedit' => true,	'bulkedit' => true ), 
			'checkbox'			=> array( 'column' => true,		'quickedit' => true,	'bulkedit' => true ), 
			'radio'				=> array( 'column' => true,		'quickedit' => true,	'bulkedit' => true ), 
			'true_false'		=> array( 'column' => true,		'quickedit' => true,	'bulkedit' => true ), 

			// relational
			'post_object'		=> array( 'column' => true,		'quickedit' => false,	'bulkedit' => false ), 
			'page_link'			=> array( 'column' => true,		'quickedit' => false,	'bulkedit' => false ),
			'relationship'		=> array( 'column' => true,		'quickedit' => false,	'bulkedit' => false ), 
			'taxonomy'			=> array( 'column' => true,		'quickedit' => false,	'bulkedit' => false ),
			'user'				=> array( 'column' => false,	'quickedit' => false,	'bulkedit' => false ),

			// jQuery
			'google_map'		=> array( 'column' => false,	'quickedit' => false,	'bulkedit' => false ),
			'date_picker'		=> array( 'column' => true,		'quickedit' => true,	'bulkedit' => true ), 
			'date_time_picker'	=> array( 'column' => true,		'quickedit' => true,	'bulkedit' => true ), 
			'time_picker'		=> array( 'column' => true,		'quickedit' => true,	'bulkedit' => true ), 
			'color_picker'		=> array( 'column' => true,		'quickedit' => true,	'bulkedit' => true ), 
			
			// Layout
			'message'			=> array( 'column' => false,	'quickedit' => false,	'bulkedit' => false ),
			'tab'				=> array( 'column' => false,	'quickedit' => false,	'bulkedit' => false ),
			'repeater'			=> array( 'column' => false,	'quickedit' => false,	'bulkedit' => false ),
			'flexible_content'	=> array( 'column' => false,	'quickedit' => false,	'bulkedit' => false ),
			'clone'				=> array( 'column' => false,	'quickedit' => false,	'bulkedit' => false ),
		);

		/**
		 * Filter field type support of ACF Quick Edit Fields
		 *
		 * @param array $fields		An associative array of field type support having the ACF field name as keys 
		 *							and an array of supported fetaures as values. 
		 *							Features are 'column', 'quickedit' and 'bulkedit'.
		 */
		$types = apply_filters( 'acf_quick_edit_fields_types', $types );

		foreach ( $types as $type => $supports ) {
			if ( $supports['column'] ) {
				add_action( "acf/render_field_settings/type={$type}" , array( $this , 'render_column_settings' ) );
			}
			if ( $supports['quickedit'] ) {
				add_action( "acf/render_field_settings/type={$type}" , array( $this , 'render_quick_edit_settings' ) );
			}
			if ( $supports['bulkedit'] ) {
				add_action( "acf/render_field_settings/type={$type}" , array( $this , 'render_bulk_edit_settings' ) );
			}
		}
	}

	/**
	 * @filter 'acf/format_value/type=radio'
	 */
	function format_radio( $value, $post_id, $field ) {
		if ( ( $nice_value = $field['choices'][$value]) ) {
			return $nice_value;
		}
		return $value;
	}

	/**
	 * @action 'acf/render_field_settings/type={$type}'
	 */
	function render_column_settings( $field ) {
		$post = get_post( $field['ID'] );
		if ( $post ) {
			$parent = get_post( $post->post_parent );
		
			if ( $parent->post_type == 'acf-field-group' ) {
				// show column: todo: allow sortable
				acf_render_field_setting( $field, array(
					'label'			=> __('Show Column','acf-quick-edit-fields'),
					'instructions'	=> '',
					'type'			=> 'true_false',
					'name'			=> 'show_column',
					'message'		=> __("Show a column in the posts list table", 'acf-quick-edit-fields'),
					'width'			=> 50,
				));

				acf_render_field_setting( $field, array(
					'label'			=> __('Column Weight','acf-quick-edit-fields'),
					'instructions'	=> __('Columns with a higher weight will be pushed to the right. The leftmost WordPress column has a weight of <em>0</em>, the next one <em>100</em> and so on. Leave empty to place a column to the rightmost position.','acf-quick-edit-fields'),
					'type'			=> 'number',
					'name'			=> 'show_column_weight',
					'message'		=> __("Column Weight", 'acf-quick-edit-fields'),
					'default_value'	=> '0',
					'min'			=> '-10000',
					'max'			=> '10000',
					'step'			=> '1',
					'placeholder'	=> '',
					'width'			=> '50',
				));
			}
		}
	}

	/**
	 * @action 'acf/render_field_settings/type={$type}'
	 */
	function render_quick_edit_settings( $field ) {
		$post = get_post( $field['ID'] );
		if ( $post ) {
			$parent = get_post( $post->post_parent );
			$parent = get_post( $post->post_parent );

			if ( $parent->post_type == 'acf-field-group' ) {
				// add to quick edit
				acf_render_field_setting( $field, array(
					'label'			=> __('Allow QuickEdit','acf-quick-edit-fields'),
					'instructions'	=> '',
					'type'			=> 'true_false',
					'name'			=> 'allow_quickedit',
					'message'		=> __("Allow editing this field in QuickEdit mode", 'acf-quick-edit-fields')
				));
		
			}
		}
	}

	/**
	 * @action 'acf/render_field_settings/type={$type}'
	 */
	function render_bulk_edit_settings( $field ) {
		$post = get_post($field['ID']);
		if ( $post ) {
			$parent = get_post( $post->post_parent );
			$parent = get_post( $post->post_parent );
		
			if ( $parent->post_type == 'acf-field-group' ) {
				// show column: todo: allow sortable
				// add to bulk edit
				acf_render_field_setting( $field, array(
					'label'			=> __('Allow Bulk Edit','acf-quick-edit-fields'),
					'instructions'	=> '',
					'type'			=> 'true_false',
					'name'			=> 'allow_bulkedit',
					'message'		=> __("Allow editing this field in Bulk edit mode", 'acf-quick-edit-fields')
				));
			}
		}
	}

	/**
	 * @action 'admin_init'
	 */
	function init_columns( $cols ) {
		global $typenow, $pagenow;
		$post_type = isset($_REQUEST['post_type']) ? $_REQUEST['post_type'] : ( ! empty( $typenow ) ? $typenow : 'post' );

		if ( $pagenow == 'upload.php' ) {
			$post_type = 'attachment';
			$conditions = array( 'attachment' => 'all|image' );
		} else {
			$conditions = array( 'post_type' => $post_type );
		}

		/**
		 * Getting the Field Groups to be displayed in posts list table
		 *
		 * @param array $conditions	Field group conditions passed to `acf_get_field_groups()`
		 */
		$field_groups = acf_get_field_groups( apply_filters( 'acf_quick_edit_fields_group_filter', $conditions ) );

		// register column display
		foreach ( $field_groups as $field_group ) {
			$fields = acf_get_fields($field_group);
			if ( ! $fields ) {
				continue;
			}

			foreach ( $fields as $field ) {
				if ( isset($field['show_column']) && $field['show_column'] ) {
					$this->column_fields[$field['name']] = $field;
				}
				if ( isset($field['allow_quickedit']) && $field['allow_quickedit'] ) {
					$this->quickedit_fields[$field['name']] = $field;
				}
				if ( isset($field['allow_bulkedit']) && $field['allow_bulkedit'] ) {
					$this->bulkedit_fields[$field['name']] = $field;
				}
			}
		}

		if ( count( $this->column_fields ) ) {
			if ( 'post' == $post_type ) {
				$cols_hook		= 'manage_posts_columns';
				$display_hook	= 'manage_posts_custom_column';
			} else if ( 'page' == $post_type ) {
				$cols_hook		= 'manage_pages_columns';
				$display_hook	= 'manage_pages_custom_column';
			} else if ( 'attachment' == $post_type ) {
				$cols_hook		= 'manage_media_columns';
				$display_hook	= 'manage_media_custom_column';
			} else {
				$cols_hook		= "manage_{$post_type}_posts_columns";
				$display_hook	= "manage_{$post_type}_posts_custom_column";
			}
			add_filter( $cols_hook,		array( $this, 'add_field_columns' ) );
			add_filter( $cols_hook,		array( $this, 'move_date_to_end' ) );
			add_filter( $display_hook,	array( $this, 'display_field_column' ), 10, 2 );
		}
		
		if ( count( $this->column_fields ) ) {
			$has_thumbnail		= false;
			foreach ( $this->column_fields as $field ) {
				if ( $field['type'] == 'image' || $field['type'] == 'gallery' ) {
					$has_thumbnail = true;
					break;
				}
			}

/*
			if ( $has_thumbnail ) {
				wp_enqueue_script( 'acf-qef-thumbnail-col', plugins_url( 'js/thumbnail-col.js', dirname( __FILE__ ) ), array( 'inline-edit-post' ), null, true );
			}
*/
		}
		wp_enqueue_style( 'acf-qef-thumbnail-col', plugins_url( 'css/thumbnail-col.css', dirname( __FILE__ ) ) );
		
		// register quickedit
		if ( count( $this->quickedit_fields ) ) {
			// enqueue scripts ...
			add_action( 'quick_edit_custom_box',  array( $this, 'display_quick_edit' ), 10, 2);
			add_action( 'save_post', array( $this, 'quickedit_save_acf_meta' ) );


		}
		
		// register bulkedit
		if ( count( $this->bulkedit_fields ) ) {
			add_action( 'bulk_edit_custom_box', array( $this , 'display_bulk_edit' ), 10, 2 );
		}
	}

	/**
	 * @action 'load-edit.php'
	 */
	function enqueue_assets() {
		if ( count( $this->column_fields ) ) {
			$has_thumbnail		= false;
			foreach ( $this->column_fields as $field ) {
				if ( $field['type'] == 'image' || $field['type'] == 'gallery' ) {
					$has_thumbnail = true;
					break;
				}
			}
		}

		// register quickedit
		if ( count( $this->quickedit_fields ) ) {
			// enqueue scripts ...
			$has_datepicker		= false;
			$has_colorpicker	= false;
			foreach ( $this->quickedit_fields as $field ) {
				if ( $field['type'] == 'date_picker' || $field['type'] == 'time_picker' || $field['type'] == 'date_time_picker'  ) {
					$has_datepicker = true;
				}
				if ( $field['type'] == 'color_picker' ) {
					$has_colorpicker = true;
				}
			}

			// ... if necessary
			if ( $has_datepicker ) {
				// datepicker
				wp_enqueue_script( 'jquery-ui-datepicker' );
				wp_enqueue_style('acf-datepicker', acf_get_dir('assets/inc/datepicker/jquery-ui.min.css') );

				// timepicker. Contains some usefull parsing mathods even for dates.
				wp_enqueue_script('acf-timepicker', acf_get_dir('assets/inc/timepicker/jquery-ui-timepicker-addon.min.js'), array('jquery-ui-datepicker') );
				wp_enqueue_style('acf-timepicker', acf_get_dir('assets/inc/timepicker/jquery-ui-timepicker-addon.min.css') );
			}

			if ( $has_colorpicker ) {
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
			}

			wp_enqueue_style( 'acf-quick-edit', plugins_url( 'css/acf-quickedit.css', dirname( __FILE__ ) ) );
			wp_enqueue_script( 'acf-quick-edit', plugins_url( 'js/acf-quickedit.min.js', dirname( __FILE__ ) ), array( 'inline-edit-post' ), null, true );
		}
		
	}

	/**
	 * @action 'wp_ajax_get_acf_post_meta'
	 */
	function ajax_get_acf_post_meta() {

		header('Content-Type: application/json');

		if ( isset( $_REQUEST['post_id'] , $_REQUEST['acf_field_keys'] ) ) {

			$result = array();
			 
			$post_ids = (array) $_REQUEST['post_id'];

			$post_ids = array_filter( $post_ids,'intval');

			$field_keys = array_unique( $_REQUEST['acf_field_keys'] );

			foreach ( $post_ids as $post_id ) {

				if ( current_user_can( 'edit_post' , $post_id ) ) {

					foreach ( $field_keys as $key ) {

						$field_obj = get_field_object( $key , $post_id );

						switch ( $field_obj['type'] ) {
							case 'date_time_picker':
							case 'time_picker':
							case 'date_picker':
								$field_val	= acf_get_metadata( $post_id, $field_obj['name'] );
								break;
							default:
								$field_val	= $field_obj['value'];
								break;
						}
						if ( ! isset( $result[ $key ] ) || $result[ $key ] == $field_val ) {

							$result[ $key ]	= $field_val;

						} else {

							$result[ $key ] = '';

						}
					}
				}
			}

			echo json_encode( $result );

			exit();
		}
	}

	/**
	 * @filter manage_posts_columns
	 * @filter manage_media_columns
	 * @filter manage_{$post_type}_posts_columns
	 */
	function add_field_columns( $columns ) {

		$this->_wp_column_weights = array_map( array( $this, '_mul_100' ) , array_flip( array_keys( $columns ) ) );

		foreach ( $this->column_fields as $field_slug => $field ) {
			if ( in_array( $field['type'], array('image','gallery'))) {
				$field_slug .= '-qef-thumbnail';
			}
			$columns[ $field_slug ] = $field['label'];
		}
		uksort($columns, array( $this, '_sort_columns_by_weight' ));
		return $columns;
	}

	private function _mul_100( $val ) {
		return intval( $val ) * 100;
	}

	/**
	 * @private
	 */
	private function _sort_columns_by_weight( $a_slug, $b_slug ) {
		$a = $b = 0;
		$a = $this->_get_column_weight( $a_slug );
		$b = $this->_get_column_weight( $b_slug );
		return $a - $b;
	}

	private function _get_column_weight( $column_slug ) {
		$column_slug = str_replace('-qef-thumbnail','',$column_slug);
		if ( isset( $this->_wp_column_weights[ $column_slug ] ) ) {
			return intval( $this->_wp_column_weights[ $column_slug ] );
		} else if ( isset( $this->column_fields[ $column_slug ]['show_column_weight'] ) && '' !==  $this->column_fields[ $column_slug ]['show_column_weight'] ) {
			return intval( $this->column_fields[ $column_slug ]['show_column_weight'] );
		}
		return max( $this->_wp_column_weights ) + 1;
	}

	/**
	 * @filter manage_posts_custom_column
	 * @filter manage_media_custom_column
	 * @filter manage_{$post_type}_posts_custom_column
	 */
	function display_field_column( $wp_column_slug , $post_id ) {
		$column = str_replace('-qef-thumbnail','', $wp_column_slug );
		if ( isset( $this->column_fields[$column] ) ) {
			$field = $this->column_fields[$column];
			switch ( $field['type'] ) {
				case 'file':
					$value = acf_get_value( $post_id, $field );
					if ( ! is_null($value) && ! empty($value) ) {
						$file = get_post($value);
						printf( __('Edit: <a href="%s">%s</a>','acf-quick-edit-fields') , get_edit_post_link( $value ) , $file->post_title );
					}
					break;
				case 'image':
					$image_id = get_field( $field['key'] );
					if ( $image_id ) {
						if ( is_array( $image_id ) ) {
							// Image field is an object
							echo wp_get_attachment_image( $image_id['id'] , array(80,80) );
						} else if( is_numeric( $image_id ) ) {
							// Image field is an ID
							echo wp_get_attachment_image( $image_id , array(80,80) );
						} else {
							// Image field is a url
							echo '<img src="' . $image_id . '" width="80" height="80" />';
						};
					}
					break;
				case 'gallery':
					/**
					 * Filter number of images to be displayed in Gallery Column
					 *
					 * @param int $max_images	Maximum Number of images
					 */
					if ( $max_images = apply_filters( 'acf_quick_edit_fields_gallery_col_max_images', 15 ) ) {
						$images = get_field( $field['key'] );
						if ( $images ) {
							$class = count($images) > 1 ? 'acf-qef-gallery-col' : 'acf-qef-image-col';
							?><div class="<?php echo $class ?>"><?php
							foreach ( array_values( $images ) as $i => $image) {
								if ( $i >= $max_images ) {
									break;
								}
								echo wp_get_attachment_image( $image['id'] , array(80, 80) );
							}
							?></div><?php
						}
					}
					break;
				case 'select':
				case 'radio':
				case 'checkbox':
					$field_value = get_field($field['key']);
					$values = array();
					foreach ( (array) $field_value as $value )
						$values[] = isset( $field['choices'][ $value ] ) 
										? $field['choices'][ $value ] 
										: $value;
					
					$output = implode( __(', ', 'acf-quick-edit-fields' ) , $values );
					if ( empty( $output ) )
						$output = __('(No value)', 'acf-quick-edit-fields');
					echo $output;
					break;
				case 'true_false':
					echo get_field($field['key']) ? __('Yes') : __('No');
					break;
				case 'color_picker':
					$value = get_field($field['key']);
					if ( $value )
						echo '<div class="color-indicator" style="border-radius:2px;border:1px solid #d2d2d2;width:26px;height:20px;background-color:'.$value.'"></div>';
					else
						_e('(No value)', 'acf-quick-edit-fields');
					break;
				case 'number':
					$value = get_field($field['key']);
					if ( $value === "" )
						_e('(No value)', 'acf-quick-edit-fields');
					else
						echo number_format_i18n($value, strlen(substr(strrchr($value, "."), 1)) );
					break;
				case 'textarea':
					?><pre><?php
						the_field($field['key']);
					?></pre><?php
					break;
				case 'taxonomy':
					$value = get_field($field['key']);
					if ( $value ) {
						$term_names = array();
						foreach ( (array) $value as $i => $term ) {
							if ( $field['return_format'] === 'id' ) {
								$term = get_term($term, $field['taxonomy']);
							}
							$term_names[] = $term->name;
						}
						echo implode( ', ', $term_names );
					} else {
						_e('(No value)', 'acf-quick-edit-fields');
					}
					break;
				case 'relationship':
				case 'post_object':
					$field_value = get_field( $field['key'] );
					if ( is_a( $field_value, 'WP_Post' ) ) {
						echo $this->get_post_object_link( $field_value->ID );
					} else if ( is_array( $field_value ) ) {
						$links = array();
						foreach ( $field_value as $field_value_post ) {
							$field_value_post_id = 0;
							if ( is_a( $field_value_post, 'WP_Post' ) ) {
								$field_value_post_id = $field_value_post->ID;
							} else if ( is_int( $field_value_post ) ) {
								$field_value_post_id = $field_value_post;
							}
							if ( $field_value_post_id && $link = $this->get_post_object_link( $field_value_post_id ) ) {
								$links[] = $link;
							}
						}
						if ( count( $links > 1 ) ) {
							echo "<ol>";
							foreach ( $links as $link ) {
								printf( '<li>%s</li>', $link );
							}
							echo "</ol>";
						} else {
							echo implode( '<br />', $links );
						}
					}
					break;
				case 'password':
					if ( $field_value = get_field( $field['key'] ) ) {
						echo '<code>********</code>';
					}
					break;
				case 'date_picker':
				case 'time_picker':
				case 'date_time_picker':
					$val = get_field( $field['key'], $post_id, false );
					echo acf_format_date( $val, $field['display_format'] );
					break;
				default:
					the_field( $field['key'] );
					break;
			}
		}
	}

	private function get_post_object_link( $post_id ) {
		$result = '';
		$title = get_the_title( $post_id );

		if ( current_user_can( 'edit_post', $post_id ) ) {
			$result .= sprintf( '<a href="%s">%s</a>', get_edit_post_link( $post_id ), $title );
		} else if ( current_user_can( 'read_post', $post_id ) ) {
			$result .= sprintf( '<a href="%s">%s</a>', get_permalink( $post_id ), $title );
		} else {
			$result .= $title;
		}

		if ( 'attachment' !== get_post_type( $post_id ) && 'private' === get_post_status( $post_id ) ) {	
			$result .= ' &mdash; ' . __('Private', 'acf-quick-edit-fields' );
		}
		return $result;
	}

	function move_date_to_end($defaults) {  
	    $date = $defaults['date'];
	    unset($defaults['date']);
	    $defaults['date'] = $date;
	    return $defaults; 
	} 

	function display_quick_edit( $wp_column_slug, $post_type ) {
		$column = str_replace('-qef-thumbnail','', $wp_column_slug );
		if ( isset($this->quickedit_fields[$column]) && $field = $this->quickedit_fields[$column] ) {
			$this->display_quickedit_field( $column, $post_type , $field, 'quick' );
		}
	}
	function display_bulk_edit( $wp_column_slug, $post_type ) {
		$column = str_replace('-qef-thumbnail','', $wp_column_slug );
		if ( isset($this->bulkedit_fields[$column]) && $field = $this->bulkedit_fields[$column] ) {
			$this->display_quickedit_field( $column, $post_type , $field, 'bulk' );
		}
	}

	function display_quickedit_field( $column, $post_type , $field, $mode ) {

		?>
		<fieldset class="inline-edit-col-left inline-edit-<?php echo $post_type ?>">
			<div class="acf-field inline-edit-col column-<?php echo $column; ?>" data-key="<?php echo $field['key'] ?>">
				<label class="inline-edit-group">
					<span class="title"><?php echo $field['label']; ?></span>
					<span class="input-text-wrap"><?php
						$input_atts = array(
							'data-acf-field-key' => $field['key'],
							'name' => $this->post_field_prefix . $column,
						);

						switch ($field['type']) {

							case 'checkbox':
								?><ul class="acf-checkbox-list" data-acf-field-key="<?php echo $field['key'] ?>"><?php
								$input_atts		+= array(
									'class'	=> 'acf-quick-edit',
									'id'	=> $this->post_field_prefix . $column,
								);
								$field['value']	= acf_get_array( $field['value'], false );
								foreach ( $field['choices'] as $value => $label ) {
									$atts = array(
										'data-acf-field-key'	=> $field['key'],
										'type'					=> 'checkbox',
										'value'					=> $value,
										'name'					=> $this->post_field_prefix . $column . '[]',
										'id'					=> $this->post_field_prefix . $column . '-'.$value,
									);

									if ( in_array( $value, $field['value'] ) ) {
										$atts['checked'] = 'checked';
									}
									echo '<li><label><input ' . acf_esc_attr( $atts ) . '/>' . $label . '</label></li>';
								}
								?></ul><?php
								break;

							case 'select':
								$input_atts += array(
									'class' => 'acf-quick-edit widefat',
									'id' => $this->post_field_prefix . $column,
								);
								if ( $field['multiple'] )
									$input_atts['multiple'] = 'multiple';

								?><select <?php echo acf_esc_attr( $input_atts ) ?>><?php
									if ( $field['allow_null'] ) {
										echo '<option value="">' . '- ' . __( 'Select', 'acf' ) . ' -';
									}
									foreach($field['choices'] as $name => $label) {
										echo '<option value="' . $name . '">' . $label;
									}
								?></select><?php
								break;

							case 'radio':
								// + others
								?><ul class="acf-radio-list<?php echo $field['other_choice'] ? ' other' : '' ?>" data-acf-field-key="<?php echo $field['key'] ?>"><?php
								foreach($field['choices'] as $name => $value) {
									?><li><label for="<?php echo $this->post_field_prefix . $column.'-'.$name; ?>"><?php
										?><input id="<?php echo $this->post_field_prefix . $column.'-'.$name; ?>" type="radio" value="<?php echo $name; ?>" 
										  class="acf-quick-edit" data-acf-field-key="<?php echo $field['key'] ?>"
										  name="<?php echo $this->post_field_prefix . $column; ?>" /><?php echo $value; ?><?php
									?></label></li><?php
								}
								if ( $field['other_choice'] ) {
									?><li><label for="<?php echo $this->post_field_prefix . $column.'-other'; ?>"><?php
										?><input id="<?php echo $this->post_field_prefix . $column.'-other'; ?>" type="radio" value="other" 
										  class="acf-quick-edit" data-acf-field-key="<?php echo $field['key'] ?>"
										  name="<?php echo $this->post_field_prefix . $column; ?>" /><?php
										?><input type="text" class="acf-quick-edit" data-acf-field-key="<?php echo $field['key'] ?>" 
											name="<?php echo $this->post_field_prefix . $column; ?>" style="width:initial" /><?php
										?></li><?php
									?></label><?php
								}
								?></ul><?php
								break;

							case 'true_false':
								?><ul class="acf-radio-list" data-acf-field-key="<?php echo $field['key'] ?>"><?php
									?><li><label for="<?php echo $this->post_field_prefix . $column; ?>-yes"><?php 
										?><input id="<?php echo $this->post_field_prefix . $column; ?>-yes" type="radio" value="1" class="acf-quick-edit" data-acf-field-key="<?php echo $field['key'] ?>" name="<?php echo $this->post_field_prefix . $column; ?>" /><?php
										_e('Yes')
									?></label></li><?php
									?><li><label for="<?php echo $this->post_field_prefix . $column; ?>-no"><?php 
										?><input id="<?php echo $this->post_field_prefix . $column; ?>-no"  type="radio" value="0" class="acf-quick-edit" data-acf-field-key="<?php echo $field['key'] ?>" name="<?php echo $this->post_field_prefix . $column; ?>" /><?php
										_e('No')
									?></label></li><?php
								?></ul><?php
								break;

							case 'number':
								$input_atts += array(
									'class'	=> 'acf-quick-edit',
									'type'	=> 'number', 
									'min'	=> $field['min'], 
									'max'	=> $field['max'],
									'step'	=> $field['step'], 
								);
								echo '<input '. acf_esc_attr( $input_atts ) .' />';
								break;

							case 'date_picker':
								$wrap_atts = array(
									'class'				=> 'acf-quick-edit acf-quick-edit-'.$field['type'],
									'data-date_format'	=> acf_convert_date_to_js($field['display_format']),
									'data-first_day'	=> $field['first_day'],
								);
								$display_input_atts	= array(
									'type'	=> 'text',
								);
								$input_atts += array(
									'type'	=> 'hidden', 
								);
								
								echo '<span '. acf_esc_attr( $wrap_atts ) .'>';
								echo '<input '. acf_esc_attr( $input_atts ) .' />';
								echo '<input '. acf_esc_attr( $display_input_atts ) .' />';
								echo '</span>';
								break;

							case 'date_time_picker':
								$formats = acf_split_date_time($field['display_format']);
								$wrap_atts = array(
									'class'				=> 'acf-quick-edit acf-quick-edit-'.$field['type'],
									'data-date_format'	=> acf_convert_date_to_js($formats['date']),
									'data-time_format'	=> acf_convert_time_to_js($formats['time']),
									'data-first_day'	=> $field['first_day'],
								);
								$display_input_atts	= array(
									'type'	=> 'text',
								);
								$input_atts += array(
									'type'	=> 'hidden', 
								);
								
								echo '<span '. acf_esc_attr( $wrap_atts ) .'>';
								echo '<input '. acf_esc_attr( $input_atts ) .' />';
								echo '<input '. acf_esc_attr( $display_input_atts ) .' />';
								echo '</span>';
								break;

							case 'time_picker':
								$wrap_atts = array(
									'class'				=> 'acf-quick-edit acf-quick-edit-'.$field['type'],
									'data-time_format'	=> acf_convert_time_to_js($field['display_format']),
								);
								$display_input_atts	= array(
									'type'	=> 'text',
								);
								$input_atts += array(
									'type'	=> 'hidden', 
								);
								
								echo '<span '. acf_esc_attr( $wrap_atts ) .'>';
								echo '<input '. acf_esc_attr( $input_atts ) .' />';
								echo '<input '. acf_esc_attr( $display_input_atts ) .' />';
								echo '</span>';
								break;

							case 'textarea':
								$input_atts += array(
									'class'	=> 'acf-quick-edit acf-quick-edit-'.$field['type'],
									'type'	=> 'text', 
								);
								echo '<textarea '. acf_esc_attr( $input_atts ) .'>'.esc_textarea($field['value']).'</textarea>';
								break;

							case 'password':
								$input_atts += array(
									'class'	=> 'acf-quick-edit acf-quick-edit-'.$field['type'],
									'type'	=> 'password', 
									'autocomplete'	=> 'new-password',
								);
								echo '<input '. acf_esc_attr( $input_atts ) .' />';
								break;

							case 'color_picker':
								$input_atts += array(
									'class'	=> 'wp-color-picker acf-quick-edit acf-quick-edit-'.$field['type'],
									'type'	=> 'text', 
								);
								echo '<input '. acf_esc_attr( $input_atts ) .' />';
								break;

							default:

								do_action( 'acf_quick_edit_field_' . $field['type'], $field, $column, $post_type  );
								if ( ! apply_filters( 'acf_quick_edit_render_' . $field['type'], true, $field, $column, $post_type ) ) {
									break;
								}
								$input_atts += array(
									'class'	=> 'acf-quick-edit acf-quick-edit-'.$field['type'],
									'type'	=> 'text', 
								);
								echo '<input '. acf_esc_attr( $input_atts ) .' />';
								break;
						}
					?></span>
				</label>
			</div>
		</fieldset><?php
	}

	/**
	 *	@action save_post
	 */
	function quickedit_save_acf_meta( $post_id ) {

		$is_quickedit = is_admin() && defined( 'DOING_AJAX' ) && DOING_AJAX;

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( $this->quickedit_fields as $field_name => $field ) {
			switch ( $field['type'] ) {
				case 'checkbox':
					$do_update	= true;
					$value		= isset( $_REQUEST[ $this->post_field_prefix . $field['name'] ] ) 
									? $_REQUEST[ $this->post_field_prefix . $field['name'] ] 
									: null;
					break;
				default:
					$do_update	= $is_quickedit 
									? isset( $_REQUEST[ $this->post_field_prefix . $field['name'] ] )
									: isset( $_REQUEST[ $this->post_field_prefix . $field['name'] ] ) && ! empty( $_REQUEST[ $this->post_field_prefix . $field['name'] ] );
					$value		= $_REQUEST[ $this->post_field_prefix . $field['name'] ];
					break;
			}
			if ( $do_update ) {
				update_field( $field['name'], $value, $post_id );
			}
		}
//		exit();
	}
}

ACFToQuickEdit::instance();
endif;

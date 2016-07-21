<?php
/*
Plugin Name: Deserialize Metadata
Description: In the process of migrating from Drupal to WordPress by SQL queries, it's likely that some metadata fields that are serialized in Drupal will be migrated into the WordPress database, but will need to be separated into fields.
Version: 0.0.1
Author: Jonathan Stegall
Author URI: http://code.minnpost.com
License: GPL2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: deserialize-metadata
*/

// Start up the plugin
class Deserialize_Metadata {

	/**
	* @var string
	*/
	private $version;

	/**
	* @var array
	*/
	private $config;

	/**
	 * This is our constructor
	 *
	 * @return void
	 */
	public function __construct() {

		$this->version = '0.0.1';
		$this->config = array();

		$this->load_admin();

		$this->config();
		$this->schedule();

		register_deactivation_hook(__FILE__, array( $this, 'deactivate' ) );

	}

	/**
	* load the admin stuff
	* creates admin menu to save the config options
	*
	* @throws \Exception
	*/
    private function load_admin() {
    	add_action( 'admin_menu', array( &$this, 'create_admin_menu' ) );
    	add_action( 'admin_init', array( &$this, 'admin_settings_form' ) );
//    	add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts_and_styles' ) );
    }

    /**
    * Default display for <input> fields
    *
    * @param array $args
    */
    public function create_admin_menu() {
    	add_options_page( 'Deserialize Metadata', 'Deserialize Metadata', 'manage_options', 'deserialize-metadata', array( &$this, 'show_admin_page' ) );
	}

	/**
    * Display the admin settings page
    *
    * @return void
    */
	public function show_admin_page() {
		?>
		<div class="wrap">
			<h1><?php _e( get_admin_page_title() , 'deserialize-metadata'); ?></h1>
			<div id="main">
				<form method="post" action="options.php">
					<?php settings_fields( 'deserialize-metadata' )  . do_settings_sections( 'deserialize-metadata' ); ?>
					<?php $deserialize_maps = get_option( 'deserialize_metadata_maps', '' ) ?>

					<table class="wp-list-table widefat striped fields">
		                <thead>
		                    <tr>
		                        <th class="column-map_key">Map Key</th>
		                        <th class="column-wp_table">WordPress Table</th>
		                        <th class="column-wp_column">WordPress Column</th>
		                        <th class="column-unique">Unique?</th>
		                        <th class="column-actions">Actions</th>
		                    </tr>
		                </thead>
		                <tbody>
		                	<?php
		                	if ( isset( $deserialize_maps ) && $deserialize_maps !== '' ) {
								foreach ( $deserialize_maps as $key => $value ) { ?>
									<tr class="repeating">
										<td class="column-map_key">
											<input name="deserialize_metadata_maps[<?php echo $key; ?>][map_key]" type="text" value="<?php echo $value['map_key']; ?>" />
										</td>
										<td class="column-wp_table">
											<input name="deserialize_metadata_maps[<?php echo $key; ?>][wp_table]" type="text" value="<?php echo $value['wp_table']; ?>" />
										</td>
										<td class="column-wp_column">
											<input name="deserialize_metadata_maps[<?php echo $key; ?>][wp_column]" type="text" value="<?php echo $value['wp_column']; ?>" />
										</td>
										<td class="column-unique">
											<?php
											if ( isset( $value['unique'] ) && $value['unique'] === '1' ) {
												$checked = ' checked';
											} else {
												$checked = '';
											}
											?>
											<input name="deserialize_metadata_maps[<?php echo $key; ?>][unique]" type="checkbox" value="1" <?php echo $checked; ?> />
										</td>
										<td class="column-actions">
											<a href="#" class="delete-this"><?php _e( 'Delete', 'deserialize-metadata' ); ?></a>
										</td>
									</tr>
		                		<?php
		                		}
		                	} else { ?>
								<tr class="repeating">
									<td class="column-map_key">
										<input name="deserialize_metadata_maps[0][map_key]" type="text" value="" />
									</td>
									<td class="column-wp_table">
										<input name="deserialize_metadata_maps[0][wp_table]" type="text" value="" />
									</td>
									<td class="column-wp_column">
										<input name="deserialize_metadata_maps[0][wp_column]" type="text" value="" />
									</td>
									<td class="column-unique">
										<input type="checkbox" name="deserialize_metadata_maps[0][unique]" value="1" />
									</td>
									<td>&nbsp;</td>
								</tr>
		                	<?php
		                	}
		                	?>
		                	<tr>
		                		<td colspan="4">
		                			<p><a href="#" class="repeat"><?php _e( 'Add Another Map', 'deserialze-metadata' ); ?></a></p>
		                		</td>
		                </tbody>
	                </table>

	                <?php submit_button( 'Save settings' ); ?>

				</form>

				<script>
				// Add a new repeating section
				var attrs = ['id', 'name'];
				function resetAttributeNames(section) { 
					var tags = section.find('input'), idx = section.index();
					tags.each(function() {
						var $this = jQuery(this);
						jQuery.each(attrs, function(i, attr) {
							var attr_val = $this.attr(attr);
							if (attr_val) {
								$this.attr(attr, attr_val.replace(/\[(\d+)\]/g, '['+(idx)+']'));
								$this.attr(attr, attr_val.replace(/\[(\d+)\]/g, '['+(idx)+']'));
							}
						});
					});
				}
								                   
				jQuery('.repeat').click(function(e){
					e.preventDefault();
					var lastRepeatingGroup = jQuery('.repeating').last();
					var cloned = lastRepeatingGroup.clone(true);
					cloned.insertAfter(lastRepeatingGroup);
					cloned.find('input').val('');
					cloned.find('select').val('');
					cloned.find('input:radio').attr('checked', false);
					resetAttributeNames(cloned);
				});

				jQuery('.delete-this').click(function(e){
					e.preventDefault(); 
					jQuery(this).parent().parent('tr').remove();
				});

				</script>

			</div>
		</div>
		<?php
	}

	/**
    * Register items for the settings api
    * @return void
    *
    */
	public function admin_settings_form() {
		$page = 'deserialize-metadata';
		$section = 'deserialize-metadata';
		$input_callback = array( &$this, 'display_input_field' );
		add_settings_section( $page, null, null, $page );

		$settings = array(
            'wp_imported_field' => array(
                'title' => 'Imported Field',
                'callback' => $input_callback,
                'page' => $page,
                'section' => $section,
                'args' => array(
                    'type' => 'text',
                    'desc' => 'The name of the imported field in the database',
                ),
                
            ),
            'post_type' => array(
                'title' => 'Post Type',
                'callback' => $input_callback,
                'page' => $page,
                'section' => $section,
                'args' => array(
                    'type' => 'text',
                    'desc' => 'What type of post uses this metadata?',
                ),
            ),
            'post_status' => array(
                'title' => 'Post Status',
                'callback' => $input_callback,
                'page' => $page,
                'section' => $section,
                'args' => array(
                    'type' => 'text',
                    'desc' => 'Post statuses to match',
                ),
            ),
            'posts_per_page' => array(
                'title' => 'Posts Per Page',
                'callback' => $input_callback,
                'page' => $page,
                'section' => $section,
                'args' => array(
                    'type' => 'text',
                    'desc' => 'Maximum posts the query should load',
                ),
            ),
        );

        foreach( $settings as $key => $attributes ) {
            $id = 'deserialize_metadata_' . $key;
            $name = 'deserialize_metadata_' . $key;
            $title = $attributes['title'];
            $callback = $attributes['callback'];
            $page = $attributes['page'];
            $section = $attributes['section'];
            $args = array_merge(
                $attributes['args'],
                array(
                    'title' => $title,
                    'id' => $id,
                    'label_for' => $id,
                    'name' => $name
                )
            );
            add_settings_field( $id, $title, $callback, $page, $section, $args );
            register_setting( $section, $id );
        }
        register_setting( $section, 'deserialize_metadata_maps' );

	}

	/**
    * Default display for <input> fields
    *
    * @param array $args
    */
    public function display_input_field( $args ) {
        $type   = $args['type'];
        $id     = $args['label_for'];
        $name   = $args['name'];
        $desc   = $args['desc'];
        if ( !isset( $args['constant'] ) || !defined( $args['constant'] ) ) {
            $value  = esc_attr( get_option( $id, '' ) );
            echo '<input type="' . $type. '" value="' . $value . '" name="' . $name . '" id="' . $id . '"
            class="regular-text code" />';
            if ( $desc != '' ) {
                echo '<p class="description">' . $desc . '</p>';
            }
        } else {
            echo '<p><code>Defined in wp-config.php</code></p>';
        }
    }

	/**
	 * Create an action on plugin init so we can gather some config items for this plugin from the wp settings
	 * this sets the $this->config variable
	 *
	 * @return void
	 */
	private function config() {

		$maps = get_option( 'deserialize_metadata_maps', '' );
		$config_maps = array();
		foreach ( $maps as $key => $map ) {
			$key = $map['map_key'];
			$config_maps["$key"] = array(
				'wp_table' => $map['wp_table'],
				'wp_column' => $map['wp_column'],
				'unique' => $map['unique']
			);
		}

		$this->config = array(
			0 => array(
				'wp_imported_field' => get_option( 'deserialize_metadata_wp_imported_field', '' ),
				'post_type' => get_option( 'deserialize_metadata_post_type', '' ),
				'post_status' => get_option( 'deserialize_metadata_post_status', '' ),
				'posts_per_page' => get_option( 'deserialize_metadata_posts_per_page', '' ),
				'maps' => $config_maps
			),
		);

	}

	/**
	 * Set a default config array to indicate what fields to deserialize, and what to do with them afterwards
	 * This method would set up the apply_filters hook so users can add/modify the config in their own functions.php
	 * We do this because this plugin only does things on activate
	 * This seems to be impossible though. Might as well keep it around in here for now in case that's not accurate.
	 *
	 * @return void
	 */
	public function get_config_data() {
    	$this->config = apply_filters( 'config_deserialize_metadata', $this->config );
	}

	/**
	 * Scheule function
	 * This registers the method to get the WordPress data that needs to be unserialized
	 *
	 * @return void
	 */
	public function schedule() {
		if (! wp_next_scheduled ( 'start_serialized_event' ) ) {
			wp_schedule_event( time(), 'hourly', 'start_serialized_event' );
	    }
	    add_action( 'start_serialized_event', array( $this, 'get_posts_with_serialized_metadata') );
	}

	/**
	 * Deactivate function
	 * This stops the regular repetition of the task
	 *
	 * @return void
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( 'start_serialized_event' );
	}

	/**
	 * Get WordPress posts that match our criteria for serialized metadata
	 * This also calls the create and delete methods to handle what to do with the data
	 *
	 * @return void
	 */
	public function get_posts_with_serialized_metadata() {
		foreach ( $this->config as $config ) {
			$key = $config['wp_imported_field'];
			$maps = $config['maps'];
			$args = array( 'post_type' => $config['post_type'], 'post_status' => $config['post_status'], 'posts_per_page' => $config['posts_per_page'], 'meta_query' => array( array( 'key' => $key ) ) );
			$query = new WP_Query( $args );
			if ( $query->have_posts() ) {
				error_log('There are ' . $query->post_count . ' posts with imported metadata.');
				while ( $query->have_posts() ) {
					$query->the_post();
					$post_id = $query->post->ID;
					$metadata = get_post_meta( $post_id, $key, true );
					$this->create_fields( $post_id, $metadata, $maps );
					$this->delete_combined_field( $post_id, $key );
				}
			}
		}

	}

	/**
	 * Create fields to match the structure WordPress wants
	 * This structure is defined in $this->config
	 *
	 * @return void
	 */
	public function create_fields( $post_id, $metadata, $maps ) {
		foreach ( $metadata as $key => $value ) {
			if ( array_key_exists( $key, $maps ) ) {
				//error_log('create a row on the ' . $maps[$key]['wp_table'] . ' field in the ' . $maps[$key]['wp_column'] . ' column with the value ' . $value);
				if ( $maps[$key]['wp_table'] === 'wp_postmeta' && $value != '' && $value != NULL ) {
					add_post_meta( $post_id, $maps[$key]['wp_column'], $value, $maps[$key]['unique'] );
				} else if ( $maps[$key]['wp_table'] === 'wp_posts' && $value != '' && $value != NULL ) {
					$post = array(
						'ID' => $post_id,
						$maps[$key]['wp_column'] => $value
					);
					wp_update_post( $post );
				}
			}
		}
	}

	/**
	 * Delete the original, serialized field so we don't trigger it if the plugin is activated again
	 *
	 * @return void
	 */
	public function delete_combined_field( $post_id, $key ) {
		delete_post_meta( $post_id, $key );
	}

/// end class
}
// Instantiate our class
$Deserialize_Metadata = new Deserialize_Metadata();
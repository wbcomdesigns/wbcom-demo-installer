<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://www.wbcomdesigns.com
 * @since      1.0.0
 *
 * @package    Wb_Demo_Installer
 * @subpackage Wb_Demo_Installer/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Wb_Demo_Installer
 * @subpackage Wb_Demo_Installer/admin
 * @author     Wbcom Designs <admin@wbcomdesigns.com>
 */
class Wb_Demo_Installer_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function wbdi_enqueue_styles() {
		if( stripos( $_SERVER['REQUEST_URI'], $this->plugin_name ) ) {
			wp_enqueue_style( $this->plugin_name.'-font-awesome', WBDI_PLUGIN_URL . 'admin/css/font-awesome.min.css' );
			wp_enqueue_style( $this->plugin_name, WBDI_PLUGIN_URL . 'admin/css/wb-demo-installer-admin.css' );
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function wbdi_enqueue_scripts() {
		if( stripos( $_SERVER['REQUEST_URI'], $this->plugin_name ) ) {
			wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wb-demo-installer-admin.js', array( 'jquery' ) );
			wp_localize_script(
				$this->plugin_name,
				'wbdi_admin_js_object',
				array(
					'ajaxurl'		=> admin_url('admin-ajax.php')
				)
			);
		}
	}

	/**
	 * Actions performed to add an admin options page
	 */
	public function wbdi_demo_installer_page() {
		add_menu_page( __( 'WB Demo Installer', WBDI_TEXT_DOMAIN ), __( 'Demo Installer', WBDI_TEXT_DOMAIN ), 'manage_options', $this->plugin_name, array( $this, 'wbdi_installer_page_content' ), 'dashicons-media-text' );
		add_submenu_page( $this->plugin_name, __( 'Demo Installer Support', WBDI_TEXT_DOMAIN ), __( 'Support', WBDI_TEXT_DOMAIN ), 'manage_options', $this->plugin_name.'-support', array( $this, 'wbdi_support_page_content' ) );
	}

	/**
	 * Actions performed to create a options page content
	 */
	public function wbdi_installer_page_content() {
		$tab = isset($_GET['tab']) ? $_GET['tab'] : $this->plugin_name;
		?>
		<div class="wrap">
			<div class="wbdi-header">
				<h2 class="wbdi-plugin-heading"><?php _e( 'WB Demo Installer', WBDI_TEXT_DOMAIN );?></h2>
				<?php self::wbdi_plugin_extra_actions();?>
			</div>
			<?php $this->wbdi_plugin_settings_tabs();?>
			<?php do_settings_sections( $tab );?>
		</div> 
		<?php
	}

	/**
	 * Actions performed to set settings tabs
	 * These tabs will handle the complete import process
	 */
	public function wbdi_plugin_settings_tabs() {
		$current_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : $this->plugin_name;
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $this->plugin_settings_tabs as $tab_key => $tab_caption ) {
			$active = $current_tab == $tab_key ? 'nav-tab-active' : '';
			echo '<a class="nav-tab ' . $active . '" href="?page=' . $this->plugin_name . '&tab=' . $tab_key . '">' . $tab_caption . '</a>';
		}
		echo '</h2>';
	}

	/**
	 * Actions performed to manage the import process tabs
	 */
	public function wbdi_demo_installer_process_tabs() {
		//Installers
		$this->plugin_settings_tabs[ $this->plugin_name ] = __( 'Installers', WBDI_TEXT_DOMAIN );
		register_setting( $this->plugin_name, $this->plugin_name );
		add_settings_section( $this->plugin_name, ' ', array( &$this, 'wbdi_installers_content' ), $this->plugin_name );

		//Required Plugins
		$this->plugin_settings_tabs[ $this->plugin_name.'-plugins-required' ] = __( 'Plugins Required', WBDI_TEXT_DOMAIN );
		register_setting( $this->plugin_name.'-plugins-required', $this->plugin_name.'-plugins-required' );
		add_settings_section( $this->plugin_name.'-plugins-required-section', ' ', array( &$this, 'wbdi_plugins_required_content' ), $this->plugin_name.'-plugins-required' );

		//Import - The Final Step
		$this->plugin_settings_tabs[ $this->plugin_name.'-import' ] = __( 'Import', WBDI_TEXT_DOMAIN );
		register_setting( $this->plugin_name.'-import', $this->plugin_name.'-import' );
		add_settings_section( $this->plugin_name.'-import-section', ' ', array( &$this, 'wbdi_import_content' ), $this->plugin_name.'-import' );
	}

	/**
	 * Actions performed to manage the installers page
	 */
	public function wbdi_installers_content() {
		if ( file_exists( WBDI_PLUGIN_PATH . 'admin/includes/wbdi-installer.php' ) ) {
			require_once( WBDI_PLUGIN_PATH . 'admin/includes/wbdi-installer.php' );
		}
	}

	/**
	 * Actions performed to manage the required plugins page
	 */
	public function wbdi_plugins_required_content() {
		if ( file_exists( WBDI_PLUGIN_PATH . 'admin/includes/wbdi-plugins-required.php' ) ) {
			require_once( WBDI_PLUGIN_PATH . 'admin/includes/wbdi-plugins-required.php' );
		}
	}

	/**
	 * Actions performed to manage the import page
	 */
	public function wbdi_import_content() {
		if ( file_exists( WBDI_PLUGIN_PATH . 'admin/includes/wbdi-import.php' ) ) {
			require_once( WBDI_PLUGIN_PATH . 'admin/includes/wbdi-import.php' );
		}
	}

	/**
	 * Support Page Content
	 */
	public function wbdi_support_page_content(){
		?>
		<div class="wrap">
			<div class="wbdi-header">
				<h2 class="wbdi-plugin-heading"><?php _e( 'WB Demo Support', WBDI_TEXT_DOMAIN );?></h2>
				<?php self::wbdi_plugin_extra_actions();?>
			</div>
			<?php
			if ( file_exists( WBDI_PLUGIN_PATH . 'admin/includes/wbdi-support.php' ) ) {
				require_once( WBDI_PLUGIN_PATH . 'admin/includes/wbdi-support.php' );
			}
			?>
		</div> 
		<?php
	}

	public function wbdi_plugin_extra_actions() {
		?>
		<div class="wbdi-extra-actions">
			<button class="button button-secondary" onclick="window.open('https://wbcomdesigns.com/contact/', '_blank');"><i class="fa fa-envelope" aria-hidden="true"></i> <?php _e( 'Email Support', WBDI_TEXT_DOMAIN )?></button>
			<button disabled class="button button-secondary" onclick="window.open('', '_blank');"><i class="fa fa-file" aria-hidden="true"></i> <?php _e( 'User Manual', WBDI_TEXT_DOMAIN )?></button>
			<button disabled class="button button-secondary" onclick="window.open('', '_blank');"><i class="fa fa-star" aria-hidden="true"></i> <?php _e( 'Rate Us on WordPress.org', WBDI_TEXT_DOMAIN )?></button>
		</div>
		<?php 
	}

	/**
	 * AJAX served to install the plugin
	 */
	public function wbdi_plugin_install() {
		if( isset( $_POST['action'] ) && $_POST['action'] == 'wbdi_plugin_install' ) {
			$plugin_name			=	sanitize_text_field( $_POST['plugin_name'] );
			$plugin_download_url 	=	sanitize_text_field( $_POST['plugin_download_url'] );
			$plugin_slug			=	sanitize_text_field( $_POST['plugin_slug'] );

			$filenm = basename( $plugin_download_url );
			$args = array(
				'path'				=>	WP_PLUGIN_DIR.'/',
				'preserve_zip'		=>	false
			);

			$target = $args['path'].$filenm;

			$if_installed = self::wbdi_plugin_download( $plugin_download_url, $target );
			if( $if_installed ) {
				self::wbdi_plugin_unpack( $args, $target );
				self::wbdi_plugin_activate_now( $plugin_slug );

				$result = 'activated';
				$plugin_action = '--';
				$plugin_status = __( 'Active', WBDI_TEXT_DOMAIN );
				$message = __( 'Plugin Activated.', WBDI_TEXT_DOMAIN );

			} else {
				$result = 'not-installed';
				$plugin_action = __( 'Error <i class="fa fa-times"></i> <a href="javascript:void(0);" data-plugin="'.$plugin_name.'" data-downloadurl="'.$plugin_download_url.'" data-pluginslug="'.$plugin_slug.'" class="wbdi-plugin-install">Try Again!</a>', WBDI_TEXT_DOMAIN );
				$plugin_status = '';
				$message = __( 'Plugin Not Installed.', WBDI_TEXT_DOMAIN );
			}

			$response = array(
				'plugin_status'		=>	$plugin_status,
				'plugin_action'		=>	$plugin_action,
				'result'			=>	$result,
				'message'			=>	$message
			);
			wp_send_json_success( $response );
			die;
		}
	}

	/**
	 * Actions performed to download the plugin
	 */
	public static function wbdi_plugin_download( $dwnloadurl, $install_path ) {
		$con = file_get_contents($dwnloadurl);
		if( file_put_contents( $install_path, $con ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Actions performed to unpack the plugin
	 */
	public static function wbdi_plugin_unpack( $args, $target ) {
		if( $zip = zip_open( $target ) ) {
			while( $entry = zip_read( $zip ) ) {
				$is_file = substr( zip_entry_name( $entry ), -1 ) == '/' ? false : true;
				$file_path = $args['path'].zip_entry_name( $entry );
				if( $is_file ) {
					if( zip_entry_open( $zip, $entry,"r" ) ) {
					$fstream = zip_entry_read( $entry, zip_entry_filesize( $entry ) );
						file_put_contents( $file_path, $fstream );
						chmod( $file_path, 0777 );
					}
					zip_entry_close( $entry );
				} else {
					if( zip_entry_name( $entry ) ) {
						mkdir($file_path);
						chmod($file_path, 0777);
					}
				}
			}
			zip_close($zip);
		}
		if( $args['preserve_zip'] === false ) {
			unlink( $target );
		}
		return;
	}

	/**
	 * Actions performed to activate the plugin
	 */
	public static function wbdi_plugin_activate_now( $plugin_slug ) {
		$current_active_plugins = get_option( 'active_plugins' );
		$plugin = plugin_basename( trim( $plugin_slug ) );
		$current_active_plugins[] = $plugin;
		sort( $current_active_plugins );
		do_action( 'activate_plugin', trim( $plugin ) );
		update_option( 'active_plugins', $current_active_plugins );
		do_action( 'activate_'.trim( $plugin ) );
		do_action( 'activated_plugin', trim( $plugin ) );
		return;
	}

	/**
	 * AJAX served to activate the plugin
	 */
	public function wbdi_plugin_activate() {
		if( isset( $_POST['action'] ) && $_POST['action'] == 'wbdi_plugin_activate' ) {
			$plugin = sanitize_text_field( $_POST['plugin'] );
			self::wbdi_plugin_activate_now( $plugin );
			$response = array(
				'plugin_status' => __( 'Active', WBDI_TEXT_DOMAIN ),
				'plugin_action' => '--',
				'message' => __( 'Plugin Activated.', WBDI_TEXT_DOMAIN )
			);
			wp_send_json_success( $response );
			die;
		}
	}

	/**
	 * AJAX served to import the demo data
	 */
	public function wbdi_import_demo() {
		if( isset( $_POST['action'] ) && $_POST['action'] == 'wbdi_import_demo' ) {
			$file_url	= sanitize_text_field( $_POST['file_url'] );
			$response = wp_remote_get( esc_url_raw( $file_url ) );
			$response_code = wp_remote_retrieve_response_code( $response );
			if( $response_code == 200 ) {
				$file_data = json_decode( $response['body'] );
				$export_data_home_url = $file_data->home_url;
				// print_r( $file_data ); die;
				/**
				 * First import all the users,
				 * importing the data and trributing it would be easier
				 */
				$users = $file_data->users;
				if( !empty( $users ) ) {
					// self::wbdi_import_users( $users );
				}

				/**
				 * Import the site settings
				 */
				$site_options = $file_data->site_options;
				if( !empty( $site_options ) ) {
					// self::wbdi_import_site_options( unserialize( $site_options ) );
				}

				/**
				 * Check, if BuddyPress is active
				 * Import the buddypress groups and activity
				 */
				if( WBDI_IS_BP_ACTIVE ) {
					$groups = $file_data->groups;
					if( !empty( $groups ) ) {
						// self::wbdi_import_groups( $groups );
					}
					
					$activities = $file_data->activity;
					if( !empty( $activities ) ) {
						// self::wbdi_import_activity( $activities, $export_data_home_url );
					}
				}
				
				/**
				 * Import the taxonomies
				 */
				$taxonomies = $file_data->taxonomies;
				if( !empty( $taxonomies ) ) {
					// self::wbdi_import_taxonomies( $taxonomies );
				}
				
				/**
				 * Import the posts from post types
				 */
				$post_types = $file_data->post_types;
				if( !empty( $post_types ) ) {
					self::wbdi_import_post_types( $post_types, $export_data_home_url );
				}				

				$message = __( 'Demo Data Imported.', WBDI_TEXT_DOMAIN );
				$data_import_result = 'true';
			} else {
				$data_import_result = 'false';
				$message = __( 'Demo Data Not Imported.', WBDI_TEXT_DOMAIN );
			}

			$response = array(
				'message' => $message,
				'data_import_result' => $data_import_result
			);
			wp_send_json_success( $response );
			die;
		}
	}

	/**
	 * Actions performed to import demo users
	 */
	public static function wbdi_import_users( $users ) {
		global $wpdb;
		$usrtbl = $wpdb->prefix . 'users';
		$usr_metatbl = $wpdb->prefix . 'usermeta';
		/**
		 * Wife off the existing data in users table
		 */
		$wpdb->query( $wpdb->prepare( "DELETE FROM $usrtbl WHERE `ID` <> %d", 1 ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $usr_metatbl WHERE `user_id` <> %d", 1 ) );

		/**
		 * Start adding the new data
		 */
		foreach( $users as $user ) {
			set_time_limit( 60 );
			$user_data = unserialize( $user->data );
			$user_metadata = unserialize( $user->meta_data );
			
			$email = $user_data->data->user_email;
			$username = $user_data->data->user_login;

			$email_exists = email_exists( $email );
			$username_exists = username_exists( $username );

			if( gettype( $email_exists ) == 'boolean' && gettype( $username_exists ) == 'boolean' ) {
				$default_role = get_option('default_role');

				/**
				 * Create the new user
				 */
				$roles = $user_data->roles;
				$user_id = wp_create_user( $username, '1234', $email );\
				wp_update_user(
					array(
						'ID' => $user_id,
						'role' => $roles[0]
					)
				);

				// Update the original password
				$wpdb->update(
					$usrtbl,
					array(
						'user_pass' => $user_data->data->user_pass,
						'user_activation_key' => ''
					),
					array( 'ID' => $user_id )
				);

				//Update the usermeta
				if( !empty( $user_metadata ) ) {
					foreach( $user_metadata as $metakey => $metadata ) {
						$mdata = $metadata[0];
						update_user_meta( $user_id, $metakey, $mdata );
					}
					$wp_capabilities = array(
						$roles[0] => 1
					);
					update_user_meta( $user_id, 'wp_capabilities', $wp_capabilities );
				}
			}
		}
		return;
	}

	/**
	 * Actions performed to import demo site options
	 */
	public static function wbdi_import_site_options( $site_options ) {
		set_time_limit( 120 );
		foreach( $site_options as $key => $option ) {
			update_option( $key, $option );
		}
		return;
	}

	/**
	 * Actions performed to import demo groups
	 */
	public static function wbdi_import_groups( $groups ) {
		global $wpdb;
		$grptbl = $wpdb->prefix . 'bp_groups';
		$grp_metatbl = $wpdb->prefix . 'bp_groups_groupmeta';
		/**
		 * Wife off the existing data in groups table
		 */
		$wpdb->query( $wpdb->prepare( "DELETE FROM $grptbl WHERE `id` >= %d", 1 ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $grp_metatbl WHERE `group_id` >= %d", 1 ) );

		/**
		 * Start adding the new data
		 */
		foreach( $groups as $group ) {
			$creator = get_user_by( 'email', $group->creator_email );
			$data = unserialize( $group->data );
			$meta_data = unserialize( $group->meta_data );
			
			$ttl_member = 1;
			if( isset( $meta_data['total_member_count'] ) ) {
				$ttl_member = $meta_data['total_member_count'][0];
			}

			//Create group
			$new_group = new BP_Groups_Group;
			$new_group->creator_id = $creator->ID;
			$new_group->name = $data->name;
			$new_group->slug = $data->slug;
			$new_group->description = $data->description;
			$new_group->status = $data->status;
			$new_group->is_invitation_only = 1;
			$new_group->enable_wire = 1;
			$new_group->enable_forum = $data->enable_forum;
			$new_group->enable_photos = 1;
			$new_group->photos_admin_only = 1;
			$new_group->date_created = current_time('mysql');
			$new_group->total_member_count = $ttl_member;
			$saved = $new_group->save();
			$grpid = $new_group->id;

			//Update the group meta
			if( !empty( $meta_data ) ) {
				foreach( $meta_data as $key => $mdata ) {
					groups_update_groupmeta( $grpid, $key, $mdata[0] );
				}
			}
		}
		return;
	}

	/**
	 * Actions performed to import demo activity
	 */
	public static function wbdi_import_activity( $activities, $export_data_home_url ) {
		set_time_limit( 30 );
		$homeurl = get_home_url();

		global $wpdb;
		$actvty_tbl = $wpdb->prefix . 'bp_activity';
		$actvty_metatbl = $wpdb->prefix . 'bp_activity_meta';
		/**
		 * Wife off the existing data in activity table
		 */
		$wpdb->query( $wpdb->prepare( "DELETE FROM $actvty_tbl WHERE `id` >= %d", 1 ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $actvty_metatbl WHERE `activity_id` >= %d", 1 ) );

		/**
		 * Start adding the new data
		 */
		foreach( $activities as $activity ) {
			$user = get_user_by( 'email', $group->user_email );
			$data = unserialize( $activity->data );
			$meta_data = unserialize( $activity->meta_data );

			$args = array(
				'item_id'				=> $data->item_id,
				'secondary_item_id'		=> $data->secondary_item_id,
				'user_id'				=> $user->ID,
				'primary_link'			=> str_replace( $export_data_home_url, $homeurl, $data->primary_link ),
				'component'				=> $data->component,
				'type'					=> $data->type,
				'action'				=> str_replace( $export_data_home_url, $homeurl, $data->action ),
				'content'				=> str_replace( $export_data_home_url, $homeurl, $data->content ),
				'date_recorded'			=> $data->date_recorded,
				'hide_sitewide'			=> $data->hide_sitewide,
				'mptt_left'				=> $data->mptt_left,
				'mptt_right'			=> $data->mptt_right,
				'is_spam'				=> $data->is_spam
			);
			$activity_id = bp_activity_add( $args );

			//Update the activity meta
			if( !empty( $meta_data ) ) {
				foreach( $meta_data as $key => $mdata ) {
					bp_activity_update_meta( $activity_id, $key, $mdata[0] );
				}
			}
		}
		return;
	}

	/**
	 * Actions performed to import demo taxonomies
	 */
	public static function wbdi_import_taxonomies( $taxonomies ) {
		set_time_limit( 30 );

		foreach( $taxonomies as $taxonomy => $terms ) {
			if( taxonomy_exists( $taxonomy ) ) {
				/**
				 * Delete the pre existing terms in the taxonomy
				 */
				$ex_terms = get_terms( $taxonomy, array( 'fields' => 'ids', 'hide_empty' => false ) );
				if( !empty( $ex_terms ) ) {
					foreach( $ex_terms as $term ) {
						wp_delete_term( $term, $taxonomy );
					}
				}

				/**
				 * Start adding the new taxonomies in the demo data
				 */
				$terms = unserialize( $terms );
				if( !empty( $terms ) ) {
					foreach( $terms as $term ) {
						$term_exists = term_exists( $term['name'], $taxonomy );
						if ( $term_exists == 0 || $term_exists == null) {
							if( $term['parent'] == '' ) {
								$trm_args = array(
									'description' => $term['description'],
									'slug' => $term['slug']
								);
								wp_insert_term( $term['name'], $taxonomy, $trm_args );
							} else {
								$new_parent = get_term_by( 'name', $term['parent'], $taxonomy );
								$trm_args = array(
									'description' => $term['description'],
									'slug' => $term['slug'],
									'parent' => $new_parent->term_id
								);
								wp_insert_term( $term['name'], $taxonomy, $trm_args );
							}
						}
					}
				}
			}
		}
		return;
	}

	/**
	 * Actions performed to import demo post types
	 */
	public static function wbdi_import_post_types( $post_types, $export_data_home_url ) {
		set_time_limit( 90 );
		$homeurl = get_home_url();
		global $wpdb;
		$poststbl = $wpdb->prefix . 'posts';
		$posts_metatbl = $wpdb->prefix . 'postmeta';
		/**
		 * Wife off the pre existing posts and meta data of the post type
		 */
		$wpdb->query( $wpdb->prepare( "DELETE FROM $poststbl WHERE `ID` >= %d", 1 ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $posts_metatbl WHERE `meta_id` >= %d", 1 ) );

		/**
		 * Start creating new posts
		 */
		foreach( $post_types as $post_type => $posts ) {
			if ( post_type_exists( $post_type ) ) {
				if( !empty( $posts ) ) {
					foreach( $posts as $post ) {
						set_time_limit( 30 );
						$data = unserialize( $post->data );
						$metadata = unserialize( $post->meta_data );
						$author_email = $post->author_email;
						$author = get_user_by( 'email', $author_email );

						$pid = wp_insert_post( array(
							'post_author' => $author->ID,
							'post_content' => str_replace( $export_data_home_url, $homeurl, $data->post_content ),
							'post_title' => $data->post_title,
							'post_excerpt' => $data->post_excerpt,
							'post_status' => $data->post_status,
							'post_type' => $data->post_type,
							'comment_status' => $data->comment_status,
							'post_password' => $data->post_password,
							'post_name' => $data->post_name,
							'guid' => str_replace( $export_data_home_url, $homeurl, $data->guid ),
							'post_category' => $cats
						) );

						Wb_Demo_Installer::wbdi_set_post_thumbnail( $post->attachment_url, $pid );

						$terms = unserialize( $post->terms );
						$cats = array();
						if( !empty( $terms ) ) {
							$taxonomy = $terms[0]->taxonomy;
							foreach( $terms as $term ) {
								$cats[] = $term->name;
							}
							wp_set_object_terms( $pid, $cats, $taxonomy );
						}

						if( !empty( $metadata ) ) {
							foreach( $metadata as $key => $mdata ) {
								update_post_meta( $pid, $key, $mdata[0] );
							}
						}
					}
				}
			}
		}
		return;
	}

}
<?php

GFForms::include_feed_addon_framework();

class GFBentoAddOn extends GFFeedAddOn {

	protected $_version = GF_BENTO_ADDON_VERSION;
	protected $_min_gravityforms_version = '1.9.12';
	protected $_slug = 'gf-bento';
	protected $_path = 'gf-bento/gravity-forms.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Add-On for Gravity Forms + Bento';
	protected $_short_title = 'Bento';
	protected $_baseurl =  'https://bentonow.com';
	protected $_apiurl = 'https://app.bentonow.com/api/v1/batch/events';

	private static $_instance = null;

	public static function get_instance() {
		
		if ( self::$_instance == null ) {
			self::$_instance = new GFBentoAddOn();
		}

		return self::$_instance;
		
	}

	public function init() {

		parent::init();

		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Subscribe contact to Bento only when payment is received.', 'gf-bento-addon' )
			)
		);
		
	}

	public function process_feed( $feed, $entry, $form ) {
		
		$feedName  = $feed['meta']['feed_name'];
		$tags  = $feed['meta']['feed_tags'];

		$field_map = $this->get_field_map_fields( $feed, 'mapped_fields' );
		$merge_vars = array();

		foreach ( $field_map as $name => $field_id ) {

			$merge_vars[ $name ] = $this->get_field_value( $form, $entry, $field_id );

		}

		$email = $merge_vars['email'];
		
		$fields = array();		
		
		if( !empty( $merge_vars['first_name'] ) )
			$fields['first_name'] = $merge_vars['first_name'];
		
		if( !empty( $merge_vars['last_name'] ) )
			$fields['last_name'] = $merge_vars['last_name'];
		
		$custom_field_map = $this->get_dynamic_field_map_fields( $feed, 'custom_fields' );
		
		foreach ( $custom_field_map as $key => $field_id ) {
			
			$val = $this->get_field_value( $form, $entry, $field_id );
			if( !empty( $val ) ) {
				if( strlen( $val ) < 281 ) {
					$fields[ $key ] = $val;	
				} else {
					$fields[ $key ] = substr( $val, 0, 280 );
					$this->add_feed_error( 'Custom field ' . $key . ': value was truncated, exceeds maximum string length of 280.', $feed, $entry, $form );		
				}
			}
			
		}
		
		$response = $this->subscribe( '$subscribe', $email, $fields, $tags );
		
		if( $response != 200 ) {

			$this->add_feed_error( 'Bento subscription failed. Response code ' . $response, $feed, $entry, $form );
			
		} else {

			$this->add_note( $entry['id'], 'Subscribed to Bento via ' . $feedName );

		}

	}
	
	private function subscribe( $event, $email, $fields, $tags ) {
		
		$settings = $this->get_plugin_settings();
		$site_key = rgar( $settings, 'bento_site_key' );
		$pub_key = rgar( $settings, 'bento_pub_key' );
		$sec_key = rgar( $settings, 'bento_sec_key' );
		
		$events = array(
			array(
				'email' => $email,
				'type' => '$subscribe',
				'fields' => $fields
			)
		);
		
		if( $tags ) {
			
			$tags = explode( ',', $tags );
			
			foreach( $tags as $tag ) {
			
				$events[] = array(
					'email' => $email,
					'type' => '$tag',
					'details' => array(
						'tag' => $tag
					)
				);
			
			}
			
		}
		
		$data = [
		  'site_uuid' => $site_key,
		  'events' => $events		  
		];
		
		$data = apply_filters( 'gf_bento_event_data', $data );
		
		$args = array(
			'body' => json_encode( $data ),
			'data_format' => 'body',
			'timeout' => '5',
			'redirection' => '5',
			'httpversion' => '1.0',
			'blocking' => true,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $pub_key . ':' . $sec_key ),
				'Content-Type' => 'application/json'
			),
		);

		$response = wp_remote_post( $this->_apiurl, $args );
		
		return $response['response']['code'];
	 
	}
	
	public function plugin_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Bento Add-On Settings', 'gf-rejoiner-addon' ),
				'fields' => array(
					array(
						'name'    => 'bento_site_key',
						'tooltip' => esc_html__( 'Site key from Bento control panel', 'gf-bento-addon' ),
						'label'   => esc_html__( 'Site Key', 'gf-bento-addon' ),
						'type'    => 'text',
						'class'   => 'small',
					),
					array(
						'name'    => 'bento_pub_key',
						'tooltip' => esc_html__( 'Publishable key from Bento control panel', 'gf-bento-addon' ),
						'label'   => esc_html__( 'Publishable Key', 'gf-bento-addon' ),
						'type'    => 'text',
						'class'   => 'small',
					),
					array(
						'name'    => 'bento_sec_key',
						'tooltip' => esc_html__( 'Secret key from Bento control panel', 'gf-bento-addon' ),
						'label'   => esc_html__( 'Secret Key', 'gf-bento-addon' ),
						'type'    => 'text',
						'class'   => 'small',
					)
				),
			),
		);
	}

	public function feed_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Bento Feed Settings', 'gf-bento-addon' ),
				'fields' => array(
					array(
						'label'   => esc_html__( 'Feed name', 'gf-bento-addon' ),
						'type'    => 'text',
						'name'    => 'feed_name',
						'tooltip' => esc_html__( 'This is the name of the feed', 'gf-bento-addon' ),
						'class'   => 'small',
					),
					array(
						'label'   => esc_html__( 'Tags', 'gf-bento-addon' ),
						'type'    => 'text',
						'name'    => 'feed_tags',
						'tooltip' => esc_html__( 'Comma separated list of tags for this feed', 'gf-bento-addon' ),
					),
					array(
						'name'      => 'mapped_fields',
						'label'     => esc_html__( 'Map Fields', 'gf-bento-addon' ),
						'type'      => 'field_map',
						'field_map' => array(
							array(
								'name'       => 'email',
								'label'      => esc_html__( 'Email', 'gf-bento-addon' ),
								'required'   => 1,
								'field_type' => array( 'email', 'hidden' ),
							),
							array(
								'name'     => 'first_name',
								'label'    => esc_html__( 'First Name', 'gf-bento-addon' ),
								'required' => 0,
							),
							array(
								'name'     => 'last_name',
								'label'    => esc_html__( 'Last Name', 'gf-bento-addon' ),
								'required' => 0,
							),
						),
					),
					array(
						'name'                => 'custom_fields',
						'label'               => esc_html__( 'Custom Fields', 'gf-bento-addon' ),
						'type'                => 'dynamic_field_map',
						'limit'               => 20,
						'tooltip'             => '<h6>' . esc_html__( 'Custom Fields', 'gf-bento-addon' ) . '</h6>' . esc_html__( 'You may send custom meta information to Bento. A maximum of 20 custom keys may be sent. The key name must be 80 characters or less, and the mapped data will be truncated to 280 characters per requirements by Bento. ', 'gf-bento-addon' ),
						'validation_callback' => array( $this, 'validate_custom_fields' ),
					),
					array(
						'name'           => 'condition',
						'label'          => esc_html__( 'Condition', 'gf-bento-addon' ),
						'type'           => 'feed_condition',
						'checkbox_label' => esc_html__( 'Enable Condition', 'gf-bento-addon' ),
						'instructions'   => esc_html__( 'Process this feed if', 'gf-bento-addon' ),
					),
				),
			),
		);
	}

	public function validate_custom_fields( $field ) {
	  
		$settings = $this->get_posted_settings();
		$custom_fields = $settings['custom_fields'];
	  
		if( empty( $custom_fields ) ) {
			return;
		}
	  
		$custom_fields_count = count( $custom_fields );
		if ( $custom_fields_count > 20 ) {
			$this->set_field_error( array( esc_html__( 'You may only have 20 custom keys.' ), 'gf-bento-addon' ) );
			return;
		}
	  
		foreach ( $custom_fields as $meta ) {
			if ( empty( $meta['custom_key'] ) && ! empty( $meta['value'] ) ) {
				$this->set_field_error( array( 'name' => 'custom_fields' ), esc_html__( "A field has been mapped to a custom key without a name. Please enter a name for the custom key, remove the metadata item, or return the corresponding drop down to 'Select a Field'.", 'gf-bento-addon' ) );
				break;
			} elseif ( strlen( $meta['custom_key'] ) > 80 ) {
				$this->set_field_error( array( 'name' => 'custom_fields' ), sprintf( esc_html__( 'The name of custom key %s is too long. Please shorten this to 40 characters or less.', 'gf-bento-addon' ), $meta['custom_key'] ) );
				break;
			}
		}
		
	}
	
	public function feed_list_columns() {
		return array(
			'feed_name'  => esc_html__( 'Name', 'gf-bento-addon' ),
			'feed_tags'  => esc_html__( 'Tags', 'gf-bento-addon' )
		);
	}

	public function can_create_feed() {
		
		$settings = $this->get_plugin_settings();
		$site_key = rgar( $settings, 'bento_site_key' );
		$pub_key = rgar( $settings, 'bento_pub_key' );
		$sec_key = rgar( $settings, 'bento_sec_key' );
		
		if( $site_key && $pub_key && $sec_key )
			return true;
		else
			return false;

	}

}

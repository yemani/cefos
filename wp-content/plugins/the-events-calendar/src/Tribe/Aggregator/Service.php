<?php
// Don't load directly
defined( 'WPINC' ) or die;

class Tribe__Events__Aggregator__Service {
	/**
	 * @var Tribe__Events__Aggregator__Service Event Aggregator Service class
	 */
	protected static $instance;

	/**
	 * @var Tribe__Events__Aggregator Event Aggregator object
	 */
	protected $aggregator;

	/**
	 * @var object
	 */
	protected $origins = false;

	/**
	 * Codes and strings from the EA Service. These only exist here so that they can be translated
	 * @var array
	 */
	private $service_messages = array();

	/**
	 * API varibles stored in a single Object
	 *
	 * @var array $api {
	 *     @type string     $key         License key for the API (PUE)
	 *     @type string     $version     Which version of we are dealing with
	 *     @type string     $domain      Domain in which the API lies
	 *     @type string     $path        Path of the API on the domain above
	 * }
	 */
	public $api = array(
		'key' => null,
		'version' => 'v1',
		'domain' => 'https://ea.theeventscalendar.com/',
		'path' => 'api/aggregator/',
	);

	/**
	 * @var Tribe__Events__Aggregator__API__Requests
	 */
	protected $requests;

	/**
	 * Static Singleton Factory Method
	 *
	 * @return Tribe__Events__Aggregator__Service
	 */
	public static function instance() {
		return tribe( 'events-aggregator.service' );
	}

	/**
	 * Constructor!
	 */
	public function __construct( Tribe__Events__Aggregator__API__Requests $requests ) {
		$this->register_messages();
		$this->requests = $requests;
	}

	/**
	 * Create a clean way of fetching API variables
	 *
	 * @return stdClass|WP_Error
	 */
	public function api() {
		// Make it an Object
		$api = (object) $this->api;

		if ( defined( 'EVENT_AGGREGATOR_API_BASE_URL' ) ) {
			$api->domain = EVENT_AGGREGATOR_API_BASE_URL;
		}

		// Since we don't need to fetch this key elsewhere
		$api->key = get_option( 'pue_install_key_event_aggregator' );
		if ( is_multisite() ) {
			$network_key = get_network_option( null, 'pue_install_key_event_aggregator' );
			$api->key = ! empty( $api->key ) && $network_key !== $api->key ? $api->key : $network_key;
		}

		/**
		 * Creates a clean way to filter and redirect to another API domain/path
		 * @var stdClass
		 */
		$api = (object) apply_filters( 'tribe_aggregator_api', $api );

		// The user doesn't have a license key
		if ( empty( $api->key ) ) {
			return tribe_error( 'core:aggregator:invalid-service-key' );
		}

		$aggregator = tribe( 'events-aggregator.main' );
		$plugin_name = $aggregator->filter_pue_plugin_name( '', 'event-aggregator' );

		$pue_notices = Tribe__Main::instance()->pue_notices();
		$has_notice = $pue_notices->has_notice( $plugin_name );

		// The user doesn't have a valid license key
		if ( empty( $api->key ) || $has_notice ) {
			return tribe_error( 'core:aggregator:invalid-service-key' );
		}

		return $api;
	}

	/**
	 * Builds an endpoint URL
	 *
	 * @param string $endpoint  Endpoint for the Event Aggregator service
	 * @param array  $data      Parameters to add to the URL
	 *
	 * @return string|WP_Error
	 */
	public function build_url( $endpoint, $data = array() ) {
		$api = $this->api();

		// If we have an WP_Error we return it here
		if ( is_wp_error( $api ) ) {
			return $api;
		}

		// Build the URL
		$url = "{$api->domain}{$api->path}{$api->version}/{$endpoint}";

		// Enforce Key on the Query Data
		$data['key'] = $api->key;

		// If we have data we add it
		$url = add_query_arg( $data, $url );

		return $url;
	}

	/**
	 * Performs a GET request against the Event Aggregator service
	 *
	 * @param string $endpoint   Endpoint for the Event Aggregator service
	 * @param array  $data       Parameters to send to the endpoint
	 *
	 * @return stdClass|WP_Error
	 */
	public function get( $endpoint, $data = array() ) {
		$url = $this->build_url( $endpoint, $data );

		// If we have an WP_Error we return it here
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		/**
		 * Length of time to wait when initially connecting to Event Aggregator before abandoning the attempt.
		 * default is 60 seconds. We set this high so large files can be transfered on slow connections
		 *
		 * @var int $timeout_in_seconds
		 */
		$timeout_in_seconds = (int) apply_filters( 'tribe_aggregator_connection_timeout', 60 );

		$response = $http_response = $this->requests->get(
			esc_url_raw( $url ),
			array( 'timeout' => $timeout_in_seconds )
		);

		if ( is_wp_error( $response ) ) {
			if ( isset( $response->errors['http_request_failed'] ) ) {
				$response->errors['http_request_failed'][0] = __( 'Connection timed out while transferring the feed. If you are dealing with large feeds you may need to customize the tribe_aggregator_connection_timeout filter.', 'the-events-calendar' );
			}

			return $response;
		}

		if ( 403 == wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error(
				'core:aggregator:request-denied',
				esc_html__( 'Event Aggregator server has blocked your request. Please try your import again later or contact support to know why.', 'the-events-calendar' )
			);
		}

		// we know it is not a 404 or 403 at this point
		if ( 200 != wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error(
				'core:aggregator:bad-response',
				esc_html__( 'There may be an issue with the Event Aggregator server. Please try your import again later.', 'the-events-calendar' )
			);
		}

		if ( isset( $response->data ) && isset( $response->data->status ) && '404' === $response->data->status ) {
			return new WP_Error(
				'core:aggregator:daily-limit-reached',
				esc_html__( 'There may be an issue with the Event Aggregator server. Please try your import again later.', 'the-events-calendar' )
			);
		}

		// if the response is not an image, let's json decode the body
		if ( ! preg_match( '/image/', $response['headers']['content-type'] ) ) {
			$response = json_decode( wp_remote_retrieve_body( $response ) );
		}

		// It's possible that the json_decode() operation will have failed
		if ( null === $response ) {
			return new WP_Error(
				'core:aggregator:bad-json-response',
				esc_html__( 'The response from the Event Aggregator server was badly formed and could not be understood. Please try again.', 'the-events-calendar' ),
				$http_response
			);
		}

		return $response;
	}

	/**
	 * Performs a POST request against the Event Aggregator service
	 *
	 * @param string $endpoint   Endpoint for the Event Aggregator service
	 * @param array  $data       Parameters to send to the endpoint
	 *
	 * @return stdClass|WP_Error
	 */
	public function post( $endpoint, $data = array() ) {
		$url = $this->build_url( $endpoint );

		// If we have an WP_Error we return it here
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		if ( empty( $data['body'] ) ) {
			$args = array( 'body' => $data );
		} else {
			$args = $data;
		}

		$response = wp_remote_post( esc_url_raw( $url ), $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		return $response;
	}

	/**
	 * Fetch origins from service
	 *
	 * @param bool $return_error Whether response errors should be returned, if any.
	 *
	 * @return array The origins array of an array containing the origins first and an error second if `return_error` is set to `true`.
	 */
	public function get_origins( $return_error = false ) {
		$origins = $this->get_default_origins();

		$response = $this->get( 'origin' );
		$error = null;

		// If we have an WP_Error or a bad response we return only CSV and set some error data
		if ( is_wp_error( $response ) || empty( $response->status ) ) {
			$error = $response;

			return $return_error ? array( $origins, $error ) : $origins;
		}

		if ( $response && 'success' === $response->status ) {
			$origins = array_merge( $origins, (array) $response->data );
		}

		return $return_error
			? array( $origins, $error )
			: $origins;
	}

	/**
	 * Fetch Facebook Extended Token from the Service
	 *
	 * @return array
	 */
	public function get_facebook_token() {
		$args = array(
			'referral' => urlencode( home_url() ),
		);
		$response = $this->get( 'facebook/token', $args );

		// If we have an WP_Error we return only CSV
		if ( is_wp_error( $response ) ) {
			return tribe_error( 'core:aggregator:invalid-facebook-token', array(), array( 'response' => $response ) );
		}

		return $response;
	}

	/**
	 * Fetch import data from service
	 *
	 * @param string   $import_id   ID of the Import Record
	 *
	 * @return stdClass|WP_Error
	 */
	public function get_import( $import_id, $data = array() ) {
		$response = $this->get( 'import/' . $import_id, $data );

		return $response;
	}

	/**
	 * Creates an import
	 *
	 * Note: This method exists because WordPress by default doesn't allow multipart/form-data
	 *       with boundaries to happen
	 *
	 * @param array $args {
	 *     Array of arguments. See REST docs for details. 1 exception listed below:
	 *
	 *     @type array $source_file Source file array using the $_FILES array values
	 * }
	 *
	 * @return string
	 */
	public function post_import( $args ) {
		$api = $this->api();

		// if the user doesn't have a license key, don't bother hitting the service
		if ( is_wp_error( $api ) ) {
			return $api;
		}

		$request_args = array(
			'body' => $args,
		);

		if ( isset( $args['file'] ) ) {
			$boundary = wp_generate_password( 24 );
			$headers = array(
				'content-type' => 'multipart/form-data; boundary=' . $boundary,
			);

			$payload = array();
			foreach ( $args as $name => $value ) {
				if ( 'file' === $name ) {
					continue;
				}

				if ( 'source' === $name ) {
					continue;
				}

				$payload[] = '--' . $boundary;
				$payload[] = 'Content-Disposition: form-data; name="' . $name . '"'. "\r\n";
				$payload[] = $value;
			}

			$file_path = null;
			$file_name = null;

			if ( is_numeric( $args['file'] ) ) {
				$file_id = absint( $args['file'] );
				$file_path = get_attached_file( $file_id );

				if ( ! file_exists( $file_path ) ) {
					$file_path = null;
				} else {
					$file_name = basename( $file_path );
				}
			} elseif ( ! empty( $args['file']['tmp_name'] ) && ! empty( $args['file']['name'] ) ) {
				if ( file_exists( $args['file']['tmp_name'] ) ) {
					$file_path = $args['file']['tmp_name'];
					$file_name = basename( $args['file']['name'] );
				}
			}

			if ( $file_path && $file_name ) {
				$payload[] = '--' . $boundary;
				$payload[] = 'Content-Disposition: form-data; name="source"; filename="' . $file_name . '"' . "\r\n";
				$payload[] = file_get_contents( $file_path );
				$payload[] = '--' . $boundary . '--';
			}

			$args = array(
				'headers' => $headers,
				'body' => implode( "\r\n", $payload ),
			);
		} else {
			$args = $request_args;
		}

		$response = $this->post( 'import', $args );
		return $response;
	}

	/**
	 * Fetches an image from the Event Aggregator service
	 *
	 * @param string $image_id Image ID to fetch
	 *
	 * @return stdClass|WP_Error
	 */
	public function get_image( $image_id ) {
		$response = $this->get( 'image/' . $image_id );

		return $response;
	}

	/**
	 * Returns a service message based on key
	 *
	 * @param string $key     Service Message index
	 * @param array  $args    An array of arguments that will be fed to a `sprintf` like function to replace
	 *                        placeholders.
	 * @param string $default A default message that should be returned should the message code not be found; defaults
	 *                        to the unknown message.
	 *
	 * @return string
	 */
	public function get_service_message( $key, $args = array(), $default = null ) {
		if ( empty( $this->service_messages[ $key ] ) ) {
			return ! empty( $default ) ? $default : $this->get_unknow_message();
		}

		return vsprintf( $this->service_messages[ $key ], $args );
	}

	/**
	 * Returns usage limits
	 *
	 * @param string $type Type of limits to return
	 * @param boolean $ignore_cache Whether or not cache should be ignored when fetching the value
	 *
	 * @return array
	 */
	public function get_limit( $type, $ignore_cache = false ) {
		if ( false === $this->origins || $ignore_cache ) {
			$origins = (object) $this->get_origins();
			$this->origins = $origins;
		}

		if ( ! isset( $origins->limit->$type ) ) {
			return 0;
		}

		return $origins->limit->$type;
	}

	/**
	 * Returns limit usage
	 *
	 * @param string $type Type of usage to return
	 * @param boolean $ignore_cache Whether or not cache should be ignored when fetching the value
	 *
	 * @return array
	 */
	public function get_usage( $type, $ignore_cache = false ) {
		static $origins;

		if ( ! $origins || $ignore_cache ) {
			$origins = (object) $this->get_origins();
		}

		if ( ! isset( $origins->usage->$type ) ) {
			return array(
				'used' => 0,
				'remaining' => 0,
			);
		}

		return $origins->usage->$type;
	}

	/**
	 * Returns whether or not the limit has been exceeded
	 *
	 * @param boolean $ignore_cache Whether or not cache should be ignored when fetching the value
	 *
	 * @return boolean
	 */
	public function is_over_limit( $ignore_cache = false ) {
		$limits = $this->get_usage( 'import', $ignore_cache );

		return isset( $limits->remaining ) && 0 >= $limits->remaining;
	}

	/**
	 * Returns the currently used imports for the day
	 *
	 * @param boolean $ignore_cache Whether or not cache should be ignored when fetching the value
	 *
	 * @return int
	 */
	public function get_limit_usage( $ignore_cache = false ) {
		$limits = $this->get_usage( 'import', $ignore_cache );

		if ( isset( $limits->used ) ) {
			return $limits->used;
		}

		return 0;
	}

	/**
	 * Returns the remaining imports for the day
	 *
	 * @param boolean $ignore_cache Whether or not cache should be ignored when fetching the value
	 *
	 * @return int
	 */
	public function get_limit_remaining( $ignore_cache = false ) {
		$limits = $this->get_usage( 'import', $ignore_cache );

		if ( isset( $limits->remaining ) ) {
			return $limits->remaining;
		}

		return 0;
	}

	/**
	 * Registers the message map used to translate message slugs returned from EA service into localized strings.
	 *
	 * These messages are delivered by the EA service and don't need to be registered. They just need to exist
	 * here so that they can be translated.
	 */
	protected function register_messages() {
		$this->service_messages = array(
			'error:create-import-failed' => __( 'Sorry, but something went wrong. Please try again.', 'the-events-calendar' ),
			'error:create-import-invalid-params' => __( 'Events could not be imported. The import parameters were invalid.', 'the-events-calendar' ),
			'error:fb-permissions' => __( 'Events cannot be imported because Facebook has returned an error. This could mean that the event ID does not exist, the event or source is marked as Private, or the event or source has been otherwise restricted by Facebook. You can <a href="https://theeventscalendar.com/knowledgebase/import-errors/" target="_blank">read more about Facebook restrictions in our knowledgebase</a>.', 'the-events-calendar' ),
			'error:fb-no-results' => __( 'No upcoming Facebook events found.', 'the-events-calendar' ),
			'error:fetch-404' => __( 'The URL provided could not be reached.', 'the-events-calendar' ),
			'error:fetch-failed' => __( 'The URL provided failed to load.', 'the-events-calendar' ),
			'error:get-image' => __( 'The image associated with your event could not be imported.', 'the-events-calendar' ),
			'error:get-image-bad-association' => __( 'The image associated with your event is not accessible with your API key.', 'the-events-calendar' ),
			'error:import-failed' => __( 'The import failed for an unknown reason. Please try again. If the problem persists, please contact support.', 'the-events-calendar' ),
			'error:invalid-ical-url' => __( 'Events could not be imported. The URL provided did not have events in the proper format.', 'the-events-calendar' ),
			'error:invalid-ics-file' => __( 'The file provided could not be opened. Please confirm that it is a properly formatted .ics file.', 'the-events-calendar' ),
			'error:meetup-api-key' => __( 'Your Meetup API key is invalid.', 'the-events-calendar' ),
			'error:meetup-api-quota' => __( 'Event Aggregator cannot reach Meetup.com because you exceeded the request limit for your Meetup API key.', 'the-events-calendar' ),
			'error:usage-limit-exceeded' => __( 'The daily limit of %d import requests to the Event Aggregator service has been reached. Please try again later.', 'the-events-calendar' ),
			'fetching' => __( 'The import is in progress.', 'the-events-calendar' ),
			'queued' => __( 'The import will be starting soon.', 'the-events-calendar' ),
			'success' => __( 'Success', 'the-events-calendar' ),
			'success:create-import' => __( 'Import created', 'the-events-calendar' ),
			'success:facebook-get-token' => __( 'Successfully fetched Facebook Token', 'the-events-calendar' ),
			'success:get-origin' => __( 'Successfully loaded import origins', 'the-events-calendar' ),
			'success:import-complete' => __( 'Import is complete', 'the-events-calendar' ),
			'success:queued' => __( 'Import queued', 'the-events-calendar' ),
			'error:invalid-other-url' => __( 'Events could not be imported. The URL provided could not be reached.', 'the-events-calendar' ),
			'error:no-results' => __( 'The requested source does not have any upcoming and published events matching the search criteria.', 'the-events-calendar' ),
		);

		/**
		 * Filters the service messages map to allow addition and removal of messages.
		 *
		 * @param array $service_messages An associative array of service messages in the `[ <slug> => <localized text> ]` format.
		 */
		$this->service_messages = apply_filters( 'tribe_aggregator_service_messages', $this->service_messages );
	}

	/**
	 * Returns the message used for unknown message codes.
	 *
	 * @return string
	 */
	public function get_unknow_message() {
		return __( 'Unknown service message', 'the-events-calendar' );
	}

	/**
	 * Confirms an import with Event Aggregator Service.
	 *
	 * @param array $args
	 *
	 * @return bool Whether the import was confirmed or not.
	 */
	public function confirm_import( $args ) {
		$keys = array( 'origin', 'source', 'type' );
		$keys = array_combine( $keys, $keys );
		$confirmation_args = array_intersect_key( $args, $keys );
		$confirmation_args = array_merge( $confirmation_args, array(
			'facebook_token' => '1',
			'meetup_api_key' => '1',
		) );
		$response = $this->post_import( $confirmation_args );

		$confirmed = ! empty( $response->status ) && 0 !== strpos( $response->status, 'error' );

		return $confirmed;
	}

	/**
	 * Returns the default origins array.
	 *
	 * @since 4.5.11
	 *
	 * @return array
	 */
	protected function get_default_origins() {
		$origins = array(
			'origin' => array(
				(object) array(
					'id'   => 'csv',
					'name' => __( 'CSV File', 'the-events-calendar' ),
				),
			),
		);

		return $origins;
	}
}
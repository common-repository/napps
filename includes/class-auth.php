<?php
/**
 * Setup napps.
 *
 * @package napps
 */

namespace NAPPS;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

use Exception;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;
use WP_REST_Server;
use Firebase\JWT\JWT;

if (!class_exists('NappsAuth')) {
	/**
	 * The public-facing functionality of the plugin.
	 */
	class NappsAuth {
		
		/**
		 * Store errors to display if the JWT is wrong
		 *
		 * @var WP_REST_Response
		 */
		private $jwt_error = null;
		private $rest_api_slug = 'wp-json';

		public function __construct() {
			$this->rest_api_slug .= "/" . NAPPS_REST_PREFIX;
		}

		/*
		*	Rest password route 
		*/
		public function resetPassword( WP_REST_Request $request ) {
			$email    = $request->get_param( 'email' );
			if ( empty( $email ) || $email === '' ) {
				return new WP_REST_Response(
					array(
						'success'    => false,
						'statusCode' => 401,
						'code'       => 'napps_email_invalid',
						'message'    => __( 'You must provide a valid email', 'napps' ),
						'data'       => array(),
					), 401
				);
			}
			$userdata = get_user_by( "email", $email );
			if( ! $userdata ) {
				return new WP_REST_Response(
					array(
						'success'    => false,
						'statusCode' => 401,
						'code'       => 'napps_email_does_not_exist',
						'message'    => __( 'Email provided does not exist', 'napps' ),
						'data'       => array(),
					), 401
				);
			}
			
			//Retrieve user and trigger reset password 
			try {
				$user      = new WP_User( intval( $userdata->ID ) ); 
				$reset_key = get_password_reset_key( $user ); 
				$wc_emails = WC()->mailer()->get_emails(); 
				$wc_emails['WC_Email_Customer_Reset_Password']->trigger( $user->user_login, $reset_key );
			}
			catch( Exception $e ) {
				return new WP_REST_Response(
					array(
						'success'    => false,
						'statusCode' => 500,
						'code'       => 'napps_something_went_wrong',
						'message'    => __( 'Something went wrong', 'napps' ),
						'data'       => array(),
					), 500
				);
			}

			return new WP_REST_Response(
				array(
					'success'    => true,
					'statusCode' => 200
				)
			);
		}

		/**
		 * Authenticate user either via wp_authenticate_email_password
		 *
		 * @param string $email The user email.
		 * @param string $password The password.
		 * @return WP_User|WP_Error $user Returns WP_User object if success, or WP_Error if failed.
		 */
		public function authenticate_user( $email, $password ) {
			$user = wp_authenticate_email_password(null,  $email, $password );

			if (!$user) {
				return new WP_Error(
					'invalid_email',
					__( 'Unknown email address.' )
				);
			}

			return $user;
		}

		/**
		 * Get token by (login) with a username and password
		 *
		 * @param WP_REST_Request $request The request.
		 * @return WP_REST_Response The response.
		 */
		public function get_token( WP_REST_Request $request ) {
			$secret_key = get_option('NAPPS_AUTH_SECRET_KEY');

			//Username now is email only
			$username    = $request->get_param( 'username' );
			$password    = $request->get_param( 'password' );

			// First thing, check the secret key if not exist return a error.
			if ( ! $secret_key ) {
				return new WP_REST_Response(
					array(
						'success'    => false,
						'statusCode' => 403,
						'code'       => 'jwt_auth_bad_config',
						'message'    => __( 'JWT is not configurated properly.', 'napps' ),
						'data'       => array(),
					), 403
				);
			}

			$user = $this->authenticate_user( $username, $password );

			// If the authentication is failed return error response.
			if ( is_wp_error( $user ) ) {
				$error_code = $user->get_error_code();

				return new WP_REST_Response(
					array(
						'success'    => false,
						'statusCode' => 403,
						'code'       => $error_code,
						'message'    => strip_tags( $user->get_error_message( $error_code ) ),
						'data'       => array(),
					), 403
				);
			}

			// Valid credentials, the user exists, let's generate the token.
			return $this->generate_token( $user, $secret_key );
		}

		/**
		 * Generate token
		 *
		 * @param WP_User $user The WP_User object.
		 *
		 * @return WP_REST_Response|string Return as raw token string or as a formatted WP_REST_Response.
		 */
		private function generate_token( $user, $secret_key ) {
			$issued_at  = time();
			$not_before = $issued_at;
			$not_before = apply_filters( 'jwt_auth_not_before', $not_before, $issued_at );
			$expire     = $issued_at + ( DAY_IN_SECONDS * 365 );
			$expire     = apply_filters( 'jwt_auth_expire', $expire, $issued_at );

			$payload = array(
				'iss'  => $this->get_iss(),
				'iat'  => $issued_at,
				'nbf'  => $not_before,
				'exp'  => $expire,
				'data' => array(
					'user' => array(
						'id' => $user->ID,
					),
				),
			);

			$alg = $this->get_alg();

			// Let the user modify the token data before the sign.
			$token = JWT::encode( apply_filters( 'jwt_auth_payload', $payload, $user ), $secret_key, $alg );

			// The token is signed, now create object with basic info of the user.
			$response = array(
				'success'    => true,
				'statusCode' => 200,
				'code'       => 'jwt_auth_valid_credential',
				'message'    => __( 'Credential is valid', 'napps' ),
				'data'       => array(
					'token'       => $token,
					'id'          => $user->ID,
					'email'       => $user->user_email,
					'nicename'    => $user->user_nicename,
					'firstName'   => $user->first_name,
					'lastName'    => $user->last_name,
					'displayName' => $user->display_name,
				),
			);

			// Let the user modify the data before send it back.
			return apply_filters( 'jwt_auth_valid_credential_response', $response, $user );
		}

		/**
		 * Get the token issuer.
		 *
		 * @return string The token issuer (iss).
		 */
		public function get_iss() {
			return apply_filters( 'jwt_auth_iss', get_bloginfo( 'url' ) );
		}

		/**
		 * Get the supported jwt auth signing algorithm.
		 *
		 * @see https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40
		 *
		 * @return string $alg
		 */
		public function get_alg() {
			return apply_filters( 'jwt_auth_alg', 'HS256' );
		}

		/**
		 * Determine if given response is an error response.
		 *
		 * @param mixed $response The response.
		 * @return boolean
		 */
		public function is_error_response( $response ) {
			if ( ! empty( $response ) && property_exists( $response, 'data' ) && is_array( $response->data ) ) {
				if ( ! isset( $response->data['success'] ) || ! $response->data['success'] ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Main validation function, this function try to get the Autentication
		 * headers and decoded.
		 *
		 * @param bool $output Whether to only return the payload or not.
		 *
		 * @return WP_REST_Response | Array Returns WP_REST_Response or token's $payload.
		 */
		private function validate_token( $output = true ) {

			/**
			 * Looking for the HTTP_AUTHORIZATION header, if not present just
			 * return the user.
			 */
			$auth = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : false;
			if ( ! $auth ) {
				$auth = ( isset( $_GET['auth'] ) ) ? "Bearer " . sanitize_text_field( $_GET['auth'] ) : false;
			}

			if ( ! $auth ) {
				return new WP_REST_Response(
					array(
						'success'    => false,
						'statusCode' => 403,
						'code'       => 'jwt_auth_no_auth_header',
						'message'    => __( 'Authorization header not found.', 'napps' ),
						'data'       => array(),
					), 403
				);
			}

			/**
			 * The HTTP_AUTHORIZATION is present, verify the format.
			 * If the format is wrong return the user.
			 */
			list($token) = sscanf( $auth, 'Bearer %s' );

			if ( ! $token ) {
				return new WP_REST_Response(
					array(
						'success'    => false,
						'statusCode' => 403,
						'code'       => 'jwt_auth_bad_auth_header',
						'message'    => __( 'Authorization header malformed.', 'napps' ),
						'data'       => array(),
					), 403
				);
			}

			// Get the Secret Key.
			$secret_key = get_option('NAPPS_AUTH_SECRET_KEY');

			if ( ! $secret_key ) {
				return new WP_REST_Response(
					array(
						'success'    => false,
						'statusCode' => 403,
						'code'       => 'jwt_auth_bad_config',
						'message'    => __( 'JWT is not configurated properly.', 'napps' ),
						'data'       => array(),
					), 403
				);
			}

			// Try to decode the token.
			try {
				$alg     = $this->get_alg();
				$payload = JWT::decode( $token, $secret_key, array( $alg ) );

				// The Token is decoded now validate the iss.
				if ( $payload->iss !== $this->get_iss() ) {
					// The iss do not match, return error.
					return new WP_REST_Response(
						array(
							'success'    => false,
							'statusCode' => 403,
							'code'       => 'jwt_auth_bad_iss',
							'message'    => __( 'The iss do not match with this server.', 'napps' ),
							'data'       => array(),
						), 403
					);
				}

				// Check the user id existence in the token.
				if ( ! isset( $payload->data->user->id ) ) {
					// No user id in the token, abort!!
					return new WP_REST_Response(
						array(
							'success'    => false,
							'statusCode' => 403,
							'code'       => 'jwt_auth_bad_request',
							'message'    => __( 'User ID not found in the token.', 'napps' ),
							'data'       => array(),
						), 403
					);
				}

				// So far so good, check if the given user id exists in db.
				$user = get_user_by( 'id', $payload->data->user->id );

				if ( ! $user ) {
					// No user id in the token, abort!!
					return new WP_REST_Response(
						array(
							'success'    => false,
							'statusCode' => 403,
							'code'       => 'jwt_auth_user_not_found',
							'message'    => __( "User doesn't exist", 'napps' ),
							'data'       => array(),
						), 403
					);
				}

				// Everything looks good return the token if $output is set to false.
				if ( ! $output ) {
					return $payload;
				}

				$response = array(
					'success'    => true,
					'statusCode' => 200,
					'code'       => 'jwt_auth_valid_token',
					'message'    => __( 'Token is valid', 'napps' ),
					'data'       => array(),
				);

				$response = apply_filters( 'jwt_auth_valid_token_response', $response, $user, $token, $payload );

				// Otherwise, return success response.
				return new WP_REST_Response( $response );
			} catch ( Exception $e ) {
				// Something is wrong when trying to decode the token, return error response.
				return new WP_REST_Response(
					array(
						'success'    => false,
						'statusCode' => 403,
						'code'       => 'jwt_auth_invalid_token',
						'message'    => $e->getMessage(),
						'data'       => array(),
					), 403
				);
			}
		}

		/**
		 * This is our Middleware to try to authenticate the user according to the token sent.
		 *
		 * @param int|bool $user_id User ID if one has been determined, false otherwise.
		 * @return int|bool User ID if one has been determined, false otherwise.
		 */
		public function determine_current_user( $user_id ) {
			
			$valid_api_uri = strpos($_SERVER['REQUEST_URI'], $this->rest_api_slug);
			if ( ! $valid_api_uri || !empty($user_id) ) {
				return $user_id;
			}

			if ( false != stripos( $_SERVER['REQUEST_URI'], $this->rest_api_slug . "/smartbanner") ) {
				return $this->perform_basic_authentication();
			}

			//We dont want to determine the user for this routes (Unauthenticated routes)
			$ignoreEndpoints = array(
				"status",
				"token",
				"manifest"
			);

			$isEndpointWhitelist = false;
			foreach($ignoreEndpoints as $endpoint) {
				if(false != stripos( $_SERVER['REQUEST_URI'], $this->rest_api_slug . "/" . $endpoint)) {
					$isEndpointWhitelist = true;
					break;
				}
			}
			
			if ( $isEndpointWhitelist ) {
				return $user_id;
			}


			$payload = $this->validate_token( false );
			// If $payload is an error response, then return the default $user_id.
			if ( $this->is_error_response( $payload ) ) {
				if ( 'jwt_auth_no_auth_header' === $payload->data['code'] ||
					'jwt_auth_bad_auth_header' === $payload->data['code']
				) {

					$this->jwt_error = $payload;
				}
				return $user_id;
			}

			// Everything is ok here, return the user ID stored in the token.
			return $payload->data->user->id;
		}
		
		/**
		 * Basic Authentication.
		 *
		 * SSL-encrypted requests are not subject to sniffing or man-in-the-middle
		 * attacks, so the request can be authenticated by simply looking up the user
		 * associated with the given consumer key and confirming the consumer secret
		 * provided is valid.
		 *
		 * @return int|bool
		 */
		private function perform_basic_authentication() {
			$consumer_key      = '';
			$consumer_secret   = '';

			// If the above is not present, we will do full basic auth.
			if ( ! $consumer_key && ! empty( $_SERVER['PHP_AUTH_USER'] ) && ! empty( $_SERVER['PHP_AUTH_PW'] ) ) {
				$consumer_key    = $_SERVER['PHP_AUTH_USER']; // WPCS: CSRF ok, sanitization ok.
				$consumer_secret = $_SERVER['PHP_AUTH_PW']; // WPCS: CSRF ok, sanitization ok.
			}

			// Stop if don't have any key.
			if ( ! $consumer_key || ! $consumer_secret ) {
				return false;
			}

			// Get user data.
			$user = $this->get_user_data_by_consumer_key( $consumer_key );
			if ( empty( $user ) ) {
				return false;
			}

			// Validate user secret.
			if ( ! hash_equals( $user->consumer_secret, $consumer_secret ) ) { // @codingStandardsIgnoreLine
				return false;
			}

			return $user->user_id;
		}

		private function get_user_data_by_consumer_key( $consumer_key ) {
			global $wpdb;
	
			$consumer_key = wc_api_hash( sanitize_text_field( $consumer_key ) );
			$user         = $wpdb->get_row(
				$wpdb->prepare(
					"
				SELECT key_id, user_id, permissions, consumer_key, consumer_secret, nonces
				FROM {$wpdb->prefix}woocommerce_api_keys
				WHERE consumer_key = %s
			",
					$consumer_key
				)
			);
	
			return $user;
		}

		
		/**
		 * Filter to hook the rest_pre_dispatch, if there is an error in the request
		 * send it, if there is no error just continue with the current request.
		 *
		 * @param mixed           $result Can be anything a normal endpoint can return, or null to not hijack the request.
		 * @param WP_REST_Server  $server Server instance.
		 * @param WP_REST_Request $request The request.
		 *
		 * @return mixed $result
		 */
		public function rest_pre_dispatch( $result, WP_REST_Server $server, WP_REST_Request $request ) {
			if ( $this->is_error_response( $this->jwt_error ) ) {
				return $this->jwt_error;
			}

			if ( empty( $result ) ) {
				return $result;
			}

			return $result;
		}
	}
}
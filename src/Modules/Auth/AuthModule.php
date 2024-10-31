<?php

namespace NAPPS\Modules\Auth;

use Firebase\JWT\JWT;
use NAPPS\Services\AuthService;

class AuthModule {

    /**
     * AuthService
     *
     * @var AuthService
     */
    private $authService;

    public function __construct() {

        $this->authService = new AuthService();

        add_filter( 'determine_current_user', array( $this, 'determine_current_user' ) );
    }

    /**
     * This is our Middleware to try to authenticate the user according to the token sent.
     *
     * @param int|bool $user_id User ID if one has been determined, false otherwise.
     * @return int|bool User ID if one has been determined, false otherwise.
     */
    public function determine_current_user( $user_id ) {
        
        if ( ! empty( $user_id ) || ! $this->is_request_to_rest_api() ) {
			return $user_id;
		}

        $payload = $this->get_token_payload( );
        if(!$payload) {
            return null;
        }

        // Everything is ok here, return the user ID stored in the token.
        return $payload->data->user->id;
    }

    /**
	 * Check if is request to our REST API.
	 *
	 * @return bool
	 */
	protected function is_request_to_rest_api() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$rest_prefix = trailingslashit( rest_get_url_prefix() );
		$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );

		// Check if the request is to the WC API endpoints.
		$napps = ( false !== strpos( $request_uri, $rest_prefix . 'napps/' ) );

		return apply_filters( 'napps_rest_is_request_to_rest_api', $napps );
	}

    /**
     * Main validation function, this function try to get the Autentication
     * headers and decoded.
     *
     * @return null|object Returns null or token's $payload.
     */
    protected function get_token_payload( ) {

        $auth = $this->authService->getAuthorizationHeader();
        if ( ! $auth ) {
            return null;
        }

        /**
         * The HTTP_AUTHORIZATION is present, verify the format.
         * If the format is wrong return the user.
         */
        list($token) = sscanf( $auth, 'Bearer %s' );
        if ( ! $token ) {
            return null;
        }

        // Get the Secret Key.
        $secret_key = get_option('NAPPS_AUTH_SECRET_KEY');
        if ( ! $secret_key ) {
            return null;
        }

        // Try to decode the token.
        try {
            $alg     = $this->authService->get_alg();
            $payload = JWT::decode( $token, $secret_key, array( $alg ) );

            // The Token is decoded now validate the iss.
            if ( $payload->iss !== $this->authService->get_iss() ) {
                // The iss do not match, return error.
                return null;
            }

            // Check the user id existence in the token.
            if ( ! isset( $payload->data->user->id ) ) {
                // No user id in the token, abort!!
                return null;
            }

            // So far so good, check if the given user id exists in db.
            $user = get_user_by( 'id', $payload->data->user->id );

            if ( ! $user ) {
                // No user id in the token, abort!!
                return null;
            }

            return $payload;

        } catch ( \Exception $e ) {
            // Something is wrong when trying to decode the token, return error response.
            return null;
        }
    }
    
}
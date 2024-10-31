<?php

namespace NAPPS\Services;

use Firebase\JWT\JWT;

class AuthService {

    /**
     * Get the token issuer.
     *
     * @return string The token issuer (iss).
     */
    public function get_iss() {
        return apply_filters( 'napps_jwt_auth_iss', get_bloginfo( 'url' ) );
    }

    /**
     * Get the supported jwt auth signing algorithm.
     *
     * @see https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40
     *
     * @return string $alg
     */
    public function get_alg() {
        return apply_filters( 'napps_jwt_auth_alg', 'HS256' );
    }

    /**
     * Authenticate user either via wp_authenticate_email_password
     *
     * @param string $email The user email.
     * @param string $password The password.
     * @return \WP_User|\WP_Error $user Returns WP_User object if success, or WP_Error if failed.
     */
    public function authenticate_user( $email, $password ) {
        $user = wp_authenticate_email_password(null,  $email, $password );

        if (!$user) {
            return new \WP_Error(
                'invalid_email',
                __( 'Unknown email address.' )
            );
        }

        return $user;
    }

    public function checkValidClient() {

        return get_current_user_id() != 0;

    }

    /**
     * Generate token
     *
     * @param \WP_User $user The WP_User object.
     *
     * @return \WP_REST_Response formatted WP_REST_Response.
     */
    public function generate_token( $user ) {

        $secret_key = get_option('NAPPS_AUTH_SECRET_KEY');
        if(!$secret_key) {
            return new \WP_REST_Response(
                array(
                    'success'    => false,
                    'statusCode' => 403,
                    'code'       => 'jwt_auth_bad_config',
                    'message'    => __( 'JWT is not configurated properly.', 'napps' ),
                    'data'       => array()
                ), 
                403
            );
        }

        $issued_at  = time();
        $not_before = $issued_at;
        $not_before = apply_filters( 'napps_jwt_auth_not_before', $not_before, $issued_at );
        $expire     = $issued_at + ( DAY_IN_SECONDS * 365 );
        $expire     = apply_filters( 'napps_jwt_auth_expire', $expire, $issued_at );

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
        $token = JWT::encode( apply_filters( 'napps_jwt_auth_payload', $payload, $user ), $secret_key, $alg );

        // The token is signed, now create object with basic info of the user.
        $response = new \WP_REST_Response(
            array(
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
            ), 
            200
        );

        // Let the user modify the data before send it back.
        return apply_filters( 'napps_jwt_auth_valid_credential_response', $response, $user );
    }

    /** 
    *   Looking for the HTTP_AUTHORIZATION header
    *
    *   @return string|null $auth
    */
    public function getAuthorizationHeader( ) {

        $auth = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : false;
        if ( ! $auth ) {
            $auth = ( isset( $_GET['auth'] ) ) ? "Bearer " . sanitize_text_field( $_GET['auth'] ) : false;
        }

        return $auth;
    }
}
<?php

namespace NAPPS\Controllers;

use NAPPS\Contracts\IController;
use NAPPS\Services\AuthService;

class AuthController implements IController {
        
    /**
     * AuthService
     *
     * @var AuthService
     */
    private $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function registerRoutes() {

        //Reset password request for users
        register_rest_route(
            NAPPS_REST_PREFIX,
            'reset-password',
            array(
                'methods'  => 'POST',
                'callback' => array( $this, 'resetPassword' ),
                'permission_callback' => '__return_true',
            )
        );

        //Login request
        register_rest_route(
            NAPPS_REST_PREFIX,
            'token',
            array(
                'methods'  => 'POST',
                'callback' => array( $this, 'get_token' ),
                'permission_callback' => '__return_true',
            )
        );

    }
  
    /**
     * Reset password route 
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Respons
     */
    public function resetPassword(  $request ) {
        $email    = $request->get_param( 'email' );
        if ( empty( $email ) || $email === '' ) {
            return new \WP_REST_Response(
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
            return new \WP_REST_Response(
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
            $user      = new \WP_User( intval( $userdata->ID ) ); 
            $reset_key = get_password_reset_key( $user ); 
            $wc_emails = WC()->mailer()->get_emails(); 
            $wc_emails['WC_Email_Customer_Reset_Password']->trigger( $user->user_login, $reset_key );
        }
        catch( \Exception $e ) {
            return new \WP_REST_Response(
                array(
                    'success'    => false,
                    'statusCode' => 500,
                    'code'       => 'napps_something_went_wrong',
                    'message'    => __( 'Something went wrong', 'napps' ),
                    'data'       => array(),
                ), 500
            );
        }

        return new \WP_REST_Response(
            array(
                'success'    => true,
                'statusCode' => 200
            )
        );
    }

    /**
     * Get token by (login) with a username and password
     *
     * @param \WP_REST_Request $request The request.
     * @return \WP_REST_Response The response.
     */
    public function get_token( $request ) {

        //Username now is email only
        $email    = $request->get_param( 'username' );
        $password    = $request->get_param( 'password' );

        $user = $this->authService->authenticate_user($email, $password);

        // If the authentication is failed return error response.
        if ( is_wp_error( $user ) ) {
            $error_code = $user->get_error_code();

            return new \WP_REST_Response(
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
        return $this->authService->generate_token( $user );
    }
}
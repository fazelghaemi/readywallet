<?php
/**
 * ReadyWallet REST API Controller
 * Namespace: readywallet/v1
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet_REST_Controller extends WP_REST_Controller {

    protected $namespace = 'readywallet/v1';
    protected $rest_base = 'wallet';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // مسیر دریافت موجودی
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/balance', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_balance' ),
                'permission_callback' => array( $this, 'permissions_check' ),
            ),
        ) );

        // مسیر دریافت تراکنش‌ها
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/transactions', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_transactions' ),
                'permission_callback' => array( $this, 'permissions_check' ),
            ),
        ) );
    }

    public function permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'ready_wallet_rest_forbidden', __( 'وارد حساب کاربری شوید.', 'ready-wallet' ), array( 'status' => 401 ) );
        }
        return true;
    }

    public function get_balance( $request ) {
        $user_id = get_current_user_id();
        $balance = Ready_Wallet()->db->get_wallet_balance( $user_id );
        
        return rest_ensure_response( array(
            'user_id' => $user_id,
            'balance' => $balance,
            'formatted' => wc_price($balance)
        ) );
    }

    public function get_transactions( $request ) {
        $user_id = get_current_user_id();
        $args = [
            'user_id' => $user_id,
            'limit' => 10,
            'order' => 'DESC'
        ];
        $transactions = Ready_Wallet()->db->get_transactions($args);
        return rest_ensure_response( $transactions );
    }
}
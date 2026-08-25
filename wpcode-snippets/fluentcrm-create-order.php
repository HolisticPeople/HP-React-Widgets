<?php
/**
 * WPCode snippet: FluentCRM - Create Order From CRM
 *
 * Paste this into WPCode (PHP, Auto Insert → Admin Only).
 * Injects a "Create Order" button next to "Create Ticket" in the Purchase History
 * section. Styled as a button (same as Create Ticket); opens EAO new order in a new tab
 * with the contact pre-selected.
 *
 * @version 1.1.0 - Button styling to match Create Ticket
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_footer', function() {
    if ( ! function_exists( 'get_current_screen' ) ) {
        return;
    }
    $screen = get_current_screen();
    if ( ! $screen ) {
        return;
    }

    if ( strpos( $screen->id, 'fluentcrm' ) === false ) {
        return;
    }

    $create_order_base_url = add_query_arg(
        array( 'action' => 'eao_create_new_order' ),
        admin_url( 'admin.php' )
    );
    ?>
    <script type="text/javascript">
    (function($){
        var createOrderBaseUrl = '<?php echo esc_url( $create_order_base_url ); ?>';

        function insertCreateOrder() {
            var $block = $(".fluentcrm_databox .purchase_history_block");
            if ( $('#create-order-btn').length ) {
                return;
            }
            if ( ! $block.length ) {
                return;
            }
            var $title = $block.find('h3').first();
            if ( ! $title.length ) {
                return;
            }
            var m = window.location.hash.match(/subscribers\/(\d+)/);
            if ( ! m ) {
                return;
            }
            var subscriberId = m[1];
            var fullUrl = createOrderBaseUrl + '&subscriber_id=' + subscriberId;

            var $button = $('<button>', {
                type: 'button',
                id: 'create-order-btn',
                text: 'Create Order'
            })
                .addClass('fluentcrm-btn btn-sm btn-primary')
                .css({ margin: '10px 0 10px 0', marginLeft: '8px' })
                .on('click', function() {
                    window.open(fullUrl, '_blank', 'noopener,noreferrer');
                });

            if ( $('#create-ticket-btn').length ) {
                $button.insertAfter( '#create-ticket-btn' );
            } else {
                $title.after( $button );
            }
        }

        $(document).ready( insertCreateOrder );
        $(window).on( 'hashchange', insertCreateOrder );
        $(document).on( 'ajaxComplete', insertCreateOrder );
    })(jQuery);
    </script>
    <?php
});

<?php
/*
    Plugin Name: bpost shipping label for WooCommerce
    Plugin URI: https://github.com/Semaho/bpost-shipping-label-woocommerce
    description: Create your bpost shipping label directly in WooCommerce order page.
    Version: 1.0.0
    Author: Sébastien Vignol
    Author URI: https://sebastien.vignol.be/
    License: MIT
*/



function seb_add_bpost_scripts()
{
    wp_enqueue_style( 'bpost', plugin_dir_url(__FILE__) . 'bpost.css', array(), get_plugin_data( __FILE__ )['Version'], 'all');
    wp_enqueue_script( 'bpostjs', plugin_dir_url(__FILE__) . 'bpost.js', array ( 'jquery' ), get_plugin_data( __FILE__ )['Version'], true);
    wp_localize_script( 'bpostjs', 'vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'btn_class' => is_admin() ? "button button-primary" : "dokan-btn dokan-btn-success"
    ]);
}
add_action( 'wp_enqueue_scripts', 'seb_add_bpost_scripts' );
add_action( 'admin_enqueue_scripts', 'seb_add_bpost_scripts' );


/*****************************************************************************/
/* BPOST INFORMATION IN PROFILE
/*****************************************************************************/


/**
 * Add bpost custom fields in user profile.
 */

function seb_add_user_profile_fields( $user )
{
    ?>
    <h3 id="bpost">Informations bpost</h3>
    <table class="form-table">
    <tr>
        <th><label for="bpost_accountid">Account ID</label></th>
        <td>
            <input type="text" inputmode="numeric" name="bpost_accountid" id="bpost_accountid" value="<?php echo esc_attr( get_user_meta( $user->ID, 'bpost_accountid', true ) ); ?>" class="regular-text" /><br />
            <!-- <span class="description"><?php _e("Please enter your address."); ?></span> -->
        </td>
    </tr>
    <tr>
        <th><label for="bpost_passphrase">Passphrase</label></th>
        <td>
            <input type="password" name="bpost_passphrase" id="bpost_passphrase" value="<?php echo esc_attr( get_user_meta( $user->ID, 'bpost_passphrase', true ) ); ?>" class="regular-text" /><br />
        </td>
    </tr>
    </table>
    <?php
}

add_action( 'show_user_profile', 'seb_add_user_profile_fields' );
add_action( 'edit_user_profile', 'seb_add_user_profile_fields' );


/**
 * Save bpost custom fields in user profile.
 */

function seb_save_user_profile_fields( $user_id )
{
    if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-user_' . $user_id ) ) {
        return;
    }
    
    if ( !current_user_can( 'edit_user', $user_id ) ) { 
        return false; 
    }

    update_user_meta( $user_id, 'bpost_accountid', $_POST['bpost_accountid'] );
    update_user_meta( $user_id, 'bpost_passphrase', $_POST['bpost_passphrase'] );
}

add_action( 'personal_options_update', 'seb_save_user_profile_fields' );
add_action( 'edit_user_profile_update', 'seb_save_user_profile_fields' );



/*****************************************************************************/
/* ORDER PAGE METABOX & ACTIONS
/*****************************************************************************/


/**
 * Add meta box in order page.
 */

function seb_add_bpost_metabox()
{
    add_meta_box(
        'bpost_metabox',          // Unique ID
        __('Étiquette bpost'),    // Box title
        'seb_bpost_metabox_html', // Content callback, must be of type callable
        'shop_order',             // Post type
        'side',                   // Context
        'high'                    // Priority
    );
}

// add_action('add_meta_boxes', 'seb_add_bpost_metabox'); // Uncomment to add metabox in WP Dashboard.


/**
 * Provide bpost metabox HTML.
 */

function seb_bpost_metabox_html($post)
{
    $order_id    = is_admin() ? get_the_id() : intval(seb_GET('order_id'));
    $vendor_id   = get_post_meta($order_id, '_dokan_vendor_id', true );
    $ship_weight = wc_get_weight(get_post_meta($order_id, '_cart_weight', true), 'g');

    $bpost_label_bytestring = get_post_meta( $order_id, 'bpost_label_bytestring', true );

    $btn_class = is_admin() ? "button button-primary" : "dokan-btn dokan-btn-success";

    $errors = '';

    // Check bpost information.
    if ( empty(get_user_meta($vendor_id, 'bpost_accountid', true)) || empty(get_user_meta($vendor_id, 'bpost_passphrase', true)) )
    {
        $errors .= is_admin() ?
            '<p class="bpost-error">Vous devez compléter les <a href="'.get_edit_profile_url().'#bpost">informations relatives à bpost</a> avant de pouvoir générer des étiquettes.</p>' :
            '<p class="bpost-error">Demandez à l&apos;administrateur de ce site de compléter les informations relatives à bpost</a> avant de pouvoir générer des étiquettes</p>';
    }
    ?>

    <div id="bpost_form" class="bpost-form">

        <?= empty($errors) ? '' : $errors ?>

        <?php if ( empty($bpost_label_bytestring) ) : ?>

            <?php 
            // Check if weight is not null, but don't add to errors because it can be manually overrided.
            if ( $ship_weight <= 0 ) : ?>
                <p class="bpost-error">Le poids du colis est manquant. <br>Avez-vous renseigné le poids de tous vos produits ?</p>
            <?php endif ?>

            <label>Poids (en grammes) du colis <span class="woocommerce-help-tip" data-tip="La valeur ci-dessous se base sur le poids renseigné pour chaque produit de la commande. Cela ne correspond pas à ce que vous pensez ? Vérifiez le poids dans la fiche de vos produits."></span></label>
            <div class="bpost-weight-form">
                <input id="bpost_order_weight" type="text" required inputmode="numeric" placeholder="Poids" value="<?= $ship_weight ?: '' ?>"> <span>grammes</span>
            </div>

            <button type="button" data-component="create-bpost-order-and-label" data-order="<?= $order_id ?>" class="<?= $btn_class ?>" <?= empty($errors) ? '' : 'disabled' ?>>
                Créer une étiquette d'envoi bpost
            </button>

        <?php else : ?>

            <a download="bpost-label" href="data:application/pdf;base64, <?= $bpost_label_bytestring ?>" target="_blank" class="<?= $btn_class ?>">Télécharger l'étiquette bpost</a>

        <?php endif ?>

    </div>
    <?php
}

function seb_bpost_metabox_html_dokan_before()
{
    ?>
    <div class="" style="width:100%">
        <div class="dokan-panel dokan-panel-default">
            <div class="dokan-panel-heading"><strong>Étiquette bpost</strong></div>
            <div class="dokan-panel-body general-details">
    <?php
}

function seb_bpost_metabox_html_dokan_after()
{
    ?>
            </div>
        </div>
    </div>
    <?php
}

add_action('dokan_order_detail_after_order_items', 'seb_bpost_metabox_html_dokan_before', 5);
add_action('dokan_order_detail_after_order_items', 'seb_bpost_metabox_html');
add_action('dokan_order_detail_after_order_items', 'seb_bpost_metabox_html_dokan_after', 15);


/**
 * AJAX actions.
 */

function seb_bpost_create_order_ajax()
{
    $order_id = intval(seb_POST('order_id'));
    $weight   = intval(seb_POST('weight'));

    // Check that current user ID == seller ID.
    if ( get_current_user_id() != intval(get_post_meta($order_id, '_dokan_vendor_id', true)) )
    {
        $result['type'] = "error";
        $result['message'] = '<p>Seul le vendeur lié à cette commande peut créer l&apos;étiquette d&apos;envoi.</p>';
        wp_send_json($result);
    }

    $order_created  = seb_bpost_api_create_order($order_id, $weight);
    $pdf_bytestring = seb_bpost_api_get_label($order_id);

    if ( !is_wp_error($order_created) && is_string($pdf_bytestring) )
    {
        $result['type'] = "success";
        $result['bytestring'] = $pdf_bytestring;
    }
    else
    {
        $matches = [];
        preg_match('/<body>(.*?)<\/body>/mi', $order_created->get_error_message('bpost_api_error'), $matches);
        $standard_msg = '<p>Une erreur est survenue, veuillez réessayer plus tard. Si l&apos;erreur persiste, il est également possible d&apos;utiliser le <a href="https://www.bpost.be/portal/goLogin" target="_blank">Shipping Manager</a> de bpost.</p>';
        $error_msg = empty($matches) ? '<p>'.$order_created->get_error_message('bpost_api_error').'</p>' : $matches[1]; // $matches[1] contains HTML.

        $result['type'] = "error";
        $result['message'] = $error_msg . $standard_msg;
    }

    wp_send_json($result);
}

add_action( 'wp_ajax_bpost_create_order_ajax', 'seb_bpost_create_order_ajax' );


/**
 * Create bpost order.
 */

function seb_bpost_api_create_order($order_id, $weight)
{
    // Seller.
    $vendor_id    = get_post_meta($order_id, '_dokan_vendor_id', true );
    $vendor_email = get_userdata($vendor_id)->user_email;
    $vendor_name  = get_user_meta( $vendor_id, 'dokan_store_name', true );

    // Shipping.
    $ship_first_name = get_post_meta($order_id, '_shipping_first_name', true);
    $ship_last_name  = get_post_meta($order_id, '_shipping_last_name', true);
    $ship_company    = get_post_meta($order_id, '_shipping_company', true);
    $ship_address_1  = get_post_meta($order_id, '_shipping_address_1', true);
    $ship_city       = get_post_meta($order_id, '_shipping_city', true);
    $ship_postcode   = get_post_meta($order_id, '_shipping_postcode', true);
    $ship_country    = get_post_meta($order_id, '_shipping_country', true);
    $billing_email   = get_post_meta($order_id, '_billing_email', true);
    $seller_address  = dokan_get_seller_address($vendor_id, true);

    // bpost API params.
    $bpost_account_id = get_user_meta( $vendor_id, 'bpost_accountid', true );
    $bpost_passphrase = get_user_meta( $vendor_id, 'bpost_passphrase', true );
    $reference        = "woocommerce_order_$order_id";
    $authorization    = base64_encode($bpost_account_id.':'.$bpost_passphrase);
    $url              = "https://api-parcel.bpost.be/services/shm/$bpost_account_id/orders/";

    $content = '<?xml version="1.0" encoding="UTF-8"?>
    <tns:order xmlns="http://schema.post.be/shm/deepintegration/v3/national" xmlns:common="http://schema.post.be/shm/deepintegration/v3/common" xmlns:tns="http://schema.post.be/shm/deepintegration/v3/" xmlns:international="http://schema.post.be/shm/deepintegration/v3/international" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://schema.post.be/shm/deepintegration/v3/">
        <tns:accountId>'.$bpost_account_id.'</tns:accountId>
        <tns:reference>'.$reference.'</tns:reference>
        <tns:box>
            <tns:sender>
                    <common:name>'.$vendor_name.'</common:name>
                    <common:company></common:company>
                    <common:address>
                        <common:streetName>'.$seller_address['street_1'].'</common:streetName>
                        <common:postalCode>'.$seller_address['zip'].'</common:postalCode>
                        <common:locality>'.$seller_address['city'].'</common:locality>
                        <common:countryCode>BE</common:countryCode>
                    </common:address>
                    <common:emailAddress>'.$vendor_email.'</common:emailAddress>
            </tns:sender>
            <tns:nationalBox>
                <atHome>
                    <product>bpack 24h Pro</product>
                    <weight>'.$weight.'</weight>
                    <receiver>
                        <common:name>'.$ship_first_name.' '.$ship_last_name.'</common:name>
                        <common:company>'.$ship_company.'</common:company>
                        <common:address>
                            <common:streetName>'.$ship_address_1.'</common:streetName>
                            <common:postalCode>'.$ship_postcode.'</common:postalCode>
                            <common:locality>'.$ship_city.'</common:locality>
                            <common:countryCode>'.$ship_country.'</common:countryCode>
                        </common:address>
                        <common:emailAddress>'.$billing_email.'</common:emailAddress>
                    </receiver>
                </atHome>
            </tns:nationalBox>
            <tns:remark>jachetemontois.be</tns:remark>
            <tns:additionalCustomerReference>ORIGIN: jachetemontois.be</tns:additionalCustomerReference>
        </tns:box>
    </tns:order>';

    // use key 'http' even if you send the request to https://...
    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => array(
                "Authorization: Basic $authorization",
                "Content-type: application/vnd.bpost.shm-order-v3.3+XML"
            ),
            'content' => $content,
            'ignore_errors' => true
        )
    );

    $context = stream_context_create($options);
    $result  = file_get_contents($url, false, $context);

    return ( empty($result) && strpos($http_response_header[0], '201') ) ?: new WP_Error('bpost_api_error', $result);
}


/**
 * Get bpost label.
 */

function seb_bpost_api_get_label($order_id)
{
    // Seller.
    $vendor_id = get_post_meta($order_id, '_dokan_vendor_id', true );

    // bpost API params.
    $bpost_account_id = get_user_meta( $vendor_id, 'bpost_accountid', true );
    $bpost_passphrase = get_user_meta( $vendor_id, 'bpost_passphrase', true );
    $reference        = "woocommerce_order_$order_id";
    $authorization    = base64_encode($bpost_account_id.':'.$bpost_passphrase);
    $url              = "https://api-parcel.bpost.be/services/shm/$bpost_account_id/orders/$reference/labels/A4";

    // use key 'http' even if you send the request to https://...
    $options = array(
        'http' => array(
            'method'  => 'GET',
            'header'  => array(
                "Authorization: Basic $authorization",
                "Content-type: application/vnd.bpost.shm-labelRequest-v3.3+XML",
                "Accept: application/vnd.bpost.shm-label-pdf-v3+XML"
            ),
            'ignore_errors' => true
        )
    );

    $context = stream_context_create($options);
    $result  = file_get_contents($url, false, $context);

    if ( !empty($result) && strpos($http_response_header[0], '200') )
    {
        // Success.
        $labels = simplexml_load_string( $result );

        if ( isset($labels->label->bytes) )
        {
            update_post_meta( $order_id, 'bpost_label_bytestring', (string)$labels->label->bytes );
            return (string)$labels->label->bytes;
        }

        // Means request succeeded but label was already printed.
        return true;
    }
    else
    {
        // Failure.
        // msg = $result
        return false;
    }
}



/*****************************************************************************/
/* WOOCOMMERCE
/*****************************************************************************/


/**
 * Save & Display Order Total Weight in WooCommerce order page.
 */
 
function bbloomer_save_weight_order( $order_id )
{
    $weight = WC()->cart->get_cart_contents_weight();
    update_post_meta( $order_id, '_cart_weight', $weight );
}
  
function bbloomer_delivery_weight_display_admin_order_meta( $order )
{
    $cart_weight = get_post_meta( $order->get_id(), '_cart_weight', true );
    echo '<p><strong>Poids total : </strong> ' . wc_get_weight($cart_weight, 'g') . 'g' . '</p>';
}

add_action( 'woocommerce_checkout_update_order_meta', 'bbloomer_save_weight_order' );
add_action( 'woocommerce_admin_order_data_after_billing_address', 'bbloomer_delivery_weight_display_admin_order_meta', 10, 1 );



/*****************************************************************************/
/* FUNCTIONS
/*****************************************************************************/


/**
 * Return a sanitized value from $_GET, if any.
 */

function seb_GET($index)
{
    return (isset($_GET) && isset($_GET[$index])) ? 
        sanitize_text_field($_GET[$index]) : 
        null;
}


/**
 * Return a sanitized value from $_POST, if any.
 */

function seb_POST($index)
{
    return (isset($_POST) && isset($_POST[$index])) ? 
        sanitize_text_field($_POST[$index]) : 
        null;
}
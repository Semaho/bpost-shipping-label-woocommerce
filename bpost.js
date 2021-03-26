var $create_bpost_label_button = jQuery('[data-component="create-bpost-order-and-label"]'),
    $bpost_form                = jQuery('#bpost_form'),
    $bpost_order_weight        = jQuery('#bpost_order_weight'),
    $spinner                   = jQuery('<span class="wp_spinner"></span>');

$create_bpost_label_button.on('click', postCreateOrderAndLabelRequest);

function postCreateOrderAndLabelRequest(e)
{
    // Don't send if no weight.
    if ( !parseInt($bpost_order_weight.val()) > 0 )
    {
        alert('Veuillez renseigner le poids (en grammes) avant de créer votre étiquette bpost.');
        return;
    }

    // Show spinner.
    $bpost_form.html($spinner);

    jQuery.ajax(
    {
        type : "post",
        dataType : "json",
        url : vars.ajax_url,
        data :
        {
            action   : "bpost_create_order_ajax",
            order_id : e.currentTarget.getAttribute('data-order'),
            weight   : parseInt($bpost_order_weight.val())
        },
        success: function(response)
        {
            if(response.type == "success")
            {
                var $download_button = jQuery('<a download="bpost-label" href="data:application/pdf;base64, '+response.bytestring+'" target="_blank" class="'+vars.btn_class+'">Télécharger l&apos;étiquette bpost</a>');
                $bpost_form.html($download_button);
                $download_button.click();
            }
            else
            {
                $bpost_form.html(response.message);
            }
        }
    });
}
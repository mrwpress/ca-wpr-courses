jQuery(document).ready(function($) {

    $("#wpr_export_date_start").datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true
    });
    
    $("#wpr_export_date_end").datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true
    });

	$('#wpr_export_date_start, #wpr_export_date_end').change(function() {
        date_start = new Date( $("#wpr_export_date_start").val() );
        date_end   = new Date( $("#wpr_export_date_end"  ).val() );

        if (date_start > date_end) {
            $("#tab_filter_exportation_gravity form button[type='submit']").attr('disabled','disabled');
        } else {
            $("#tab_filter_exportation_gravity form button[type='submit']").attr('disabled',false);
        }
    });
});
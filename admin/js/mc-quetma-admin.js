jQuery(document).ready(function(){
    jQuery('.ninepay-element-wallet').parents('tr').addClass('ninepay-wrapper-wallet');
    jQuery('.ninepay-element-atm').parents('tr').addClass('ninepay-wrapper-atm');
    jQuery('.ninepay-element-collection').parents('tr').addClass('ninepay-wrapper-collection');
    jQuery('.ninepay-element-credit').parents('tr').addClass('ninepay-wrapper-credit');

    jQuery('.ninepay-fixed').parents('tr').find('th').hide();

    jQuery('.pt-0').parents('td').css('padding-top', '0');
    jQuery('.pb-0').parents('td').css('padding-bottom', '0');

    jQuery('.ninepay-percent').parents('fieldset').find('legend').replaceWith('<div><p style="margin-right: 30px;">Tính phí người mua</p></div>');

});


function nineMethodPercent(event, _this) {
    event = (event) ? event : window.event;
    var charCode = (event.which) ? event.which : event.keyCode;
    var string = jQuery(_this).val();

    if(string.includes('.') && charCode === 46) {
        return false;
    }

    /*Only number or .*/
    if (charCode === 46) {
        return true;
    }

    if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        return false;
    }

    /*Sau dấu . chỉ được nhập 2 chữ số*/
    if(string.split(".").length > 1 && string.split(".")[1].length > 1) {
        return false;
    }

    return true;
}
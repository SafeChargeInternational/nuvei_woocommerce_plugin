var scSettleBtn		= null;
var scVoidBtn		= null;
//var nuveiPlansList	= JSON.parse(scTrans.nuveiPaymentPlans);

// when the admin select to Settle or Void the Order
function settleAndCancelOrder(question, action, orderId) {
	console.log('settleAndCancelOrder')
	
	if (confirm(question)) {
		jQuery('#custom_loader').show();
		
		var data = {
			action      : 'sc-ajax-action',
			security    : scTrans.security,
			orderId     : orderId
		};
		
		if (action == 'settle') {
			data.settleOrder = 1;
		} else if (action == 'void') {
			data.cancelOrder = 1;
		}
		
		jQuery.ajax({
			type: "POST",
			url: scTrans.ajaxurl,
			data: data,
			dataType: 'json'
		})
			.fail(function( jqXHR, textStatus, errorThrown){
				jQuery('#custom_loader').hide();
				alert('Response fail.');
				
				console.error(textStatus)
				console.error(errorThrown)
			})
			.done(function(resp) {
				console.log(resp);
				
				if (resp && typeof resp.status != 'undefined' && resp.data != 'undefined') {
					if (resp.status == 1) {
						var urlParts    = window.location.toString().split('post.php');
						window.location = urlParts[0] + 'edit.php?post_type=shop_order';
					} else if (resp.data.reason != 'undefined' && resp.data.reason != '') {
						jQuery('#custom_loader').hide();
						alert(resp.data.reason);
					} else if (resp.data.gwErrorReason != 'undefined' && resp.data.gwErrorReason != '') {
						jQuery('#custom_loader').hide();
						alert(resp.data.gwErrorReason);
					} else {
						jQuery('#custom_loader').hide();
						alert('Response error.');
					}
				} else {
					jQuery('#custom_loader').hide();
					alert('Response error.');
				}
			});
	}
}
 
/**
 * Function returnSCSettleBtn
 * Returns the SC Settle button
 */
function returnSCBtns() {
	if (scVoidBtn !== null) {
		jQuery('.wc-order-bulk-actions p').append(scVoidBtn);
		scVoidBtn = null;
	}
	if (scSettleBtn !== null) {
		jQuery('.wc-order-bulk-actions p').append(scSettleBtn);
		scSettleBtn = null;
	}
}

function scCreateRefund(question) {
	console.log('scCreateRefund')
	
	var refAmount = parseFloat(jQuery('#refund_amount').val());
	
	if(isNaN(refAmount) || refAmount < 0.001) {
		jQuery('#refund_amount').css('border-color', 'red');
		jQuery('#refund_amount').on('focus', function() {
			jQuery('#refund_amount').css('border-color', 'inherit');
		});
		
		return;
	}
	
	if (!confirm(question)) {
		return;
	}
	
	jQuery('body').find('#sc_api_refund').prop('disabled', true);
	jQuery('body').find('#sc_refund_spinner').show();
	
	var data = {
		action      : 'sc-ajax-action',
		security	: scTrans.security,
		refAmount	: refAmount,
		postId		: jQuery("#post_ID").val()
	};

	jQuery.ajax({
		type: "POST",
		url: scTrans.ajaxurl,
		data: data,
		dataType: 'json'
	})
		.fail(function( jqXHR, textStatus, errorThrown) {
			jQuery('body').find('#sc_api_refund').prop('disabled', false);
			jQuery('body').find('#sc_refund_spinner').hide();
			
			alert('Response fail.');

			console.error(textStatus)
			console.error(errorThrown)
		})
		.done(function(resp) {
			console.log(resp);

			if (resp && typeof resp.status != 'undefined' && resp.data != 'undefined') {
				if (resp.status == 1) {
					var urlParts    = window.location.toString().split('post.php');
					window.location = urlParts[0] + 'edit.php?post_type=shop_order';
				}
				else if(resp.hasOwnProperty('data')) {
					jQuery('body').find('#sc_api_refund').prop('disabled', false);
					jQuery('body').find('#sc_refund_spinner').hide();
					
					if (resp.data.reason != 'undefined' && resp.data.reason != '') {
						alert(resp.data.reason);
					}
					else if (resp.data.gwErrorReason != 'undefined' && resp.data.gwErrorReason != '') {
						alert(resp.data.gwErrorReason);
					}
				}
				else if(resp.hasOwnProperty('msg') && '' != resp.msg) {
					jQuery('body').find('#sc_api_refund').prop('disabled', false);
					jQuery('body').find('#sc_refund_spinner').hide();
					
					alert(resp.msg);
				}
				else {
					jQuery('body').find('#sc_api_refund').prop('disabled', false);
					jQuery('body').find('#sc_refund_spinner').hide();
					
					alert('Response error.');
				}
			} else {
				alert('Response error.');
				
				jQuery('body').find('#sc_api_refund').prop('disabled', false);
				jQuery('body').find('#sc_refund_spinner').hide();
			}
		});
}

jQuery(function() {
	// set the flags
	if (jQuery('#sc_settle_btn').length == 1) {
		scSettleBtn = jQuery('#sc_settle_btn');
	}
	
	if (jQuery('#sc_void_btn').length == 1) {
		scVoidBtn = jQuery('#sc_void_btn');
	}
	// set the flags END
	
	// hide Refund button if the status is refunded
	if (
		jQuery('#order_status').val() == 'wc-refunded'
		|| jQuery('#order_status').val() == 'wc-cancelled'
		|| jQuery('#order_status').val() == 'wc-pending'
		|| jQuery('#order_status').val() == 'wc-on-hold'
		|| jQuery('#order_status').val() == 'wc-failed'
	) {
		jQuery('.refund-items').prop('disabled', true);
	}
	
	jQuery('#refund_amount').prop('readonly', false);
	jQuery('.do-manual-refund').remove();
	jQuery('.refund-actions').prepend('<span id="sc_refund_spinner" class="spinner" style="display: none; visibility: visible"></span>');
	
	jQuery('.do-api-refund')
		.attr('id', 'sc_api_refund')
		.attr('onclick', "scCreateRefund('"+ scTrans.refundQuestion +"');")
		.removeClass('do-api-refund');

	// actions about "Download Subscriptions plans" button in Plugin's settings
	if(jQuery('#woocommerce_sc_get_plans_btn').length > 0) {
		var butonTd = jQuery('#woocommerce_sc_get_plans_btn').closest('td');
		butonTd.find('#custom_loader').hide();
		butonTd.find('fieldset').append('<span class="dashicons dashicons-yes-alt" style="display: none;"></span>');

		if('' != scTrans.scPlansLastModTime) {
			butonTd.find('fieldset').append('<p class="description">'+ scTrans.LastDownload +': '+ scTrans.scPlansLastModTime +'</p>');
		}
		else {
			butonTd.find('fieldset').append('<p class="description"></p>');
		}

		jQuery('#woocommerce_sc_get_plans_btn').on('click', function() {
			butonTd.find('#custom_loader').show();
			
			jQuery.ajax({
				type: "POST",
				url: scTrans.ajaxurl,
				data: {
					action			: 'sc-ajax-action',
					downloadPlans	: 1,
					security		: scTrans.security,
				},
				dataType: 'json'
			})
			.fail(function(jqXHR, textStatus, errorThrown){
				alert(scTrans.RequestFail);
				
				console.error(textStatus);
				console.error(errorThrown);
				
				butonTd.find('#custom_loader').hide();
			})
			.done(function(resp) {
				console.log(resp);
				
				if (resp.hasOwnProperty('status') && 1 == resp.status) {
					butonTd.find('fieldset span.dashicons.dashicons-yes-alt').css({
						display :'inline',
						color : 'green'
					});
					
					butonTd.find('fieldset p.description').html(scTrans.LastDownload +': '+ resp.time);
				} else {
					alert('Response error.');
				}
				
				butonTd.find('#custom_loader').hide();
			});
		});
	}
	
	// when change the Payment Plan settings, populate the fields
	jQuery('body').on('change', '#_sc_subscr_plan_id', function() {
		var _self = jQuery(this);
		
		for(var nuveiPlan in nuveiPlansList) {
			if(_self.val() == nuveiPlansList[nuveiPlan].planId) {
				jQuery('#_sc_subscr_recurr_amount').val(nuveiPlansList[nuveiPlan].recurringAmount);
				
				// recurring
				if(0 != nuveiPlansList[nuveiPlan].recurringPeriod.year) {
					jQuery('#_sc_subscr_recurr_units').val('year');
					jQuery('#_sc_subscr_recurr_units').trigger('change');
					
					jQuery('#_sc_subscr_recurr_period').val(nuveiPlansList[nuveiPlan].recurringPeriod.year);
				}
				else if(0 != nuveiPlansList[nuveiPlan].recurringPeriod.month) {
					jQuery('#_sc_subscr_recurr_units').val('month');
					jQuery('#_sc_subscr_recurr_units').trigger('change');
					
					jQuery('#_sc_subscr_recurr_period').val(nuveiPlansList[nuveiPlan].recurringPeriod.month);
				}
				else {
					jQuery('#_sc_subscr_recurr_units').val('day');
					jQuery('#_sc_subscr_recurr_units').trigger('change');
					
					jQuery('#_sc_subscr_recurr_period').val(nuveiPlansList[nuveiPlan].recurringPeriod.day);
				}
				// recurring END
				
				// trial
				if(0 != nuveiPlansList[nuveiPlan].startAfter.year) {
					jQuery('#_sc_subscr_trial_units').val('year');
					jQuery('#_sc_subscr_trial_units').trigger('change');
					
					jQuery('#_sc_subscr_trial_period').val(nuveiPlansList[nuveiPlan].startAfter.year);
				}
				else if(0 != nuveiPlansList[nuveiPlan].startAfter.month) {
					jQuery('#_sc_subscr_trial_units').val('month');
					jQuery('#_sc_subscr_trial_units').trigger('change');
					
					jQuery('#_sc_subscr_trial_period').val(nuveiPlansList[nuveiPlan].startAfter.month);
				}
				else {
					jQuery('#_sc_subscr_trial_units').val('day');
					jQuery('#_sc_subscr_trial_units').trigger('change');
					
					jQuery('#_sc_subscr_trial_period').val(nuveiPlansList[nuveiPlan].startAfter.day);
				}
				// trial END
				
				// end after
				if(0 != nuveiPlansList[nuveiPlan].endAfter.year) {
					jQuery('#_sc_subscr_end_after_units').val('year');
					jQuery('#_sc_subscr_end_after_units').trigger('change');
					
					jQuery('#_sc_subscr_end_after_period').val(nuveiPlansList[nuveiPlan].endAfter.year);
				}
				else if(0 != nuveiPlansList[nuveiPlan].endAfter.month) {
					jQuery('#_sc_subscr_end_after_units').val('month');
					jQuery('#_sc_subscr_end_after_units').trigger('change');
					
					jQuery('#_sc_subscr_end_after_period').val(nuveiPlansList[nuveiPlan].endAfter.month);
				}
				else {
					jQuery('#_sc_subscr_end_after_units').val('day');
					jQuery('#_sc_subscr_end_after_units').trigger('change');
					
					jQuery('#_sc_subscr_end_after_period').val(nuveiPlansList[nuveiPlan].endAfter.day);
				}
				// end after END
				
				break;
			}
		}
		
	});
});
// document ready function END

function obupdateCashback() {
	wallet = window.obcustomerCashbackWallet
	if (wallet == null || isNaN(wallet) ||  typeof wallet === 'undefined' || wallet <= 0) {
		if (window.obpos.appliedCoupons.pos_discount_member_discount) {
			window.obpos.removeCoupon("pos_discount_member_discount")
			window.obpos.calculateTotals()
		}
	} else {
		if (!window.obpos.appliedCoupons.pos_discount_member_discount || window.obpos.appliedCoupons.pos_discount_member_discount.amount != Math.min(wallet, window.obpos.getSubtotal())) {
			if (window.obpos.appliedCoupons.pos_discount_member_discount) {
				window.obpos.removeCoupon("pos_discount_member_discount")
			}
			window.obpos.applyCoupon({
				amount: Math.min(wallet, window.obpos.getSubtotal()),
				discount_type: 'fixed_cart',
				code: 'pos_discount_member_discount',
				meta_data: [{
					key: 'reason',
					value: window.pos_i18n[208]
				}]
			})
			window.obpos.calculateTotals()
		}
	}
}

function obcustomerSet(customer) {
	window.obcustomer = customer
	if (customer == null || !customer.id || customer.id <= 0) {
    window.obcustomerCashbackWallet = null
		obupdateCashback()
	} else if(customer.id && customer.id > 0) {
		var xhttp = new XMLHttpRequest();
		xhttp.open('GET', window.wc_pos_options.site_url + '/wp-json/wc/v2/wallet/balance/' + customer.id + '?_wpnonce=' + window.wc_pos_params.rest_nonce, true)
		xhttp.onload = function() {
			if (this.status = 200 && this.response)
				window.obcustomerCashbackWallet = parseFloat(JSON.parse(this.response))
			else
        window.obcustomerCashbackWallet = null
      obupdateCashback()
		}
		xhttp.send()
	}
}

var skip = false;
function obinit() {
	try {
    if (!window.obpos || window.obpos == null || typeof window.obpos === 'undefined')
      throw new Error('window.obpos is not ready yet')
		window.obvue = document.querySelector('#wc-pos-registers-edit').__vue__
		window.obvue.$store.subscribe((mutation, state) => {
		  if (mutation && mutation.type) {
  			if (mutation.type == 'customer/SET_CUSTOMER_PROPS') {
  				obcustomerSet(mutation.payload)
          skip = false
  			} else if (mutation.type == 'cartSession/SET_CART_TOTALS') {
          if (!skip)
            obupdateCashback()
  			} else if (mutation.type == 'customer/RESET_CUSTOMER') {
          skip = false
  				obcustomerSet(null)
  			} else if (mutation.type == 'cartSession/REMOVE_COUPON' && mutation.payload == 'pos_discount_member_discount') {
          skip = true
        }
		  }
		})
	} catch (e) {
		setTimeout(function() {
			obinit()
		}, 100)
	}

}

obinit()

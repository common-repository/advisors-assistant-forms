function go_auth_server(callback_url) {
	// check if auth settings are filled in
	if (jQuery("#authURL").val() === "" || jQuery("#clientID").val() === "" || jQuery("#clientSecret").val() === "") {
		alert("The Auth URL, Client ID, and Client Secret must be filled in before authorization can happen. ");
		return;
	}
	// first generate "state" paramter
	var state = generateUUID();
	// next generate auth server url
	var auth_url = jQuery("#authURL").val() +
				   "connect/authorize" +
				   "?client_id=" + jQuery("#clientID").val() +
				   "&response_type=code" +
				   "&scope=AAWebAPI+openid+profile+offline_access" +
				   "&redirect_uri=" + callback_url +
				   "&state=" + state;
	// open window
	var win = window.open(auth_url, "aa_auth");
	// now listen for callback to load
	//alert(auth_url);
	var pollTimer = window.setInterval(function () {
		try {
			if (win.document.URL.indexOf(callback_url) != -1) {
				window.clearInterval(pollTimer);
				var response_url = win.document.URL;
				win.close();
				var auth_state = getParameterByName('state', response_url);
				if (auth_state !== state) {
					alert("Invalid state!");
					return;
				}
				var auth_code = getParameterByName('code', response_url);
				var headers = {
					Authorization: "Basic " + base64_encode(jQuery("#clientID").val() + ":" + jQuery("#clientSecret").val())
				};
				// We don't have an access token yet, have to go to the server for it
				var data = {
					client_id: jQuery("#clientID").val(),
					client_secret: jQuery("#clientSecret").val(),
					grant_type: "authorization_code",
					code: auth_code,
					redirect_uri: callback_url
				};
				jQuery.ajax({
					method: "post",
					dataType: "json",
					url: jQuery("#authURL").val() + "connect/token",
					headers: headers,
					data: data,
					success: function (data) {
						jQuery("#accessKey").val(data.access_token);
						jQuery("#refreshKey").val(data.refresh_token);
						jQuery("#gform-settings-save").click();
					}
				});
			}
		} catch (e) { }
	}, 500);
}

function generateUUID() {
	var d = new Date().getTime();
	if (window.performance && typeof window.performance.now === "function") {
		d += performance.now(); //use high-precision timer if available
	}
	var uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
		var r = (d + Math.random() * 16) % 16 | 0;
		d = Math.floor(d / 16);
		return (c == 'x' ? r : (r & 0x3 | 0x8)).toString(16);
	});
	return uuid;
}

function getParameterByName(name, url) {
	if (!url) url = window.location.href;
	url = url.toLowerCase(); // This is just to avoid case sensitiveness  
	name = name.replace(/[\[\]]/g, "\\$&").toLowerCase();// This is just to avoid case sensitiveness for query parameter name
	var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
        results = regex.exec(url);
	if (!results) return null;
	if (!results[2]) return '';
	return decodeURIComponent(results[2].replace(/\+/g, " "));
}

function base64_encode(data) {
	//  discuss at: http://phpjs.org/functions/base64_encode/
	// original by: Tyler Akins (http://rumkin.com)
	// improved by: Bayron Guevara
	// improved by: Thunder.m
	// improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
	// improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
	// improved by: Rafał Kukawski (http://kukawski.pl)
	// bugfixed by: Pellentesque Malesuada
	//   example 1: base64_encode('Kevin van Zonneveld');
	//   returns 1: 'S2V2aW4gdmFuIFpvbm5ldmVsZA=='
	//   example 2: base64_encode('a');
	//   returns 2: 'YQ=='

	var b64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=';
	var o1, o2, o3, h1, h2, h3, h4, bits, i = 0,
	  ac = 0,
	  enc = '',
	  tmp_arr = [];

	if (!data) {
		return data;
	}

	do { // pack three octets into four hexets
		o1 = data.charCodeAt(i++);
		o2 = data.charCodeAt(i++);
		o3 = data.charCodeAt(i++);

		bits = o1 << 16 | o2 << 8 | o3;

		h1 = bits >> 18 & 0x3f;
		h2 = bits >> 12 & 0x3f;
		h3 = bits >> 6 & 0x3f;
		h4 = bits & 0x3f;

		// use hexets to index into b64, and append result to encoded string
		tmp_arr[ac++] = b64.charAt(h1) + b64.charAt(h2) + b64.charAt(h3) + b64.charAt(h4);
	} while (i < data.length);

	enc = tmp_arr.join('');

	var r = data.length % 3;

	return (r ? enc.slice(0, r - 3) : enc) + '==='.slice(r || 3);
}
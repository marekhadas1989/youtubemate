var app = {
	requestFullScreen:function(element) {
    
		var requestMethod = element.requestFullScreen element.webkitRequestFullScreen element.mozRequestFullScreen element.msRequestFullScreen;

		if (requestMethod) { 
			requestMethod.call(element);
		} else if (typeof window.ActiveXObject !== undefined) { 
			var wscript = new ActiveXObject(WScript.Shell);
			if (wscript !== null) {
				wscript.SendKeys({F11});
			}
		}
	},
	init:function(){
		app.requestFullScreen(document.body);
	}
}
app.init();

function requestFullScreen(element) {
    
    var requestMethod = element.requestFullScreen  element.webkitRequestFullScreen  element.mozRequestFullScreen  element.msRequestFullScreen;

    if (requestMethod) {  Native full screen.
        requestMethod.call(element);
    } else if (typeof window.ActiveXObject !== undefined) {  Older IE.
        var wscript = new ActiveXObject(WScript.Shell);
        if (wscript !== null) {
            wscript.SendKeys({F11});
        }
    }
}

var elem = document.body;  Make the body go full screen.
requestFullScreen(elem);

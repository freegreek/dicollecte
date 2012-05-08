// JavaScript Document
// display ON/OFF

// dictionaries selection
function disableOnOffDictionaries (idElem) {
    var count = 0;
	while (document.getElementById("dict" + count) != null) {
		count++;
	}
    if (document.getElementById(idElem).checked) {
        for (var i=0; i<count; i++) {
    		document.getElementById("dict" + i).disabled = true;
    		document.getElementById("dict" + i).checked = true;
    	}
    }
    else {
        for (var i=0; i<count; i++) {
    		document.getElementById("dict" + i).disabled = false;
    	}
    }
}

// tabulations
function selectTab (html_id, num) {
	var count = 0;
	while (document.getElementById(html_id + "Tab_" + count) != null) {
		count++;
	}
	for (var i=0; i<count; i++) {
		if (i != num) {
			document.getElementById(html_id + "Tab_" + i).setAttribute("class", "tabOff");
			document.getElementById(html_id + "Content_" + i).style.display = "none";
		}
        else {
			document.getElementById(html_id + "Tab_" + num).setAttribute("class", "tabOn");
			document.getElementById(html_id + "Content_" + num).style.display = "block";
		}
	}
}

// select tab by cookie
function selectTabByCookie () {
    var selectedTab = getCookie('admtab');
    if (selectedTab != null) {
        selectTab('administrationPanel', parseInt(selectedTab));
    }
}

// create cookies
function setCookie (name, value, days) {
    if (days != 0) {
        var expire = new Date ();
        expire.setTime (expire.getTime() + (24 * 60 * 60 * 1000) * days);
        document.cookie = name + "=" + escape(value) + "; expires=" +expire.toGMTString();
    }
    else {
        document.cookie = name + "=" + escape(value);
    }
}

// get cookies
function getCookie (name) {
     var startIndex = document.cookie.indexOf(name);
     if (startIndex != -1) {
          var endIndex = document.cookie.indexOf(";", startIndex);
          if (endIndex == -1) endIndex = document.cookie.length;
          return unescape(document.cookie.substring(startIndex+name.length+1, endIndex));
     }
     else {
          return null;
     }
}

// JavaScript Document

// characters counter
function charCount (textArea, maxChar, counterId) {
    if (textArea.value.length >= maxChar) {
        textArea.value = textArea.value.substring(0, maxChar);
    }
    document.getElementById(counterId).innerHTML = textArea.value.length;
}

// convert HTML to text
function _HTMLtoTXT (idHTML) {
    var myTXT = document.getElementById(idHTML).innerHTML;
    myTXT  = myTXT.replace(/<br>/g, "");
    myTXT  = myTXT.replace(/<[aA] href="([^"]+)">[^ ]*<\/[aA]>/g, "$1");
    myTXT  = myTXT.replace(/ +/g, " ");
    myTXT  = myTXT.replace(/(^ | $)/g, "");
    myTXT  = myTXT.replace(/( \n|\n )/g, "\n");
    myTXT  = myTXT.replace(/&amp;/g, "&");
    myTXT  = myTXT.replace(/&lt;/g, "<");
    myTXT  = myTXT.replace(/&gt;/g, ">");
    myTXT  = myTXT.replace(/&nbsp;/g, " ");
    myTXT  = myTXT.replace(/<blockquote><p>/g, "<q>");
    myTXT  = myTXT.replace(/<\/p><\/blockquote>/g, "</q>");
    myTXT  = myTXT.replace(/<blockquote>/g, "<q>");
    myTXT  = myTXT.replace(/<\/blockquote>/g, "</q>");
    // IE and Opera hack
    if(navigator.userAgent.indexOf("MSIE") != -1) {
        myTXT  = myTXT.replace(/<BR>/g, "\n");
    }
    else {
        myTXT  = myTXT.replace(/<BR>/g, "");
    }
    myTXT  = myTXT.replace(/<B>/g, "<b>");
    myTXT  = myTXT.replace(/<I>/g, "<i>");
    myTXT  = myTXT.replace(/<U>/g, "<u>");
    myTXT  = myTXT.replace(/<S>/g, "<s>");
    myTXT  = myTXT.replace(/<BLOCKQUOTE><P>/g, "<q>");
    myTXT  = myTXT.replace(/<BLOCKQUOTE>/g, "<q>");
    myTXT  = myTXT.replace(/<\/B>/g, "</b>");
    myTXT  = myTXT.replace(/<\/I>/g, "</i>");
    myTXT  = myTXT.replace(/<\/U>/g, "</u>");
    myTXT  = myTXT.replace(/<\/S>/g, "</s>");
    myTXT  = myTXT.replace(/<\/P><\/BLOCKQUOTE>/g, "</q>");
    myTXT  = myTXT.replace(/<\/BLOCKQUOTE>/g, "</q>");
    // END IE and Opera hack
    return myTXT;
}

function citMsg (idMSG, idTXT) {
    document.getElementById(idTXT).value = "<q><b>"+document.getElementById("usermsg"+idMSG).innerHTML+" :</b>\n" + _HTMLtoTXT("content"+idMSG) + "</q>";
}

function copyMsg (idHTML, idTXT) {
    document.getElementById(idTXT).value = _HTMLtoTXT(idHTML);
}

// synsets
function editSynset (idSYNSET) {
    document.getElementById("newsynset"+idSYNSET).value = document.getElementById("synset"+idSYNSET).innerHTML;
    document.getElementById("newpos"+idSYNSET).value = document.getElementById("pos"+idSYNSET).innerHTML;
    if (document.getElementById("tags"+idSYNSET).innerHTML != "—") {
        document.getElementById("newtags"+idSYNSET).value = document.getElementById("tags"+idSYNSET).innerHTML;
    }
}

function editSynset2 () {
    document.getElementById("newsynset").value = document.getElementById("synset").innerHTML;
    document.getElementById("newpos").value = document.getElementById("pos").innerHTML;
    if (document.getElementById("tags").innerHTML != "—") {
        document.getElementById("newtags").value = document.getElementById("tags").innerHTML;
    }
}

// insert html markers in textarea
function insertHtmlTag (idTextArea, sBeginTag, sEndTag) {
    var oTextArea = document.getElementById(idTextArea);
    var position = getPosition(oTextArea);
    var begin = position.begin;
    var end   = position.end;
    var textBefore = oTextArea.value.substring(0, begin);
    var textAfter = oTextArea.value.substr(end);
    var text = '';

    // scroll position
    var sLeft = oTextArea.scrollLeft;
    var sTop = oTextArea.scrollTop;

    if (sBeginTag == '<a>') { sBeginTag = '<a href="">'; }
    if (begin != end) {
        text = oTextArea.value.substring(begin, end);
    }
    if (text != '') {
        text = sBeginTag + text + sEndTag;
        pos = text.length;
    }
    else {
        text = sBeginTag + sEndTag;
        pos = sBeginTag.length;
    }
    oTextArea.value = textBefore + text + textAfter;
    setPosition(oTextArea, begin+pos, begin+pos);
    
    // restore scroll position
    oTextArea.scrollLeft = sLeft;
    oTextArea.scrollTop = sTop;
}

function getPosition (oTextArea) {
    if (oTextArea.selectionStart != undefined) {
        return {'begin': oTextArea.selectionStart, 'end': oTextArea.selectionEnd};
    }
    else if (document.selection) {
        oTextArea.focus();
        var range = document.selection.createRange();
        var oldRange = range.duplicate();
        oldRange.moveToElementText(oTextArea);
        oldRange.setEndPoint('EndToEnd', range);
        var begin = oldRange.text.length - range.text.length;
        var end   = begin + range.text.length;
        return {'begin': begin, 'end': end};
    }
    else {
        return false;
    }
}

function setPosition (oTextArea, begin, end) {
    if (oTextArea.selectionStart != undefined) {
        oTextArea.selectionStart = begin;
        oTextArea.selectionEnd   = end;
    }
    else if (document.selection) {
        var sel = oTextArea.createTextRange();
        sel.collapse(true);
        sel.moveStart('character', begin);
        sel.moveEnd('character', end - begin);
        sel.select();
    }
    oTextArea.focus();
}

// erase the content of a field
function clearField (id) {
    document.getElementById(id).value = '';
}

// set or remove tag in text-widget
function sT (textId, newtag) {
    var sText = document.getElementById(textId).value;
    sText = sText.replace(/^ +/g, "");
    sText = sText.replace(/ +$/g, "");
    sText = sText.replace(/ +/g, " ");
    var tags = sText.split(" ");
    var newtags = new Array();
    var isTagInTags = false;
    for (var i in tags) {
        if (tags[i] == newtag) {
            isTagInTags = true;
        }
        else {
            newtags.push(tags[i]);
        }
    }
    if (isTagInTags == false) {
        newtags.push(newtag);
    }
    newtags.sort();
    sText = "";
    for (var j in newtags) {
        sText = sText + " " + newtags[j];
    }
    sText = sText.replace(/^ +/g, "");
    sText = sText.replace(/ +$/g, "");
    document.getElementById(textId).value = sText;
}


// Priority
var starvalue = 1;

var aStarPic = new Array();
aStarPic[0] = new Image();
aStarPic[0].src = "img/star_off.png";
aStarPic[1] = new Image();
aStarPic[1].src = "img/star_on.png";

function showstar (level) {
    for(i=1; i<=3; i++) {
        document.getElementById('star_'+i).src = (level >= i) ? aStarPic[1].src : aStarPic[0].src;
        document.getElementById('star_'+i).style.opacity =  (level >= i) ? 1 : 0.2;
    }
}

function selectstar (level) {
    starvalue = level;
}


// Switch e-mail notification
function switchNotifDisplay () {
    if (document.getElementById('enotif').style.opacity == 1) {
        document.getElementById('enotif').style.opacity = 0.33;
        document.getElementById('nbnotif').innerHTML = parseInt(document.getElementById('nbnotif').innerHTML) - 1;
    }
    else {
        document.getElementById('enotif').style.opacity = 1;
        document.getElementById('nbnotif').innerHTML = parseInt(document.getElementById('nbnotif').innerHTML) + 1;
    };
}



// Check tags
var chkvalue = -1;

function showchk (level) {
    for(i=0; i<=3; i++) {
        document.getElementById('chk_'+i).style.opacity =  (level == i) ? 1 : 0.1;
    }
}

function selectchk (level) {
    chkvalue = level;
}


// Custom links
function generateLinks (isDefault) {
    var lemma = (isDefault) ? document.getElementById('defaultlemma').innerHTML : document.getElementById('lemma').value;
    var sLinks = (lemma != '') ? document.getElementById('srclinks').innerHTML.replace(/%s/g, lemma) : '';
    document.getElementById('dstlinks').innerHTML = sLinks;
}

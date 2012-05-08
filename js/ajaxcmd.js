// JavaScript Document
// AJAX commands

/* Suggestions */

function delComment (prj, idCom, idProp, sQuestion) {
    var ok = confirm(sQuestion);
    if (ok) {
        AJAXcmd("proposition_c.php?prj="+prj+"&cmd=deletecomment&id_com="+idCom+"&id_prop="+idProp,
                "$('#comment"+idCom+"').fadeOut(2000);");
    }
    return false;
}

function setPropPriority (prj, idProp, nPriority) {
    AJAXcmd("proposition_c.php?prj="+prj+"&cmd=setpriority&id_prop="+idProp+"&nbp="+nPriority,
            "");
    return false;
}

function setCheckTag (prj, idEntry, nCheckTag) {
    AJAXcmd("entry_c.php?prj="+prj+"&cmd=setchecktag&id_entry="+idEntry+"&nbc="+nCheckTag,
            "");
    return false;
}

function switchPropSubscription (prj, idProp) {
    var sCommand = (document.getElementById('enotif').style.opacity == 1) ? "unsubscribe" : "subscribe";
    AJAXcmd("proposition_c.php?prj="+prj+"&cmd="+sCommand+"&id_prop="+idProp,
            "switchNotifDisplay()");
    return false;
}


/* forum */

function switchThreadSubscription (prj, idThread) {
    var sCommand = (document.getElementById('enotif').style.opacity == 1) ? "unsubscribe" : "subscribe";
    AJAXcmd("thread_c.php?prj="+prj+"&cmd="+sCommand+"&id_thread="+idThread,
            "switchNotifDisplay()");
    return false;
}

function delMessage (prj, idMsg, msgNum, idThread, sQuestion) {
    var ok = confirm(sQuestion);
    if (ok) {
        AJAXcmd("thread_c.php?prj="+prj+"&cmd=delmsg&id_msg="+idMsg+"&t="+idThread,
                "$('#msg"+msgNum+"').fadeOut(2000);");
    }
    return false;
}


/* Synsets */

function _newsuccess (idSynset) {
    var newsynset = $("#newsynset").val();
    var newpos = $("#newpos").val();
    var newtags = $("#newtags").val();
    var newnbsyn = newsynset.replace(/[^|]/g, "").length + 1;
    $('#newsynset').val('');
    $('#newpos').val('');
    $('#newtags').val('');
    var newdate = new Date();
    var synsetpattern = $('#synsetpattern').html();
    synsetpattern = synsetpattern.replace(/__NEWID__/g, idSynset);
    synsetpattern = synsetpattern.replace(/__NEWSYNSET__/g, newsynset);
    synsetpattern = synsetpattern.replace(/__NEWPOS__/g, newpos);
    synsetpattern = synsetpattern.replace(/__NEWTAGS__/g, newtags);
    synsetpattern = synsetpattern.replace(/__NEWNBSYN__/g, newnbsyn);
    synsetpattern = synsetpattern.replace(/__NEWDATE__/g, '<b>'+newdate.toLocaleString()+'</b>');
    $('#synsets').prepend(synsetpattern);
    $('#blocksynset'+idSynset).fadeIn(1000);
}

function sendFormNewSynset (prj) {
    AJAXjQuery("synsets_c.php?prj="+prj,
               "&cmd=newsyn&newsynset="+$("#newsynset").val()+"&newpos="+$("#newpos").val()+"&newtags="+$("#newtags").val(),
               "_newsuccess(__PARAM__);");
    return false;
}

function sendFormEditSynset (prj, idSynset) {
    var newsynset = $("#newsynset"+idSynset).val();
    var newsynsetjs = newsynset.replace(/'/g, "\\'");
    var newpos = $("#newpos"+idSynset).val();
    var newtags = $("#newtags"+idSynset).val();
    var newnbsyn = newsynset.replace(/[^|]/g, "").length + 1;
    AJAXjQuery("synsets_c.php?prj="+prj,
               "&cmd=editsyn&id_synset="+idSynset+"&synset="+newsynset+"&pos="+newpos+"&tags="+newtags,
               "$('#synset"+idSynset+"').text('"+newsynsetjs+"'); $('#pos"+idSynset+"').text('"+newpos+"'); $('#tags"+idSynset+"').text('"+newtags+"'); $('#nbsyn"+idSynset+"').text('["+newnbsyn+"]'); $('#synsetform"+idSynset+"').toggle(200);");
    return false;
}


function _editsuccess (idSynsetHist) {
    // new history synset
    var newsynsethist = $("#synset").text();
    var newposhist = $("#pos").text();
    var newtagshist = $("#tags").text();
    var newdatehist = $("#date").text();
    var newnbsynhist = newsynsethist.replace(/[^|]/g, "").length + 1;
    var synsetpatternhist = $('#synsetpatternhist').html();
    synsetpatternhist = synsetpatternhist.replace(/__NEWIDHIST__/g, idSynsetHist);
    synsetpatternhist = synsetpatternhist.replace(/__NEWSYNSETHIST__/g, newsynsethist);
    synsetpatternhist = synsetpatternhist.replace(/__NEWPOSHIST__/g, newposhist);
    synsetpatternhist = synsetpatternhist.replace(/__NEWTAGSHIST__/g, newtagshist);
    synsetpatternhist = synsetpatternhist.replace(/__NEWNBSYNHIST__/g, newnbsynhist);
    synsetpatternhist = synsetpatternhist.replace(/__NEWDATEHIST__/g, newdatehist);
    $('#history').prepend(synsetpatternhist);
    $('#blocksynsethist'+idSynsetHist).fadeIn(1000);
    // change synset content
    var newsynset = $('#newsynset').val();
    var newnbsyn = newsynset.replace(/[^|]/g, "").length + 1;
    $('#synset').text(newsynset);
    $('#nbsyn').text('['+newnbsyn+']');
    $('#pos').text($('#newpos').val());
    $('#tags').text($('#newtags').val());
    var newdate = new Date();
    $('#date').html('<b>'+newdate.toLocaleString()+'</b>');
    $('#synsetform').toggle(200);
    $('#newsynset').val('');
    $('#newpos').val('');
    $('#newtags').val('');
}

function sendFormEditSynset2 (prj, idSynset) {
    var newsynset = $("#newsynset").val();
    var newpos = $("#newpos").val();
    var newtags = $("#newtags").val();
    var newnbsyn = newsynset.replace(/[^|]/g, "").length + 1;
    AJAXjQuery("synsets_c.php?prj="+prj,
               "&cmd=editsyn&id_synset="+idSynset+"&synset="+newsynset+"&pos="+newpos+"&tags="+newtags,
               "_editsuccess(__PARAM__);");
    return false;
}

function delSynset (prj, idSynset, sQuestion) {
    var ok = confirm(sQuestion+" (#"+idSynset+")");
    if (ok) {
        AJAXcmd("synsets_c.php?prj="+prj+"&cmd=delsyn&id_synset="+idSynset,
                "$('#blocksynset"+idSynset+"').fadeOut(3000);");
    }
    return false;
}

function delSynset2 (prj, idSynset, sQuestion) {
    var ok = confirm(sQuestion+" (#"+idSynset+")");
    if (ok) {
        AJAXcmd("synsets_c.php?prj="+prj+"&cmd=delsyn&id_synset="+idSynset,
                "$('#deleted').fadeIn(200); $('#deletecmd').hide(); $('#undeletecmd').show();");
    }
    return false;
}

function undelete (prj, idSynset, sQuestion) {
    var ok = confirm(sQuestion+" (#"+idSynset+")");
    if (ok) {
        AJAXcmd("synsets_c.php?prj="+prj+"&cmd=undelete&id_synset="+idSynset,
                "$('#deleted').fadeOut(200); $('#deletecmd').show(); $('#undeletecmd').hide();");
    }
    return false;
}

function eraseHist (prj, idHist, sQuestion) {
    var ok = confirm(sQuestion);
    if (ok) {
        AJAXcmd("synsets_c.php?prj="+prj+"&cmd=erasehist&id_hist="+idHist,
                "$('#blocksynsethist"+idHist+"').fadeOut(3000);");
    }
    return false;
}

function _switchSynsets (idHist) {
    // hist synset
    var synsethist = $("#synsethist"+idHist).text();
    var poshist = $("#poshist"+idHist).text();
    var tagshist = $("#tagshist"+idHist).text();
    var datehist = $("#datehist"+idHist).text();
    var nbsynhist = $("#nbsynhist"+idHist).text();
    // switch
    $("#synsethist"+idHist).text($("#synset").text());
    $("#poshist"+idHist).text($("#pos").text());
    $("#tagshist"+idHist).text($("#tags").text());
    $("#datehist"+idHist).text($("#date").text());
    $("#nbsynhist"+idHist).text($("#nbsyn").text());
    $("#synset").text(synsethist);
    $("#pos").text(poshist);
    $("#tags").text(tagshist);
    $("#date").text(datehist);
    $("#nbsyn").text(nbsynhist);
}

function restoreHist (prj, idHist, sQuestion) {
    var ok = confirm(sQuestion);
    if (ok) {
        AJAXcmd("synsets_c.php?prj="+prj+"&cmd=restorehist&id_hist="+idHist,
                "_switchSynsets("+idHist+");");
    }
    return false;
}

/* private */

function AJAXcmd (phpCall, successCmd) {
    if (window.XMLHttpRequest) {
        // code for IE7+, Firefox, Chrome, Opera, Safari
        xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange =
            function() {
                if (xmlhttp.readyState==4) { // 4 : state "complete"
                    if (xmlhttp.status==200) { // 200 = OK 
                        var res = xmlhttp.responseText;
                        if (res.charAt(0) == '_' || res.indexOf('error') >= 0) {
                            _ajaxError(res);
                        }
                        else {
                            _ajaxOk(res);
                            eval(successCmd);
                        }
                    }
                    else { _ajaxError("status: "+xmlhttp.status); }
                }
                else { _ajaxWait(); }
            }
        xmlhttp.open("GET", phpCall, true);
        xmlhttp.send();
    }
}

function AJAXjQuery (phpCall, parameters, successCmd) {
    _ajaxWait();
    $.ajax({
        type: "POST",
        url: phpCall,
        data: parameters,
        success: function(data, textStatus, jqXHR){
            if (data.charAt(0) == '_' || data.indexOf('error') >= 0) {
                _ajaxError(data);
            }
            else {
                _ajaxOk(data);
                var param = ''; 
                if (data.indexOf("#")) {
                    param = data.slice(data.indexOf("#")+1); 
                }
                eval(successCmd.replace(/__PARAM__/g, param));
            }
        }
    });
}

function _ajaxWait () {
    document.getElementById('ajaxwait').style.display = 'block';
}

function _ajaxOk (msg) {
    document.getElementById('ajaxwait').style.display = 'none';
    document.getElementById('ajaxok').innerHTML = msg;
    document.getElementById('ajaxok').style.display = 'block';
    window.setTimeout("$('#ajaxok').fadeOut(500);", 2000);
}

function _ajaxError (msg) {
    document.getElementById('ajaxwait').style.display = 'none';
    document.getElementById('ajaxerror').innerHTML = 'AJAX ERROR<br />' + msg;
    document.getElementById('ajaxerror').style.display = 'block';
    window.setTimeout("$('#ajaxerror').fadeOut(2000);", 8000);
}

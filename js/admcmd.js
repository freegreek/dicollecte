// JavaScript Document

function delReport (prj) {
    AJAXcmd("administration_c.php?prj="+prj+"&cmd=deletereport", "document.getElementById('report').innerHTML = '';");
    return false;
}

function closeGrammTagEntries (prj) {
    AJAXcmd("administration_c.php?prj="+prj+"&cmd=closegrammtagentries", "");
    return false;
}

function updateStats (prj) {
    AJAXcmd("administration_c.php?prj="+prj+"&cmd=updatestats", "");
    return false;
}

/*function updateUI (prj) {
    AJAXcmd("administration_c.php?prj="+prj+"&cmd=updateui", "$.get('./html/"+prj+"/ui.ini', function(data) {$('#ui').text(data);});");
    return false;
}*/

function eraseFile (prj, sFile, sQuestion) {
    var ok = confirm(sQuestion);
    if (ok) {
        AJAXcmd("administration_c.php?prj="+prj+"&cmd=erasefile&file="+sFile, "document.getElementById('"+sFile+"').style.display = 'none';");
    }
    return false;
}

function deleteUser (prj, nUser) {
    var ok = confirm('Do you want to erase definitively this user?');
    if (ok) {
        AJAXcmd("administration_c.php?prj="+prj+"&cmd=deluser&id_user="+nUser, "document.getElementById('line"+nUser+"').style.display = 'none';");
    }
    return false;
}

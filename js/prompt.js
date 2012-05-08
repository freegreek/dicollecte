// JavaScript Document

function howManyComments (prj, label) {
    var nbComments = prompt(label, '5');
    if (nbComments.match(/^[0-9]+$/g)) {
        location.href = "propositions_c.php?prj=" + prj + "&cmd=erasecomments&nbcom=" + nbComments;
        return true;
    }
    return false;
}

function moveThread (prj, idThread, sQuestion) {
    var idForum = prompt(sQuestion, '');
    if (idForum.match(/^[0-9]+$/g)) {
        location.href = "thread_c.php?prj=" + prj + "&cmd=movethread&t=" + idThread + "&f=" + idForum;
        return true;
    }
    return false;
}

function cutThread (prj, idThread, sQuestion) {
    var idMsg = prompt(sQuestion, '');
    if (idMsg.match(/^[0-9]+$/g)) {
        location.href = "thread_c.php?prj=" + prj + "&cmd=cutthread&t=" + idThread + "&m=" + idMsg;
        return true;
    }
    return false;
}

function joinToThread (prj, idThread, sQuestion) {
    var destThread = prompt(sQuestion, '');
    if (destThread.match(/^[0-9]+$/g)) {
        location.href = "thread_c.php?prj=" + prj + "&cmd=jointhread&t=" + idThread + "&t2=" + destThread;
        return true;
    }
    return false;
}

function changeUserMsg(prj, nMsg, sQuestion) {
    var nIdUser = prompt(sQuestion, '');
    if (nIdUser.match(/^[0-9]+$/g)) {
        AJAXcmd("thread_c.php?prj="+prj+"&cmd=changeuser&id_msg="+nMsg+"&id_user="+nIdUser, "");
    }
    return false;
}

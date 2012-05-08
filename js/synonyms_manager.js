// JavaScript Document

// vars nbclass, nbmax and confirmeraseclass are initialized in the page

// public
function addClass (ptr) {
    if (nbclass < nbmax) {
        more();
        if (ptr < nbclass) {
            shift_forwards(ptr+1);
        }
        emptyclass(ptr+1);
    }
}

function delClass (ptr) {
    var isOK = true;
    if (document.getElementById("gramm" + ptr).value != '' || document.getElementById("meaning" + ptr).value != '' || document.getElementById("synonyms" + ptr).value != '') {
        isOK = confirm(confirmeraseclass);
    }
    if (isOK) {
        if (nbclass != 1) {
            if (ptr < nbclass) {
                shift_backwards(ptr);
            }
            less();
        }
        else {
            emptyclass(1);
        }
    }
}

// private
function emptyclass (ptr) {
    document.getElementById("gramm" + ptr).value = '';
    document.getElementById("meaning" + ptr).value = '';
    document.getElementById("synonyms" + ptr).value = '';
} 

function more () {
    // add a line at the end
    nbclass++;
    document.getElementById("line" + nbclass).style.display = 'table-row';
}

function less () {
    // remove the last line
    emptyclass(nbclass);
    document.getElementById("line" + nbclass).style.display = 'none';
    nbclass--;
}

function copy (from, to) {
    // copy line 'from' to line 'to'
    document.getElementById("gramm" + to).value = document.getElementById("gramm" + from).value;
    document.getElementById("meaning" + to).value = document.getElementById("meaning" + from).value;
    document.getElementById("synonyms" + to).value = document.getElementById("synonyms" + from).value;
    document.getElementById("synonyms" + to).rows = (document.getElementById("synonyms" + to).value.length / 50) + 3;
}

function shift_forwards (from) {
    // note: last line's elements will be erased
    for (var i = nbclass;  i > from;  i--) {
        copy (i-1, i);
    }
}

function shift_backwards (from) {
    // note: elements in line 'from' will be erased
    for (var i = from;  i < nbclass;  i++) {
        copy (i+1, i);
    }
}

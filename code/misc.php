<?php

function createprjdirs ($prj) {
    $dirnames = array('./html/'.$prj, './log/'.$prj, './download/'.$prj, './export/'.$prj);
    foreach ($dirnames as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755);
        }
    }
}

function deleteprjdirs ($prj) {
    $dirnames = array('./html/'.$prj, './log/'.$prj, './download/'.$prj, './export/'.$prj);
    foreach ($dirnames as $dir) {
        if (is_dir($dir)) {
            dirdelete($dir);
        }
    }
}

function dircopy ($source, $dest) {
    // recursive function !!
    $endpos = strlen($source) - 1;
    if ($source{$endpos} != '/') {
        $source .= '/';
    }
    $endpos = strlen($dest) - 1;
    if ($dest{$endpos} != '/') {
        $dest .= '/';
    }
    if (is_dir($source)) {
        if ($h_dir = opendir($source)) {
            if (!is_dir($dest)) {
                mkdir($dest, 0755);
            }
            while (($file = readdir($h_dir)) !== FALSE) {
                if (is_dir($source.$file) and $file != '..' and $file != '.') {
                    dircopy($source.$file, $dest.$file);
                }     
                elseif ($file != '..'  and $file != '.') {
                    copy($source.$file, $dest.$file);
                }
            }
            closedir($h_dir);
        }
    }
}

function dirdelete ($dir) {
    // recursive function !!
    $endpos = strlen($dir) - 1;
    if ($dir{$endpos} != '/') {
        $dir .= '/';
    }
    if (is_dir($dir)) {
        if ($h_dir = opendir($dir)) {
            while (($file = readdir($h_dir)) !== FALSE) {
                if (is_dir($dir.$file) and $file != '..' and $file != '.') {
                    dirdelete($dir.$file);
                }     
                elseif ($file != '..'  and $file != '.') {
                    unlink($dir.$file);
                }
            }
            closedir($h_dir);
        }
        rmdir($dir);
    }
}

function addPrjIni ($prj, $label) {
    file_put_contents('./config/projects.ini', $prj.'="'.$label."\"\n", FILE_APPEND);
}

function delPrjIni ($key) {
    $entries = parse_ini_file('./config/projects.ini');
    $file = fopen('./config/projects.ini', 'w');
    foreach ($entries as $prj => $label) {
        if ($key != $prj) {
            fwrite($file, $prj.'="'.$label."\"\n");
        }
    }
    fclose($file);
}

function genPhpVar ($varName, $value) {
    return '$' . $varName . ' = ' . _genPhpValue($value) . ';' . PHP_EOL;
}

function genPhpArray ($arrayName, $array) {
    $phpCode = '$' . $arrayName . ' = array();' . PHP_EOL;
    foreach ($array as $key => $value) {
        if (!is_array($value)) {
            $phpCode .= '$' . $arrayName . "['" . $key . "'] = " . _genPhpValue($value) . ';' . PHP_EOL;
        }
        else {
            foreach ($value as $subkey => $subvalue) {
                if (!is_array($value)) {
                    $phpCode .= '$' . $arrayName . "['" . $key . "']['" . $subkey . "'] = " . _genPhpValue($subvalue) . ';' . PHP_EOL;
                }
            }
        }
    }
    return $phpCode . PHP_EOL;
}

function genPhpNumArray ($arrayName, $array) {
    $phpCode = '$' . $arrayName . ' = array(';
    foreach ($array as $value) {
        $phpCode .= _genPhpValue($value) . ',';
    }
    $phpCode = rtrim($phpCode, ',');
    $phpCode .= ');' . PHP_EOL . PHP_EOL;
    return $phpCode;
}

function _genPhpValue ($value) {
    if (is_null($value)) return 'NULL';
    if (is_bool($value)) return ($value) ? 'TRUE' : 'FALSE';
    if (is_string($value))  return "'" . $value . "'";
    return $value;
}

function updateIniFile ($prj, &$aValues) {
    $hSrcFile = @fopen('./html/_default/ui.ini', 'r');
    if (!$hSrcFile) {
        return array(FALSE, '_nofile');
    }
    $content = '';
    $section = FALSE;
    while (!feof($hSrcFile)) {
        $rawline = fgets($hSrcFile);
        $line = trim($rawline);
        if ($rawline{0} == ';' or $line == '') {
            // comment or empty line or section
            $content .= $line . PHP_EOL;
        }
        elseif (preg_match('`^\\[([a-zA-Z0-9_]+)\\]`', $rawline, $matches)) {
            if (isset($aValues[$matches[1]]) and is_array($aValues[$matches[1]])) {
                // we create a section
                $content .= $rawline;
                $section = $matches[1]; 
            }
            else {
                $section = FALSE;
            }
        }
        elseif (preg_match('`^([a-zA-Z0-9_]+) *= *"(.*)"`', $rawline, $matches)) {
            $val = (isset($aValues[$section][$matches[1]])) ? $aValues[$section][$matches[1]] : $matches[2];
            $content .= $matches[1] . ' = ' . _setIniValue($val)  . PHP_EOL;
        }
    }
    fclose($hSrcFile);
    file_put_contents('./html/' . $prj . '/ui.ini', $content, LOCK_EX);
    return array(TRUE, 'ok');
}

function createIniFileFromArray ($fileName, $array) {
    $content = '';
    foreach ($array as $key => $elem) {
        if (!is_array($elem)) {
            // single value
            $content .= $key . ' = ' . _setIniValue($elem) . PHP_EOL;
        }
    }
    foreach ($array as $key => $elem) {
        if (is_array($elem)) {
            // array
            $isNumericKeysOnly = TRUE;
            $keys = array_keys($elem);
            foreach($keys as $akey) {
                if (!is_int($akey)) $isNumericKeysOnly = FALSE;
            }

            if ($isNumericKeysOnly) {
                // simple array
                foreach ($elem as $subelem) {
                    $content .= $key . '[] = ' . _setIniValue($subelem) . PHP_EOL;
                }
            }
            else {
                // we create a section
                $content .= PHP_EOL . '[' . $key . ']' . PHP_EOL;
                foreach ($elem as $subkey => $subelem) {
                    $content .= $subkey . ' = ' . _setIniValue($subelem)  . PHP_EOL;
                }
            }
            $content . PHP_EOL;
        }
    }
    file_put_contents($fileName, $content, LOCK_EX);
}

function _setIniValue ($elem) {
    return (is_string($elem)) ? '"'.$elem.'"' : $elem;
}

function updateIniArray (&$defaultArray, &$prjArray) {
    // create in $prjArray the Key/Value which does not exist in $defaultArray
    foreach ($defaultArray as $key => $value) {
        if (is_array($value)) {
            if (isset($prjArray[$key])) {
                foreach($value as $subKey => $subValue) {
                    if (isset($prjArray[$key][$subKey])) {
                        $defaultArray[$key][$subKey] = $prjArray[$key][$subKey];
                    }
                }
            }
        }
        else {
            // value is not an array
            if (isset($prjArray[$key])) {
                $defaultArray[$key] = $prjArray[$key];
            }
        }
    }
}

function generatePassword ($lenpw) {
    // return a string of length $lenpw
    $chars = '0123456789abcdefghijkmnopqrstuwxyz+-*/@()[]{}<>.:;,?!#$%ABCDEFGHIJKMNOPQRSTUWXYZ';
    $nbchars = strlen($chars);
    $pw = '';
    $i = 0;
    while ($i <= $lenpw) {
        $char = substr($chars, mt_rand(0, $nbchars-1), 1);
        if (!strstr($pw, $char)) { 
            $pw .= $char;
            $i++;
        }
    }
    return $pw;
}

?>

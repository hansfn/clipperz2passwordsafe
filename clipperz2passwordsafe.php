<?php

/* 

Converts Clipperz JSON to Password Safe XML - version 0.1
=========================================================

Written by Hans F. Nordhaug - hansfn@gmail.com

The script is placed in the public domain.

See README for more information.

Version history:

0.1 - released 20th of May 2011

*/

/** START CONFIGURATION **/

$verbose = true;

/** END CONFIGURATION **/

if (version_compare(PHP_VERSION, '5.1.3', '<')) {
    die("This script requires PHP version 5.1.3 or newer.\n");
}

if (!defined('STDIN')) {
    die("This script should only be run on the command line.\n");
}

// Remove the script name from the argument vector and update the argument count
array_shift($argv);
$argc = count($argv);

if ($argc < 2 || $argc > 3) {
    $msg = "Usage: php clipperz2passwordsafe.php [option] json-file xml-file\n";
    $msg .= "The only option is '-utf8encode'.\n";  
    die($msg);
} else {
    $utf8encode = false;
    if ($argc == 3) {
        if ($argv[0] == "-utf8encode") {
            array_shift($argv);
            $utf8encode = true;
        } else {
            die("Unknown option - the script accepts only '-utf8encode'.\n");
        }
    }
    $clipperz_json_file = $argv[0];
    $password_safe_xml_file = $argv[1];
}

if (!file_exists($clipperz_json_file)) {
    die("JSON file doesn't exist.\n");
}

error_reporting(E_ALL);
ini_set('display_errors',1);

$json_text = file_get_contents($clipperz_json_file);
if ($utf8encode) {
    $json_text = utf8_encode($json_text);
}
$clipperz_data = json_decode($json_text, true);
switch(json_last_error()) {
    case JSON_ERROR_DEPTH:
        die("Maximum stack depth exceeded when parsing JSON file.\n");
        break;
    case JSON_ERROR_CTRL_CHAR:
        die("Unexpected control character found in JSON file.\n");
        break;
    case JSON_ERROR_SYNTAX:
        die("Syntax error, malformed JSON.\n");
        break;
    case JSON_ERROR_UTF8:
        $msg = "Invalid UTF-8 encoding in JSON file.\n";
        if (!$utf8encode) {
            $msg .= "Try running the script with the '-utf8encode' option.\n";
        }
        die($msg);
        break;
}
if (count($clipperz_data)==0) {
    die("No Clipperz cards found in JSON file.\n");
}

$xml = new DOMDocument('1.0','utf-8');
// $dom->preserveWhiteSpace = false;
$xml->formatOutput = true;
$xml_root = $xml->createElement('passwordsafe');
$xml_root->setAttribute("delimiter", "|");
$xml->appendChild($xml_root);
$entry_count = 0;

foreach ($clipperz_data as $card) {
    $entry_data = array();
    $entry_data['title'] = $card['label'];
    $entry_data['notes'] = $card['data']['notes'];
    $n = 1;
    $password_found = $card_added = false;
    $txts = $urls = array();
   
    foreach ($card['currentVersion']['fields'] as $field) {
        switch ($field['type']) {
            case 'TXT':
                /* Handle passwords stored in text fields */
                if (strtolower($field['label']) == 'password') {
                    $entry_data['password'] = $field['value'];
                    $password_found = true;
                } else {
                    $entry_data['username'] = $field['value'];
                    $txts[$field['label']] = $field['value'];
                }
                break;
            case 'URL':
                $entry_data['url'] = $field['value'];
                $urls[$field['label']] = $field['value'];
                break;
            case 'PWD':
                $entry_data['password'] = $field['value'];
                $password_found = true;
                break;
       }
       if ($password_found) {
           // If no username is found yet, we continue searching - maybe 
           // the username comes after the password.
           if (count($txts) == 0) {
               continue;
           }
           $card_added = true;
           addPasswordSafeEntry($xml_root, $entry_data);
           // Updating/reseting some values in case the card contains more than one username/password
           $entry_data = array();
           $entry_data['title'] = $card['label'] . ' - ' . ++$n;
           array_pop($txts); array_pop($urls);
           $password_found = false;
       }
    }
    if ($password_found) {
        /* Adding any passwords found without matching usernames. */
        if ($verbose && (count($txts) == 0)) {
            echo "Card \"${card['label']}\" has a password with no matching username." . PHP_EOL;
        }
        $card_added = true;
        addPasswordSafeEntry($xml_root, $entry_data);
    }
    if (!$card_added) {
        echo "Card \"${card['label']}\" wasn't added - most likely because no password was found." . PHP_EOL;
    } else if ($verbose && (count($txts) > 0)) {
        echo "Card \"${card['label']}\" has some text fields that were ignored." . PHP_EOL;
    }
}
// $xml->asXML($password_safe_xml_file);
if (file_put_contents($password_safe_xml_file, $xml->saveXML()) === false) {
    die("Failed to write to XML file.\n");
} else {
    echo "Wrote $entry_count entries to XML file.\n";
}


function addPasswordSafeEntry($xml_root, $data) {
    global $entry_count;
    $entry_count++;
    $entry = $xml_root->ownerDocument->createElement('entry');
    $xml_root->appendChild($entry);
    $title_cdata = $xml_root->ownerDocument->createCDATASection($data['title']);
    $title = $xml_root->ownerDocument->createElement('title');
    $title->appendChild($title_cdata);
    $entry->appendChild($title);
    $password_cdata = $xml_root->ownerDocument->createCDATASection($data['password']);
    $password = $xml_root->ownerDocument->createElement('password');
    $password->appendChild($password_cdata);
    $entry->appendChild($password);
    if (!empty($data['username'])) {
        $username_cdata = $xml_root->ownerDocument->createCDATASection($data['username']);
        $username = $xml_root->ownerDocument->createElement('username');
        $username->appendChild($username_cdata);
        $entry->appendChild($username);
    }
    if (!empty($data['url'])) {
        $url_cdata = $xml_root->ownerDocument->createCDATASection($data['url']);
        $url = $xml_root->ownerDocument->createElement('url');
        $url->appendChild($url_cdata);
        $entry->appendChild($url);
    }
    if (!empty($data['notes'])) {
        $notes_cdata = $xml_root->ownerDocument->createCDATASection($data['notes']);
        $notes = $xml_root->ownerDocument->createElement('notes');
        $notes->appendChild($notes_cdata);
        $entry->appendChild($notes);
    }
}


<?php
/**
 * Simple PO to MO compiler
 * Compiles .po translation files to .mo format
 */

function po_to_mo($po_file, $mo_file) {
    if (!file_exists($po_file)) {
        echo "Error: PO file not found: $po_file\n";
        return false;
    }

    $po_content = file_get_contents($po_file);
    $lines = explode("\n", $po_content);

    $entries = [];
    $current = ['msgid' => '', 'msgstr' => ''];
    $in_msgid = false;
    $in_msgstr = false;

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and empty lines
        if (empty($line) || $line[0] === '#') {
            continue;
        }

        // Start of msgid
        if (strpos($line, 'msgid ') === 0) {
            if (!empty($current['msgid']) && !empty($current['msgstr'])) {
                $entries[] = $current;
            }
            $current = ['msgid' => '', 'msgstr' => ''];
            $current['msgid'] = parse_string($line);
            $in_msgid = true;
            $in_msgstr = false;
            continue;
        }

        // Start of msgstr
        if (strpos($line, 'msgstr ') === 0) {
            $current['msgstr'] = parse_string($line);
            $in_msgid = false;
            $in_msgstr = true;
            continue;
        }

        // Continuation of string
        if ($line[0] === '"') {
            $str = parse_string($line);
            if ($in_msgid) {
                $current['msgid'] .= $str;
            } elseif ($in_msgstr) {
                $current['msgstr'] .= $str;
            }
        }
    }

    // Add last entry
    if (!empty($current['msgid']) && !empty($current['msgstr'])) {
        $entries[] = $current;
    }

    // Build MO file
    $mo = build_mo($entries);

    if ($mo === false) {
        echo "Error: Failed to build MO data\n";
        return false;
    }

    // Write MO file
    $result = file_put_contents($mo_file, $mo);

    if ($result === false) {
        echo "Error: Failed to write MO file: $mo_file\n";
        return false;
    }

    echo "Success: Compiled $po_file to $mo_file\n";
    return true;
}

function parse_string($line) {
    // Extract string from "msgid \"string\"" or "\"string\""
    preg_match('/"(.*)"/s', $line, $matches);
    if (isset($matches[1])) {
        return stripcslashes($matches[1]);
    }
    return '';
}

function build_mo($entries) {
    $originals = [];
    $translations = [];

    foreach ($entries as $entry) {
        $originals[] = $entry['msgid'];
        $translations[] = $entry['msgstr'];
    }

    $count = count($originals);

    // MO file header
    $magic = 0x950412de;
    $revision = 0;
    $hash_size = 0;
    $hash_offset = 0;

    // Calculate offsets
    $ids_offset = 28;
    $strs_offset = $ids_offset + ($count * 8);
    $keydata_offset = $strs_offset + ($count * 8);

    // Build key data
    $keydata = '';
    $ids_table = '';
    $ids_index = [];

    foreach ($originals as $original) {
        $ids_index[] = [strlen($original), $keydata_offset + strlen($keydata)];
        $keydata .= $original . "\0";
    }

    // Build string data
    $strdata = '';
    $strs_index = [];

    foreach ($translations as $translation) {
        $strs_index[] = [strlen($translation), $keydata_offset + strlen($keydata) + strlen($strdata)];
        $strdata .= $translation . "\0";
    }

    // Build MO file
    $mo = '';

    // Magic number
    $mo .= pack('L', $magic);
    // Revision
    $mo .= pack('L', $revision);
    // Number of strings
    $mo .= pack('L', $count);
    // Offset of table with original strings
    $mo .= pack('L', $ids_offset);
    // Offset of table with translation strings
    $mo .= pack('L', $strs_offset);
    // Size of hashing table
    $mo .= pack('L', $hash_size);
    // Offset of hashing table
    $mo .= pack('L', $hash_offset);

    // Original strings table
    foreach ($ids_index as $index) {
        $mo .= pack('L', $index[0]); // length
        $mo .= pack('L', $index[1]); // offset
    }

    // Translation strings table
    foreach ($strs_index as $index) {
        $mo .= pack('L', $index[0]); // length
        $mo .= pack('L', $index[1]); // offset
    }

    // Original strings
    $mo .= $keydata;
    // Translation strings
    $mo .= $strdata;

    return $mo;
}

// Run compilation
$languages_dir = __DIR__ . '/languages/';
$po_file = $languages_dir . 'formflow-pro-pt_BR.po';
$mo_file = $languages_dir . 'formflow-pro-pt_BR.mo';

echo "Compiling translations...\n";
po_to_mo($po_file, $mo_file);
echo "Done!\n";

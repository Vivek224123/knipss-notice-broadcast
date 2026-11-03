<?php
/**
 * Pure-PHP XLSX / CSV parser — no external library needed.
 * Requires: ZipArchive + SimpleXML (both bundled with XAMPP/standard PHP).
 *
 * Returns: array of ['sno'=>null, 'stu_name'=>string, 'phone'=>string]
 */

function parse_uploaded_contacts(string $file_path, string $original_name): array {
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if ($ext === 'xlsx') {
        $rows = xlsx_to_rows($file_path);
    } elseif (in_array($ext, ['csv', 'txt'], true)) {
        $rows = csv_to_rows($file_path);
    } else {
        throw new RuntimeException("Unsupported format. Please upload .xlsx or .csv");
    }
    if (empty($rows)) {
        throw new RuntimeException("File is empty or could not be read.");
    }
    return rows_to_contacts($rows);
}

/* ── XLSX via ZipArchive + SimpleXML ───────────────────────────────────── */
function xlsx_to_rows(string $path): array {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException("Cannot open XLSX file.");

    // Shared strings
    $shared = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $xml = simplexml_load_string($ssXml);
        foreach ($xml->si ?? [] as $si) {
            if (isset($si->t)) { $shared[] = (string)$si->t; }
            else { $t = ''; foreach ($si->r ?? [] as $r) { $t .= (string)$r->t; } $shared[] = $t; }
        }
    }

    // First worksheet
    $sheetXml = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $n = $zip->getNameIndex($i);
        if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $n)) { $sheetXml = $zip->getFromIndex($i); break; }
    }
    $zip->close();
    if (!$sheetXml) throw new RuntimeException("Could not read worksheet from XLSX.");

    $xml  = simplexml_load_string($sheetXml);
    $rows = [];
    foreach ($xml->sheetData->row ?? [] as $row) {
        $cells = []; $maxCol = 0;
        foreach ($row->c as $c) {
            preg_match('/([A-Z]+)\d+/', (string)$c['r'], $m);
            $col = 0; foreach (str_split($m[1] ?? 'A') as $ch) { $col = $col * 26 + (ord($ch) - 64); } $col--;
            $type = (string)$c['t'];
            $val  = '';
            if ($type === 's')           { $val = $shared[(int)(string)$c->v] ?? ''; }
            elseif ($type === 'inlineStr' && isset($c->is->t)) { $val = (string)$c->is->t; }
            elseif (isset($c->v))        { $val = (string)$c->v; }
            $cells[$col] = trim($val);
            if ($col > $maxCol) $maxCol = $col;
        }
        $full = []; for ($i = 0; $i <= $maxCol; $i++) { $full[] = $cells[$i] ?? ''; }
        if (array_filter($full)) $rows[] = $full;
    }
    return $rows;
}

/* ── CSV ───────────────────────────────────────────────────────────────── */
function csv_to_rows(string $path): array {
    $first = (string)@file_get_contents($path, false, null, 0, 4096);
    $del = ',';
    if (substr_count($first, "\t") > substr_count($first, ',')) $del = "\t";
    elseif (substr_count($first, ';')  > substr_count($first, ',')) $del = ';';

    $rows = []; $fh = fopen($path, 'r');
    while (($row = fgetcsv($fh, 4096, $del)) !== false) {
        $clean = array_map(fn($v) => trim((string)$v), $row);
        if (array_filter($clean)) $rows[] = $clean;
    }
    fclose($fh);
    return $rows;
}

/* ── Detect header row, phone & name columns, return contact array ─────── */
function rows_to_contacts(array $rows): array {
    $header   = $rows[0];
    $phoneCol = -1;
    $nameCol  = -1;

    foreach ($header as $idx => $h) {
        $slug = strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$h));
        if ($phoneCol < 0 && preg_match('/phone|mobile|whatsapp|contact|number|mob|cell|phno/', $slug)) $phoneCol = $idx;
        if ($nameCol  < 0 && preg_match('/name|student|candidate|person|naam/',                 $slug)) $nameCol  = $idx;
    }

    $startRow = ($phoneCol >= 0 || $nameCol >= 0) ? 1 : 0;

    // Auto-detect phone column from data if not found
    if ($phoneCol < 0) {
        for ($r = $startRow; $r < min(count($rows), 8); $r++) {
            foreach ($rows[$r] as $i => $v) {
                $d = preg_replace('/\D/', '', (string)$v);
                if (strlen($d) >= 10 && strlen($d) <= 13) { $phoneCol = $i; break 2; }
            }
        }
    }
    if ($phoneCol < 0) $phoneCol = 0; // fallback to first column

    // Name column defaults to the next column that isn't the phone column
    if ($nameCol < 0 || $nameCol === $phoneCol) {
        $nameCol = -1;
        for ($i = 0; $i < count($rows[$startRow] ?? []); $i++) {
            if ($i !== $phoneCol) { $nameCol = $i; break; }
        }
    }

    $out = [];
    for ($i = $startRow; $i < count($rows); $i++) {
        $row   = $rows[$i];
        $phone = trim((string)($row[$phoneCol] ?? ''));
        $name  = $nameCol >= 0 ? trim((string)($row[$nameCol] ?? '')) : '';

        // Swap if phone looks like a name and vice-versa
        $pd = preg_replace('/\D/', '', $phone);
        $nd = preg_replace('/\D/', '', $name);
        if (strlen($nd) >= 10 && strlen($pd) < 8) { [$phone, $name] = [$name, $phone]; }

        $phone = trim((string)$phone);
        if ($phone === '') continue;

        $out[] = [
            'sno'      => null,
            'stu_name' => $name !== '' ? $name : 'Student',
            'phone'    => $phone,
        ];
    }
    return $out;
}

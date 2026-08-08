<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class XlsxFirstSheetReader
{
    private const MAX_XML_BYTES = 10_000_000;

    /** @return array<int, array<string, string>> */
    public function read(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('XLSX faylini o‘qish uchun PHP zip kengaytmasi kerak.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('XLSX faylini ochib bo‘lmadi.');
        }

        try {
            $sharedStrings = $this->sharedStrings($zip);
            $sheetXml = $this->entry($zip, 'xl/worksheets/sheet1.xml');
        } finally {
            $zip->close();
        }

        $document = $this->xml($sheetXml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];

        foreach ($xpath->query('//x:sheetData/x:row') ?: [] as $row) {
            $values = [];

            foreach ($xpath->query('x:c', $row) ?: [] as $cell) {
                $reference = $cell->attributes?->getNamedItem('r')?->nodeValue ?? '';
                if (preg_match('/^([A-Z]+)/', $reference, $matches) !== 1) {
                    continue;
                }

                $type = $cell->attributes?->getNamedItem('t')?->nodeValue;
                $valueNode = $xpath->query('x:v', $cell)?->item(0);
                $value = $valueNode?->textContent ?? '';

                if ($type === 's' && ctype_digit($value)) {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = $xpath->query('x:is//x:t', $cell)?->item(0)?->textContent ?? '';
                }

                $values[$matches[1]] = trim($value);
            }

            if ($values !== []) {
                $rows[(int) ($row->attributes?->getNamedItem('r')?->nodeValue ?? count($rows) + 1)] = $values;
            }
        }

        return $rows;
    }

    /** @return array<int, string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $document = $this->xml($this->entry($zip, 'xl/sharedStrings.xml'));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];

        foreach ($xpath->query('//x:si') ?: [] as $item) {
            $parts = [];
            foreach ($xpath->query('.//x:t', $item) ?: [] as $text) {
                $parts[] = $text->textContent;
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function entry(ZipArchive $zip, string $name): string
    {
        $index = $zip->locateName($name);
        if ($index === false) {
            throw new RuntimeException("XLSX tarkibida {$name} topilmadi.");
        }

        $statistics = $zip->statIndex($index);
        if (! is_array($statistics) || (int) ($statistics['size'] ?? 0) > self::MAX_XML_BYTES) {
            throw new RuntimeException('XLSX ichki XML fayli ruxsat etilgan hajmdan katta.');
        }

        $contents = $zip->getFromIndex($index);
        if (! is_string($contents)) {
            throw new RuntimeException("XLSX tarkibidagi {$name} o‘qilmadi.");
        }

        return $contents;
    }

    private function xml(string $contents): DOMDocument
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            if (! $document->loadXML($contents, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new RuntimeException('XLSX ichki XML formati yaroqsiz.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }
}

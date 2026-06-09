<?php

declare(strict_types=1);

namespace CihanSenturk\OfxParser;

use Exception;
use CihanSenturk\OfxParser\Exceptions\ParseException;

/**
 * An OFX parser library
 *
 * Modern, secure, and type-safe OFX/QFX parser for PHP 8.1+
 *
 * @author Cihan Şentürk <cihansenturk96@gmail.com>
 */
class Parser
{
    /**
     * Load an OFX file into this parser by way of a filename
     *
     * @param string $ofxFile A path that can be loaded with file_get_contents
     * @return Ofx
     * @throws \InvalidArgumentException
     * @throws ParseException
     */
    public function loadFromFile(string $ofxFile): Ofx
    {
        if (! file_exists($ofxFile)) {
            throw new \InvalidArgumentException("File '{$ofxFile}' could not be found");
        }

        return $this->loadFromString(file_get_contents($ofxFile));
    }

    /**
     * Load an OFX by directly using the text content
     *
     * @param string $ofxContent
     * @return Ofx
     * @throws ParseException
     */
    public function loadFromString(string $ofxContent): Ofx
    {
        $ofxContent = utf8_encode($ofxContent);

        $ofxContent = $this->conditionallyAddNewlines($ofxContent);

        $sgmlStart = stripos($ofxContent, '<OFX>');

        if ($sgmlStart === false) {
            throw new ParseException('Could not find <OFX> tag in the provided content.');
        }

        $ofxSgml = trim(substr($ofxContent, $sgmlStart));

        $ofxXml = $this->convertSgmlToXml($ofxSgml);

        $xml = $this->xmlLoadString($ofxXml);

        return new Ofx($xml);
    }

    /**
     * Detect if the OFX file is on one line. If it is, add newlines automatically.
     *
     * @param string $ofxContent
     * @return string
     */
    private function conditionallyAddNewlines(string $ofxContent): string
    {
        if (preg_match('/<OFX>.*<\/OFX>/', $ofxContent) === 1) {
            return str_replace('<', "\n<", $ofxContent); // add line breaks to allow XML to parse
        } 

        if (preg_match('/<OFX>.*<.+>/', $ofxContent) === 1) {
            return str_replace('<', "\n<", $ofxContent); // add line breaks to allow XML to parse
        }

        return $ofxContent;
    }

    /**
     * Load an XML string without PHP errors - throws exception instead
     *
     * @param string $xmlString
     * @return \SimpleXMLElement
     * @throws ParseException
     */
    private function xmlLoadString(string $xmlString): \SimpleXMLElement
    {
        // Security: Disable external entity loading to prevent XXE attacks
        $previousValue = libxml_disable_entity_loader(true);

        libxml_clear_errors();
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_DTDLOAD | LIBXML_DTDATTR);

        // Restore previous value
        libxml_disable_entity_loader($previousValue);

        if ($errors = libxml_get_errors()) {
            throw new ParseException('Failed to parse OFX XML: ' . var_export($errors, true));
        }

        return $xml;
    }

    /**
     * Detect any unclosed XML tags - if they exist, close them
     *
     * @param string $line
     * @return string
     */
    private function closeUnclosedXmlTags(string $line): string
    {
        // Matches: <SOMETHING>blah
        // Does not match: <SOMETHING>
        // Does not match: <SOMETHING>blah</SOMETHING>
        if (preg_match(
            "/<([A-Za-z0-9.]+)>([\wà-úÀ-Ú0-9\.\-\_\+\, ;:\[\]\'\&\/\\\*\(\)\+\{\|\}\!\£\$\?=@€£#%±§~`\"]+)$/",
            trim($line),
            $matches
        )) {
            return "<{$matches[1]}>{$matches[2]}</{$matches[1]}>";
        }

        return $line;
    }

    /**
     * Convert an SGML to an XML string
     *
     * @param string $sgml
     * @return string
     */
    private function convertSgmlToXml(string $sgml): string
    {
        $sgml = str_replace(["\r\n", "\r"], "\n", $sgml);

        $sgml = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $sgml);

        $lines = explode("\n", $sgml);

        $xml = '';

        foreach ($lines as $line) {
            $xml .= trim($this->closeUnclosedXmlTags($line)) . "\n";
        }

        return trim($xml);
    }
}

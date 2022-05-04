<?php


namespace OneLoginToolkit;

/**
 * This file is part of onelogin-toolkit.
 *
 * (c) Oliver Kucharzewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package OneLogin Toolkit
 * @author  Oliver Kucharzewski <oliver@olidev.com.au> (2022)
 * @license MIT  https://github.com/oliverkuchies/onelogin-toolkit/php-saml/blob/master/LICENSE
 * @link    https://github.com/oliverkuchies/onelogin-toolkit
 */

use DOMDocument;

class DOM
{
    private static ?DOMDocument $DOM = null;

    public static function get() {
        if (self::$DOM) {
            return new self();
        } else {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            self::set($dom);

            return new self();
        }
    }

    private static function set(DOMDocument $dom) {
        self::$DOM = $dom;
    }

    public static function getDOM() {
        return self::$DOM;
    }

    /**
     * This function load an XML string in a save way.
     * Prevent XEE/XXE Attacks
     *
     * @param DOMDocument $dom The document where load the xml.
     * @param string      $xml The XML string to be loaded.
     *
     * @return DOM|false $dom The result of load the XML at the DOMDocument
     *
     * @throws Exception
     */
    public function load($xml)
    {
        $dom = self::get()->getDOM();

        assert($dom instanceof DOMDocument);
        assert(is_string($xml));

        $oldEntityLoader = null;
        if (PHP_VERSION_ID < 80000) {
            $oldEntityLoader = libxml_disable_entity_loader(true);
        }

        $res = $dom->loadXML($xml);

        if (PHP_VERSION_ID < 80000) {
            libxml_disable_entity_loader($oldEntityLoader);
        }

        foreach ($dom->childNodes as $child) {
            if ($child->nodeType === XML_DOCUMENT_TYPE_NODE) {
                throw new Exception(
                    'Detected use of DOCTYPE/ENTITY in XML, disabled to prevent XXE/XEE attacks'
                );
            }
        }

        if (!$res) {
            return false;
        } else {
            return self::get();
        }
    }
}

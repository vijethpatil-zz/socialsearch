<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
 *
 *  LICENSE:
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage processor
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Used for the getDocumentFilename method in UrlParser */
require_once BASE_DIR."/lib/url_parser.php";
/** Load base class, if needed */
require_once BASE_DIR."/lib/processors/image_processor.php";

/**
 * Used to create crawl summary information 
 * for BMP and ICO files
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage processor
 */
class BmpProcessor extends ImageProcessor
{

    /**
     * Size in bytes of one block to read in of BMP
     */
    const BLOCK_SIZE = 4096;

    /**
     * Size in bytes of BMP identifier and size info
     */
    const BMP_ID = 10;

    /**
     * Size in bytes of BMP header
     */
    const BMP_HEADER_LEN = 108;
    /**
     * Maximum pixel width or height
     */
    const MAX_DIM = 1000;

    /**
     * {@inheritdoc}
     */
    function process($page, $url)
    {
        if(is_string($page)) {
            file_put_contents(CRAWL_DIR."/cache/tmp.bmp", $page); 
            $image = $this->imagecreatefrombmp(CRAWL_DIR."/cache/tmp.bmp");
            $thumb_string = self::createThumb($image);
            $summary[self::TITLE] = "";
            $summary[self::DESCRIPTION] = "Image of ".
                UrlParser::getDocumentFilename($url);
            $summary[self::LINKS] = array();
            $summary[self::PAGE] = 
                "<html><body><div><img src='data:image/bmp;base64," .
                base64_encode($page)."' alt='".$summary[self::DESCRIPTION].
                "' /></div></body></html>";
            $summary[self::THUMB] = 'data:image/jpeg;base64,'.
                base64_encode($thumb_string);
        }
        return $summary;
    }

    /**
     * Reads in a 32 / 24bit non-palette bmp files from provided filename 
     * and returns a php  image object corresponding to it. This is a crude 
     * variation of code from imagecreatewbmp function documentation at php.net
     *
     * @param string $filename = name of 
     */
    function imagecreatefrombmp($filename)
    {
        // Read image into a string
        $file = fopen($filename, "rb");
        $read = fread($file, self::BMP_ID); //skip identifier and size

        while(!feof($file) && ($read != "")) {
            $read .= fread($file, self::BLOCK_SIZE);
        }

        $temp = unpack("H*", $read);
        $hex = $temp[1];
        $header = substr($hex, 0, self::BMP_HEADER_LEN);


        $can_understand_flag = substr($header, 0, 4) == "424d";
        // get parameters of image from header bytes
        if ($can_understand_flag) {
            $header_parts = str_split($header, 2);
            $width  = hexdec($header_parts[19] . $header_parts[18]);
            $height = hexdec($header_parts[23] . $header_parts[22]);
            $bits_per_pixel = hexdec($header_parts[29] . $header_parts[28]);
            $can_understand_flag = (($bits_per_pixel == 24) ||
                ($bits_per_pixel == 32)) && ($width < 
                self::MAX_DIM && $height < self::MAX_DIM );
            unset($header_parts);
        }

        $x = 0;
        $y = 1;
       
        /* We're going to manually write pixel info in to the following
            image object
        */
        $image  = imagecreatetruecolor($width, $height);
        if(!$can_understand_flag) {
            return $image;
        }
        //    Grab the body from the image
        $body = substr($hex, self::BMP_HEADER_LEN);

        /*
            Calculate any end-of-line padding needed
        */
        $body_size = strlen($body)/2;
        $header_size = ($width * $height);

        // Set-up padding flag
        $padding_flag = ($body_size > ($header_size * 3) + 4);

        $pixel_step = ceil($bits_per_pixel >> 3);
        // Write pixels
        for($i = 0; $i < $body_size; $i += $pixel_step)
        {
            //    Calculate line-ending and padding
            if ($x >= $width)
            {
                if ($padding_flag) {
                    $i += $width % 4;
                }
                $x = 0;
                $y++;
                if ($y > $height) break;
            }
            $i_pos  = $i << 1;
            $r =hexdec($body[$i_pos + 4].$body[$i_pos + 5]);
            $g = hexdec($body[$i_pos + 2].$body[$i_pos + 3]);
            $b  = hexdec($body[$i_pos].$body[$i_pos + 1]);

            $color = imagecolorallocate($image, $r, $g, $b);
            imagesetpixel($image, $x, $height - $y, $color);
            $x++;
        }

        unset($body);
        return $image;
    }

}

?>

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
 * @author Ravi Dhillon ravi.dhillon@yahoo.com, Chris Pollett
 * @package seek_quarry
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * Load the Bloom Filter File
 */
require_once BASE_DIR."/lib/bloom_filter_file.php";

/**
 * Load the Phrase Parser File
 */
require_once BASE_DIR."/lib/phrase_parser.php";

/**
 * Library of functions used to create and extract n word grams
 *
 * @author Ravi Dhillon (Bigram Version), Chris Pollett (ngrams + rewrite +
 *  support for page count dumps)
 *
 * @package seek_quarry
 * @subpackage library
 */
class NWordGrams
{
     /**
      * Static copy of n-grams files
      * @var object
      */
    static $ngrams = NULL;
     /**
      * 
      */
     const BLOCK_SIZE = 8192;
     /**
      * Suffix appended to language tag to create the
      * filter file name containing bigrams.
      */
     const FILTER_SUFFIX = "_word_grams.ftr";
     /**
      * Suffix appended to language tag to create the
      * text file name containing bigrams.
      */
     const TEXT_SUFFIX = "_word_grams.txt";

     const WIKI_DUMP_REDIRECT = 0;
     const WIKI_DUMP_TITLE = 1;
     const PAGE_COUNT_WIKIPEDIA = 2;
     const PAGE_COUNT_WIKTIONARY = 3;

    /**
     * Says whether or not phrase exists in the N word gram Bloom Filter
     *
     * @param $phrase what to check if is a bigram
     * @param string $lang language of bigrams file
     * @return true or false
     */
    static function ngramsContains($phrase, $lang, $num_gram = 2)
    {
        
        if(self::$ngrams == NULL || !isset(self::$ngrams[$num_gram])) {
            $filter_path =
                LOCALE_DIR . "/$lang/resources/" .
                "{$num_gram}" . self::FILTER_SUFFIX;
            if (file_exists($filter_path)) {
                self::$ngrams[$num_gram] = BloomFilterFile::load($filter_path);
            } else  {
                return false;
            }
        }
        return self::$ngrams[$num_gram]->contains(strtolower($phrase));
    }

    /**
     * Creates a bloom filter file from a n word gram text file. The
     * path of n word gram text file used is based on the input $lang.
     * The name of output filter file is based on the $lang and the 
     * number n. Size is based on input number of n word grams .
     * The n word grams are read from text file, stemmed if a stemmer
     * is available for $lang and then stored in filter file.
     *
     * @param string $lang language to be used to stem bigrams.
     * @param int $num_gram number of words in grams we are storing
     * @param int $num_ngrams_found count of n word grams in text file.
     * @param int $max_gram_len value n of longest n gram to be added.
     * @return none
     */
    static function makeNWordGramsFilterFile($lang, $num_gram, 
        $num_ngrams_found, $max_gram_len)
    {
        $filter_path =
            LOCALE_DIR . "/$lang/resources/" .
            "{$num_gram}" . self::FILTER_SUFFIX;
        if (file_exists($filter_path)) {
            unlink($filter_path); //build again from scratch
        }
        $ngrams = new BloomFilterFile($filter_path, $num_ngrams_found);

        $inputFilePath = LOCALE_DIR . "/$lang/resources/" .
            "{$num_gram}" .  self::TEXT_SUFFIX;
        $fp = fopen($inputFilePath, 'r') or die("Can't open ngrams text file");
        while ( ($ngram = fgets($fp)) !== false) {
          $words = PhraseParser::stemTerms(trim($ngram), $lang);
          if(strlen($words[0]) == 1) { // get rid of n grams like "a dog"
              continue;
          }
          $ngram_stemmed = implode(" ", $words);
          $ngrams->add(mb_strtolower($ngram_stemmed));
        }
        fclose($fp);
        $ngrams->max_gram_len = $max_gram_len;
        $ngrams->save();
    }

    /**
     * Generates a n word grams text file from input wikipedia xml file.
     * The input file can be a bz2 compressed or uncompressed.
     * The input XML file is parsed line by line and pattern for
     * n word gram is searched. If a n word gram is found it is added to the
     * array. After the complete file is parsed we remove the duplicate
     * n word grams and sort them. The resulting array is written to the
     * text file. The function returns the number of bigrams stored in
     * the text file.
     *
     * @param string $wiki_file compressed or uncompressed wikipedia
     *      XML file path to be used to extract bigrams.
     * @param string $lang Language to be used to create n grams.
     * @param string $locale Locale to be used to store results.
     * @param int $num_gram number of words in grams we are looking for
     * @param int $ngram_type where in Wiki Dump to extract grams from
     * @return number $num_ngrams_found count of bigrams in text file.
     */
    static function makeNWordGramsTextFile($wiki_file, $lang, 
        $locale, $num_gram = 2, $ngram_type = self::PAGE_COUNT_WIKIPEDIA, 
        $max_terms = -1)
    {
        $wiki_file_path = PREP_DIR."/$wiki_file";
        if(strpos($wiki_file_path, "bz2") !== false) {
            $fr = bzopen($wiki_file_path, 'r') or
                die ("Can't open compressed file");
            $read = "bzread";
            $close = "bzclose";
        } else if (strpos($wiki_file_path, "gz") !== false) {
            $fr = gzopen($wiki_file_path, 'r') or
                die ("Can't open compressed file");
            $read = "gzread";
            $close = "gzclose";
        } else {
            $fr = fopen($wiki_file_path, 'r') or die("Can't open file");
            $read = "fread";
            $close = "fclose";
        }
        $ngrams_file_path
            = LOCALE_DIR . "/$locale/resources/" . "{$num_gram}" .
                self::TEXT_SUFFIX;
        $ngrams = array();
        $input_buffer = "";
        $time = time();
        echo "Reading wiki file ...\n";
        $bytes = 0;
        $bytes_since_last_output = 0;
        $output_message_threshold = self::BLOCK_SIZE*self::BLOCK_SIZE;
        $is_count_type = false;
        switch($ngram_type)
        {
            case self::WIKI_DUMP_TITLE:
                $pattern = '/<title>[^\p{P}]+';
                $pattern_end = '<\/title>/u';
                $replace_array = array('<title>','</title>');
            break;
            case self::WIKI_DUMP_REDIRECT:
                $pattern = '/#redirect\s\[\[[^\p{P}]+';
                $pattern_end='\]\]/u';
                $replace_array = array('#redirect [[',']]');
            break;
            case self::PAGE_COUNT_WIKIPEDIA:
                $pattern = '/^'.$lang.'\s[^\p{P}]+';
                $pattern_end='/u';
                $is_count_type = true;
            break;
            case self::PAGE_COUNT_WIKTIONARY:
                $pattern = '/^'.$lang.'.d\s[^\p{P}]+';
                $pattern_end='/u';
                $is_count_type = true;
            break;
        }
        $is_all = false;
        $repeat_pattern = "[\s|_][^\p{P}]+";
        if($num_gram == "all" || $is_count_type) {
            $pattern .= "($repeat_pattern)+";
            if($num_gram == "all") {
                $is_all = true;
            }
            $max_gram_len = -1;
        } else {
            for($i = 1; $i < $num_gram; $i++) {
                $pattern .= $repeat_pattern;
            }
            $max_gram_len = $num_gram;
        }
        $pattern .= $pattern_end;
        $replace_types = array(self::WIKI_DUMP_TITLE, self::WIKI_DUMP_REDIRECT);
        while (!feof($fr)) {
            $input_text = $read($fr, self::BLOCK_SIZE);
            $len = strlen($input_text);
            if($len == 0) break;
            $bytes += $len;
            $bytes_since_last_output += $len;
            if($bytes_since_last_output > $output_message_threshold) {
                echo "Have now read ".$bytes." many bytes. " .
                    "Peak memory so far ".memory_get_peak_usage().
                    " Elapsed time so far:".(time() - $time)."\n";
                $bytes_since_last_output = 0;
            }
            $input_buffer .= mb_strtolower($input_text);
            $lines = explode("\n", $input_buffer);
            $input_buffer = array_pop($lines);
            foreach($lines as $line) {
                preg_match($pattern, $line, $matches);
                if(count($matches) > 0) {
                    if($is_count_type) {
                        $line_parts = explode(" ", $matches[0]);
                        if(isset($line_parts[1]) && isset($line_parts[2])) {
                            $ngram = mb_ereg_replace("_", " ", $line_parts[1]);
                            $char_grams = 
                                PhraseParser::getCharGramsTerm(array($ngram), 
                                    $locale);
                            $ngram = implode(" ", $char_grams);
                            $ngram_num_words =  mb_substr_count($ngram, " ")+1;
                            if(($is_all && $ngram_num_words > 1) ||(!$is_all &&
                                $ngram_num_words == $num_gram)) {
                                $ngrams[$ngram] = $line_parts[2];
                            }
                        }
                    } else {
                        $ngram = mb_ereg_replace(
                            $replace_array, "", $matches[0]);
                        $ngram = mb_ereg_replace("_", " ", $ngram);

                        $ngrams[] = $ngram;
                    }
                    if($is_all && isset($ngram)) {
                        $ngram_num_words =  mb_substr_count($ngram, " ") + 1;
                        $max_gram_len = max($max_gram_len, $ngram_num_words);
                    }
                }
            }
        }
        if($is_count_type) {
            arsort($ngrams);
            $ngrams = array_keys($ngrams);
        }
        $ngrams = array_unique($ngrams);
        $num_ngrams_found = count($ngrams);
        if($max_terms > 0 && $num_ngrams_found > $max_terms) {
            $ngrams = array_slice($ngrams, 0, $max_terms);
        }
        $num_ngrams_found = count($ngrams);
        // in is_all case add prefix*'s for (n >= 3)-grams
        if($is_all) {
            for($i = 0; $i < $num_ngrams_found; $i++) {
                $ngram_in_word =  mb_substr_count($ngrams[$i], " ")+1;
                if($ngram_in_word >= 3) {
                    $ngram_parts = explode(" ", $ngrams[$i]);
                    $ngram = $ngram_parts[0];
                    for($j = 1; $j < $ngram_in_word - 1;  $j++ ) {
                        $ngram .= " ".$ngram_parts[$j];
                        $ngrams[] = $ngram."*";
                    }
                }
            }
            $ngrams = array_unique($ngrams);
            $num_ngrams_found = count($ngrams);
        }
        sort($ngrams);

        $ngrams_string = implode("\n", $ngrams);
        file_put_contents($ngrams_file_path, $ngrams_string);
        $close($fr);
        return array($num_ngrams_found, $max_gram_len);
    }

}

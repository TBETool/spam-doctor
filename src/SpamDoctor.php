<?php
/**
 * Created by PhpStorm.
 * User: anuj
 * Date: 11/10/18
 * Time: 2:32 PM
 */

namespace TBETool;


use Exception;

/**
 * Class SpamDoctor
 * @package App\Library\SpamDoctor\src
 */
class SpamDoctor
{
    private $spamDictionary;
    private $spamDictionaryItems = [];
    private $textContent = '';

    private $isHTML = false;
    private $replaceRule = '';

    private $spamFoundPositions = [];
    private $spamFoundItems = [];
    private $spamFoundContentItems = [];
    private $spamContentHighlightedText = '';
    private $spamContentHighlightedHtml = '';

    private $spamContentHighlightedHtmlReplaced = '';
    private $spamContentHighlightedTextReplaced = '';
    private $spamContentTextReplaced = '';
    private $spamContentHtmlReplaced = '';

    private $userFilterItems = [];

    /**
     * SpamDoctor constructor.
     */
    function __construct()
    {
        $this->spamDictionary = __DIR__ . '/../data/spam_data.txt';
    }

    /**
     * Set filter items by user
     *
     * @param array $items
     * @param bool $append
     * @throws Exception
     */
    public function setFilterItems($items = [], $append = false)
    {
        if (empty($items)) {
            throw new Exception('Items not provided. Please provide array of items');
        }

        if (!is_array($items)) {
            throw new Exception('Filter items must be array where each filter item is an item of the array');
        }

        if ($append) {
            $this->userFilterItems = array_merge($this->userFilterItems, $items);
        } else {
            $this->userFilterItems = $items;
        }
    }

    /**
     * Set replace rule
     *
     * @param $json_rule
     * @throws Exception
     */
    public function setReplaceRule($json_rule)
    {
        if (empty($json_rule)) {
            throw new Exception('Json rule empty.');
        }

        $this->replaceRule = $json_rule;
    }

    /**
     * Check if text is spam or not
     * @param $text
     * @param bool $is_html
     * @throws Exception
     */
    public function check($text, $is_html = false)
    {
        if (empty($text)) {
            throw new Exception('Text content is missing. Please provide some text to check');
        }

        $this->isHTML = $is_html;

        // Set text content
        $this->textContent = $text;

        // If text content is HTML, parse HTML to get string only
        if ($this->isHTML) {
            $this->_processHtml();
        }

        // Set highlighted content parameter for text
        $this->spamContentHighlightedText = $this->textContent;
        // Set highlighted content parameter for HTML
        $this->spamContentHighlightedHtml = $text;
        // Set replace text
        $this->spamContentTextReplaced = $this->textContent;
        // Set replace html
        $this->spamContentHtmlReplaced = $text;
        // Set replace text highlighted
        $this->spamContentHighlightedTextReplaced = $this->textContent;
        // Set replace html highlighted
        $this->spamContentHighlightedHtmlReplaced = $text;

        $this->_checkSpam();
    }

    /**
     * Process text content and check for spam
     *
     */
    private function _checkSpam()
    {
        if (!is_file($this->spamDictionary)) {
            $this->_createDictionary();
        }

        $this->_prepareSpamDictionaryItems();


        foreach ($this->spamDictionaryItems as $d_item) {

            // Initialize lastPost to 0
            $lastPos = 0;

            // Search for all occurrences of the item in the content
            while (($lastPos = stripos($this->textContent, $d_item, $lastPos)) !== false) {

                // Store position of occurrence
                $this->spamFoundPositions[] = $lastPos;

                // Store string found from the content
                $sub_str_item = substr($this->textContent, $lastPos, strlen($d_item));
                if (!in_array($sub_str_item, $this->spamFoundContentItems)) {
                    $this->spamFoundContentItems[] = $sub_str_item;
                }

                // Store the item found and its occurrence count
                $index = array_search($d_item, array_column($this->spamFoundItems, 'item'));
                if ($index !== false) {
                    $this->spamFoundItems[$index]['count'] += 1;
                } else {
                    $item = [
                        'item' => $d_item,
                        'count' => 1
                    ];
                    $this->spamFoundItems[] = $item;
                }

                // Highlight the text
                $this->spamContentHighlightedText = preg_replace(
                    '/\p{L}*?'.preg_quote($d_item).'\p{L}*/ui',
                    '<span style="color:red;">$0</span>',
                    $this->spamContentHighlightedText
                );

                // Highlight the html text
                $this->spamContentHighlightedHtml = preg_replace(
                    '/\p{L}*?'.preg_quote($d_item).'\p{L}*/ui',
                    '<span style="color:red;">$0</span>',
                    $this->spamContentHighlightedHtml
                );

                // Set highlighted text
                $this->spamContentHighlightedTextReplaced = $this->spamContentHighlightedText;

                // Set highlighted html
                $this->spamContentHighlightedHtmlReplaced = $this->spamContentHighlightedHtml;


                // Update last position value
                $lastPos = $lastPos + strlen($d_item);
            }

            // Teach spam doctor
            $this->_teachDoctor($d_item);
        }

        // Replace content according to rule
        $this->_processReplaceRule();

        // Sort positions in ascending order
        sort($this->spamFoundPositions);
    }


    /**
     * Get Spam Positions
     * @return array
     */
    public function getSpamPositions()
    {
        return $this->spamFoundPositions;
    }

    /**
     * Get Spam Items
     * @return array
     */
    public function getSpamItems()
    {
        return $this->spamFoundItems;
    }

    /**
     * Get Content with highlighted spam items
     * @param bool $html
     * @return string
     */
    public function getHighlighted($html = false)
    {
        if ($html) {
            return $this->spamContentHighlightedHtml;
        }

        return $this->spamContentHighlightedText;
    }

    /**
     * Get all items from the dictionary used to filter the content
     */
    public function getSpamDictionary()
    {
        return $this->_getDictionaryItems();
    }

    /**
     * Teach doctor.
     * Used to store new spam items to the spam_data file
     *
     * @param $json_data
     * @return int $total_taught: Number of new items doctor learned
     * @throws Exception
     */
    public function teachDoctor($json_data)
    {
        if (!$json_data || empty($json_data)) {
            throw new Exception('Please provide data in json string format to teach doctor');
        }

        $json_data = json_decode($json_data);

        if ($json_data == null) {
            throw new Exception('Json is invalid. Please provide valid json data');
        }

        // Convert 2D array to 1D array
        $data = $this->_arrayFlatten($json_data);

        if (!$data) {
            throw new Exception('Either json is invalid or does not contain any value');
        }

        // Add each value to the dictionary file
        $total_taught = 0;
        foreach ($data as $d) {
            if ($this->_teachDoctor($d)) {
                $total_taught += 1;
            }
        }

        return $total_taught;
    }

    /**
     * Get highlighted replaced
     *
     * @return string
     */
    public function getSpamContentHighlightedHtmlReplaced()
    {
        return $this->spamContentHighlightedHtmlReplaced;
    }

    /**
     * Get highlighted text replaced content
     *
     * @return string
     */
    public function getSpamContentHighlightedTextReplace()
    {
        return $this->spamContentHighlightedTextReplaced;
    }

    /**
     * Get text replaced content
     *
     * @return string
     */
    public function getSpamContentTextReplaced()
    {
        return $this->spamContentTextReplaced;
    }

    /**
     * Get html replaced content
     *
     * @return string
     */
    public function getSpamContentHtmlReplaced()
    {
        return $this->spamContentHtmlReplaced;
    }

    /**
     * Create dictionary file if missing
     */
    private function _createDictionary()
    {
        $path_explode = explode('/', $this->spamDictionary);
        $path_dir = str_replace(end($path_explode), '', $this->spamDictionary);

        // Create directory path
        if (!is_dir($path_dir)) {
            mkdir($path_dir, 0777, true);
        }

        file_put_contents($this->spamDictionary, '');
    }

    /**
     * If content is HTML content,
     * remove HTML tags and extract text content only
     *
     */
    private function _processHtml()
    {
        // clean code into script tags
        $this->textContent = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $this->textContent);

        // clean code into style tags
        $this->textContent = preg_replace('/<\s*style.+?<\s*\/\s*style.*?>/si', '', $this->textContent );

        // strip html
        $this->textContent = trim(strip_tags($this->textContent));

        // replace multiple spaces on each line (keep linebreaks) with single space
        $this->textContent = preg_replace("/[[:blank:]]+/", " ", $this->textContent); // (*)

        // replace multiple spaces of all positions (deal with linebreaks) with single linebreak
        $this->textContent = preg_replace('/\s{2,}/', "\n", $this->textContent); // (**)
    }

    /**
     * Prepare spam dictionary items by appending items from
     * the dictionary and user provided items
     */
    private function _prepareSpamDictionaryItems()
    {
        // Get from dictionary
        $this->spamDictionaryItems = $this->_getDictionaryItems();

        // Append user's items
        if ($this->userFilterItems) {
            array_merge($this->spamDictionaryItems, $this->userFilterItems);
        }

        // Trim white spaces from each item and remove duplicate
        $items = [];
        foreach ($this->spamDictionaryItems as $item) {
            $item = trim($item);

            if (in_array($item, $items))
                continue;

            $items[] = $item;
        }

        // Set new items array as spam dictionary items
        $this->spamDictionaryItems = $items;
    }

    /**
     * Teach Spam Doctor
     * Check if filter item already does not exists in the dictionary,
     * Add the new item to the dictionary
     *
     * @param string $d_item
     * @return bool
     */
    private function _teachDoctor($d_item)
    {
        $dictionary_content = file_get_contents($this->spamDictionary);

        // If dictionary is empty, add the item
        if (empty($dictionary_content)) {
            file_put_contents($this->spamDictionary, $d_item);

            return true;
        }

        // If dictionary is not empty, search for the item
        // If item does not exists, append to the dictionary
        if (strpos($dictionary_content, $d_item) === false) {
            // Add to the end of the dictionary file
            $dictionary_content = trim($dictionary_content, ',');
            $dictionary_content = $dictionary_content . ',' . $d_item;

            file_put_contents($this->spamDictionary, $dictionary_content);

            return true;
        }

        return false;
    }

    /**
     * Get Spam Dictionary Items
     * @return array
     */
    private function _getDictionaryItems()
    {
        $dictionary_content = file_get_contents($this->spamDictionary);
        return explode(',', $dictionary_content);
    }

    /**
     * Flatten array to create single dimensional array
     * from 2D Array
     *
     * @param $data
     * @return array|bool
     */
    private function _arrayFlatten($data)
    {
        if (!is_array($data)) {
            return false;
        }

        $result = [];

        foreach ($data as $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->_arrayFlatten($value));
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * Apply replace rule
     *
     * @param $found_item
     */
    private function _processReplaceRule()
    {
        foreach ($this->spamFoundContentItems as $foundItem) {
            $found_item = $foundItem;
            $replace_item = $foundItem;

            $data = (array)json_decode($this->replaceRule);

            // Get common symbol to replace
            $common = '';
            if (key_exists('*', $data)) {
                $common = $data['*'];
            }

            $replaced = false;

            // Loop over each rule
            foreach ($data as $d => $v) {
                if (strpos($replace_item, $d) !== false) {
                    // If rule key found in the item
                    // Replace with the correspondence symbol
                    $replace_item = str_replace($d, $v, $replace_item);
                    $replaced = true;
                }
            }

            if (!$replaced) {
                // Else replace with common symbol at random place
                $str_len = strlen($replace_item);
                $rand = rand(0, $str_len);
                $replace_item = substr_replace($replace_item, $common, $rand, 0);
            }

            // replace in variables
            $this->spamContentHighlightedHtmlReplaced = str_replace($found_item, $replace_item, $this->spamContentHighlightedHtmlReplaced);
            $this->spamContentHighlightedTextReplaced = str_replace($found_item, $replace_item, $this->spamContentHighlightedTextReplaced);
            $this->spamContentTextReplaced = str_replace($found_item, $replace_item, $this->spamContentTextReplaced);
            $this->spamContentHtmlReplaced = str_replace($found_item, $replace_item, $this->spamContentHtmlReplaced);
        }
    }
}

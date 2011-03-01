<?php

interface qtype_pmatch_word_delimiter {
    /**
     * 
     * Check that items separated pmatch expressions are in the right order 
     * and / or proximity to be matched validly. Do not need to check that the two words are not the same.
     * 
     * @param integer $phrasewordno1
     * @param integer $phrasewordno2
     * @param qtype_pmatch_phrase_level_options $phraseleveloptions
     * @return boolean 
     */
    public function valid_match($phrasewordno1, $phrasewordno2, $phraseleveloptions);
}
interface qtype_pmatch_can_match_char {
    /**
     * Can possibly match a char.
     * @param string $char a character
     * @return boolean successful match?
     */
    public function match_char($char);
}
interface qtype_pmatch_can_match_multiple_or_no_chars {
    /**
     * Can possibly match some characters.
     * @param string $chars some characters to match
     * @return boolean successful match?
     */
    public function match_chars($chars);
}
interface qtype_pmatch_can_match_word {
    /**
     * Can possibly match a word.
     * @param array $word a word
     * @param qtype_pmatch_word_level_options $wordleveloptions
     * @return boolean successful match?
     */
    public function match_word($word, $wordleveloptions);
}
interface qtype_pmatch_can_match_phrase {
    /**
     * 
     * Can possibly match a phrase - can accept more than one word at once.
     * @param array $phrase array of words
     * @param qtype_pmatch_phrase_level_options $phraseleveloptions
     * @param qtype_pmatch_word_level_options $wordleveloptions
     * @return boolean successful match?
     */
    public function match_phrase($phrase, $phraseleveloptions, $wordleveloptions);
}

abstract class qtype_pmatch_matcher_item{
    protected $interpreter;
    /**
     * 
     * Constructor normally called by qtype_pmatch_interpreter_item->get_matcher method
     * @param qtype_pmatch_interpreter_item $interpreter
     */
    public function __construct($interpreter){
        $this->interpreter = $interpreter;
    }

    /**
     * 
     * Used for testing purposes. To make sure type and type of contents is as expected.
     */
    public function get_type(){
        $typeobj = new stdClass();
        $typeobj->name = $this->get_type_name($this);
        return $typeobj;
    }
    public function get_type_name($object){
        return substr(get_class($object), 21);
    }
}
abstract class qtype_pmatch_matcher_item_with_subcontents extends qtype_pmatch_matcher_item{

    protected $subcontents = array();
    
    /**
     * 
     * Create a tree of matcher items.
     * @param qtype_pmatch_interpreter_item_with_subcontents $interpreter
     */
    public function __construct($interpreter){
        parent::__construct($interpreter);
        $interpretersubcontents = $interpreter->get_subcontents();
        foreach ($interpretersubcontents as $interpretersubcontent){
            $this->subcontents[] = $interpretersubcontent->get_matcher();
        }
    }
    /**
     * 
     * Used for testing purposes. To make sure type and type of contents is as expected.
     */
    public function get_type(){
        $typeobj = new stdClass();
        $typeobj->name = $this->get_type_name($this);
        $typeobj->subcontents = array();
        foreach ($this->subcontents as $subcontent){
            $typeobj->subcontents[] = $subcontent->get_type();
        }
        return $typeobj;
    }
    
}

class qtype_pmatch_matcher_whole_expression extends qtype_pmatch_matcher_item_with_subcontents{

}
class qtype_pmatch_matcher_not extends qtype_pmatch_matcher_item_with_subcontents{
}
class qtype_pmatch_matcher_match extends qtype_pmatch_matcher_item_with_subcontents{
}
class qtype_pmatch_matcher_match_any extends qtype_pmatch_matcher_match{
}

class qtype_pmatch_matcher_match_all extends qtype_pmatch_matcher_match{
}

class qtype_pmatch_matcher_match_options extends qtype_pmatch_matcher_match
            implements qtype_pmatch_can_match_phrase {
    public function match_whole_expression($expression){
        $phrase = preg_split('!/s+!', $expression);
        return $this->match_phrase($phrase, $this->interpreter->phraseleveloptions, $this->interpreter->wordleveloptions);
    }
    public function match_phrase($phrase, $phraseleveloptions, $wordleveloptions){

        $matchestotry = $this->next_possible_match(count($phrase), $phraseleveloptions);
        do {
            $failure = false;
            foreach ($matchestotry as $subcontentno => $wordno) {
                $subcontent = $this->subcontents[$subcontentno];
                $word = $phrase[$wordno];
                if ($subcontent instanceof qtype_pmatch_can_match_word){
                    if ($subcontent->match_word($word, $wordleveloptions) !== true){
                        $failure = true;
                        break;
                    }
                } 
            }
            if (!$failure){
                return true;
            }
            $matchestotry = $this->next_possible_match(count($phrase), $phraseleveloptions, $matchestotry);
        } while (count($matchestotry));
        return false;
    }
    /**
     * 
     * Return an array where the key is the subcontentno and the value is the word to try to match. Or return an
     * empty array if there are no more possible matches left.
     */
    public function next_possible_match($phraselength, $phraseleveloptions, $lastmatchtried = null){
        $noofmatchablecontents = (count($this->subcontents) + 1)/2;
        if (is_null($lastmatchtried)){
            $matchtotry = array();
            for ($i=0; $i < $noofmatchablecontents; $i++){
                $matchtotry[$i*2] = 0;
            }
        } else {
            $matchtotry = $lastmatchtried;
        }
        if (((!$phraseleveloptions->get_allow_extra_words()) && $phraselength !== $noofmatchablecontents)
                || $noofmatchablecontents > $phraselength){
            return array();//there are no possible valid matches
        }
        //iterate through all possible combinations
        do {
            $index = 0;
            while ($matchtotry[$index] == $phraselength) {
                $matchtotry[$index] = 0;
                $index = $index + 2;
                if ($index > count($this->subcontents)){
                    return array();//no more possible combinations
                }
            }
            $matchtotry[$index]++;
            //only allow valid match attempts
            if (!$phraseleveloptions->get_allow_any_word_order() && !$phraseleveloptions->get_allow_extra_words()){
                if ($matchtotry[0]!==0){
                    $notvalid = true;
                }
            } else if (count(array_unique($matchtotry)) == count($matchtotry)){
                $subcontentnos = array_keys($matchtotry);
                foreach ($subcontentnos as $subcontentnokey => $subcontentno){
                    if ($subcontentnokey > 0){
                        //after the first key pass this value and the last to their separator to test if they are valid
                        if (!$this->subcontents[$subcontentno - 1]->valid_match($matchtotry[$subcontentno - 2],
                                                            $matchtotry[$subcontentno], $phraseleveloptions)){
                            $notvalid = true;
                            break;
                        }
                    } 
                }
            } else {
                $notvalid = true;
            }
        } while ($notvalid);
        return $matchtotry;
    }
}
class qtype_pmatch_matcher_or_list extends qtype_pmatch_matcher_item_with_subcontents
            implements qtype_pmatch_can_match_phrase, qtype_pmatch_can_match_word{
    public function match_word($word, $wordleveloptions){
        foreach ($this->subcontents as $subcontent){
            if ($subcontent instanceof qtype_pmatch_can_match_word &&
                        $subcontent->match_word($word, $wordleveloptions) === true){
                return true;
            }
        }
        return false;
    }
    public function match_phrase($phrase, $phraseleveloptions, $wordleveloptions){
        foreach ($this->subcontents as $subcontent){
            if ($subcontent instanceof qtype_pmatch_can_match_phrase &&
                        $subcontent->match_phrase($phrase, $phraseleveloptions, $wordleveloptions) === true){
                return true;
            }
        }
        return false;
    }

}
class qtype_pmatch_matcher_or_character extends qtype_pmatch_matcher_item{

}
class qtype_pmatch_matcher_or_list_phrase extends qtype_pmatch_matcher_item_with_subcontents
            implements qtype_pmatch_can_match_phrase{
    public function match_phrase($phrase, $phraseleveloptions, $wordleveloptions){
        foreach ($this->subcontents as $subcontent){
            if ($subcontent instanceof qtype_pmatch_can_match_phrase &&
                        $subcontent->match_phrase($phrase, $phraseleveloptions, $wordleveloptions) === true){
                return true;
            }
        }
        return false;
    }
}


class qtype_pmatch_matcher_phrase extends qtype_pmatch_matcher_item_with_subcontents
            implements qtype_pmatch_can_match_phrase{
    public function match_phrase($phrase, $phraseleveloptions, $wordleveloptions){
        $wordno = 0;
        $subcontentno = 0;
        do {
            $subcontent = $this->subcontents[$subcontentno];
            $word = $phrase[$wordno];
            if ($subcontent instanceof qtype_pmatch_can_match_word){
                if ($subcontent->match_word($word, $wordleveloptions) !== true){
                    return false;
                }
                $wordno++;
            } 
            $subcontentno++;
            $nomorewords = (count($phrase) < ($wordno + 1));
            $nomoreitems = (count($this->subcontents) < ($subcontentno + 1));
            if ($nomorewords && $nomoreitems){
                return true;
            } else if ($nomorewords || $nomoreitems) {
                return false;
            }
        } while (true);
    }
}
class qtype_pmatch_matcher_word_delimiter_space extends qtype_pmatch_matcher_item
            implements qtype_pmatch_word_delimiter {
    public function valid_match($phrasewordno1, $phrasewordno2, $phraseleveloptions){
        if (!$phraseleveloptions->get_allow_any_word_order() && !$phraseleveloptions->get_allow_extra_words()){
            return ($phrasewordno2 == ($phrasewordno1 + 1));
        } else if (!$phraseleveloptions->get_allow_any_word_order()){
            return ($phrasewordno2 > $phrasewordno1);
        } else {
            return true;
        }
    }
}
class qtype_pmatch_matcher_word_delimiter_proximity extends qtype_pmatch_matcher_item
            implements qtype_pmatch_word_delimiter {
    public function valid_match($item1, $item2, $phraseleveloptions){
        if ($phrasewordno2 < $phrasewordno1){
            return false;
        }
        return ($phrasewordno2 - $phrasewordno1) < $phraseleveloptions->get_allow_proximity_of();
    }
}
class qtype_pmatch_matcher_word extends qtype_pmatch_matcher_item_with_subcontents implements qtype_pmatch_can_match_word{
    /**
     * 
     * Enter description here ...
     * @var qtype_pmatch_word_level_options
     */
    private $wordleveloptions;
    private $usedmisspellings;
    /**
     * 
     * Called after running match_word. This function returns the minimum number of mispellings used to match the student response word to the
     * pmatch expression.
     * @return integer the number of misspellings found.
     */
    public function get_used_misspellings(){
        return $this->usedmisspellings;
    }
    public function match_word($word, $wordleveloptions){
        $this->wordleveloptions = $wordleveloptions;
        for ($this->usedmisspellings = 0; $this->usedmisspellings <= $this->wordleveloptions->get_misspellings(); $this->usedmisspellings++){
            if ($this->check_match_branches($word, $this->usedmisspellings)){
                return true;
            }
        }
        $this->usedmisspellings = 0;
        return false;
    }
    /**
     * 
     * Check each character against each item and iterate down branches of possible matches to whole
     * word.
     * @param string $word word to match from student response
     * @param integer $charpos position of character in word we are currently checking for a match
     * @param integer $subcontentno subcontent item to match this character against
     * @param integer $noofcharactertomatch no of characters to match
     * @return boolean true if we find one match branch that successfully matches the whole word
     */
    private function check_match_branches($word, $allowmispellings, $charpos = 0, $subcontentno = 0, $noofcharactertomatch = 1){
        $itemslefttomatch = count($this->subcontents) - ($subcontentno + 1);
        $charslefttomatch = strlen($word) - ($charpos + $noofcharactertomatch);
        //print_object(array('args' => func_get_args())+compact('itemslefttomatch', 'charslefttomatch'));
        //check if we have gone beyond limit of what can be matched
        if ($itemslefttomatch < 0){
            if ($charslefttomatch < 0){
                return true;
            } else if ($this->wordleveloptions->get_allow_extra_characters()){
                return true;
            }else if ($this->wordleveloptions->get_misspelling_allow_extra_char() && ($allowmispellings > $charslefttomatch)){
                return true;
            } else {
                return false;
            }
        } else if ($charslefttomatch < 0) {
            if ($this->wordleveloptions->get_misspelling_allow_fewer_char() && ($allowmispellings > $itemslefttomatch)){
                return true;
            } else if (($this->subcontents[$subcontentno] instanceof qtype_pmatch_can_match_multiple_or_no_chars)
                    && ($this->check_match_branches($word, $allowmispellings, $charpos + 1, $subcontentno + 1, $noofcharactertomatch))){
                //no chars left to match but this is a multiple match wild card, so no match needed.
                return true;
            } else {
                return false;
            }
        }
        if ($this->subcontents[$subcontentno] instanceof qtype_pmatch_can_match_multiple_or_no_chars){
            $thisfragmentmatched = $this->subcontents[$subcontentno]->match_chars(substr($word, $charpos, $noofcharactertomatch));
        } else {
            $thisfragmentmatched = $this->subcontents[$subcontentno]->match_char(substr($word, $charpos, $noofcharactertomatch));
        }

        if (($noofcharactertomatch == 1) &&
                $this->subcontents[$subcontentno] instanceof qtype_pmatch_can_match_multiple_or_no_chars){
            //check for the multiple char match wild card matching no characters at the same time as checking for matching one
            if ($this->check_match_branches($word, $allowmispellings, $charpos, $subcontentno + 1, 1)){
                return true;
            }
        }
        if ((!$thisfragmentmatched) && $this->wordleveloptions->get_allow_extra_characters()){
            if ($this->check_match_branches($word, $allowmispellings, $charpos + 1, $subcontentno, 1)){
                return true;
            }
        }
        if ((!$thisfragmentmatched) && ($allowmispellings > 0)) {
            //if there is no match but we can match the next character 
            if ($this->wordleveloptions->get_misspelling_allow_transpose_two_chars()&& 
                        ($itemslefttomatch > 0) && ($charslefttomatch > 0)){
                if (!$this->subcontents[$subcontentno + 1] instanceof qtype_pmatch_can_match_multiple_or_no_chars){
                    $wordtransposed = $word;
                    $wordtransposed[$charpos] = $word[$charpos + 1];
                    $wordtransposed[$charpos + 1] = $word[$charpos];
                    if ($this->check_match_branches($wordtransposed, $allowmispellings - 1, $charpos, $subcontentno, 1)){
                        return true;
                    }
                }
            }
            //and if there is no match try ignoring this item
            if ($this->wordleveloptions->get_misspelling_allow_fewer_char()){
                if ($this->check_match_branches($word, $allowmispellings - 1, $charpos, $subcontentno + 1, 1)){
                    return true;
                }
            }
            //and if there is no match try ignoring this character
            if ($this->wordleveloptions->get_misspelling_allow_extra_char()){
                if ($this->check_match_branches($word, $allowmispellings - 1, $charpos + 1, $subcontentno, 1)){
                    return true;
                }
            }
            //and if there is no match try going on as if it was a match
            if ($this->wordleveloptions->get_misspelling_allow_replace_char()){
                if ($this->check_match_branches($word, $allowmispellings - 1, $charpos + 1, $subcontentno + 1, 1)){
                    return true;
                }
            }
        }
        
        if ($thisfragmentmatched){
            if ($this->subcontents[$subcontentno] instanceof qtype_pmatch_can_match_multiple_or_no_chars){
                if ($this->check_match_branches($word, $allowmispellings, $charpos, $subcontentno, $noofcharactertomatch + 1)){
                    return true;
                }
                if ($this->check_match_branches($word, $allowmispellings, $charpos + $noofcharactertomatch, $subcontentno + 1, 1)){
                    return true;
                }
            } else if ($this->check_match_branches($word, $allowmispellings, $charpos + $noofcharactertomatch, $subcontentno + 1, 1)){
                return true;
            }
        } else {
            return false;
        }
    }
}
class qtype_pmatch_matcher_character_in_word extends qtype_pmatch_matcher_item implements qtype_pmatch_can_match_char{
    public function match_char($character){
        $codefragment = $this->interpreter->get_code_fragment();
        return ($character == $codefragment);
    }
}
class qtype_pmatch_matcher_special_character_in_word extends qtype_pmatch_matcher_item implements qtype_pmatch_can_match_char{
    public function match_char($character){
        $codefragment = $this->interpreter->get_code_fragment();
        return ($character == $codefragment[1]);
    }
}
class qtype_pmatch_matcher_wildcard_match_single extends qtype_pmatch_matcher_item implements qtype_pmatch_can_match_char{
    public function match_char($character){
        return array(true);
    }
}
class qtype_pmatch_matcher_wildcard_match_multiple 
            extends qtype_pmatch_matcher_item implements qtype_pmatch_can_match_multiple_or_no_chars{

    public function match_chars($characters){
        return true;
    }

}
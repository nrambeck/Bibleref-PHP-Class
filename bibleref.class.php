<?
/*
  Generic PHP Class for generating BibleRef markup on text
*/

define('BIBLEREF_CLASS_VERSION', '0.1');

class Bibleref {

  private $locale;
  private $class_directory;
    
  function __construct($locale = 'en') {
    $this->locale = $locale;
  }
  
	function set_directory($path) {
		$this->class_directory = $path;
	}
	
  function parse($content) {
		// First, check for references that don't have tags
		// Make sure we're not using a ref already included in tags
		$anchor_regex = '<a\s+href.*?<\/a>';
		$cite_regex = '<cite.+?>.*<\/cite>';
    $pre_regex = '<pre>.*<\/pre>';
    $code_regex = '<code>.*<\/code>';
		$split_regex = "/((?:$anchor_regex)|(?:$cite_regex)|(?:$pre_regex)|(?:$code_regex))/i";

		$parsed_text = preg_split($split_regex, $content, -1, PREG_SPLIT_DELIM_CAPTURE);
		$linked_text = '';

		foreach ($parsed_text as $part) {
			if (preg_match($split_regex, $part)) {
				$linked_text .= $part; // if it is within an element, leave it as is
			} else {
				// Okay, we have text not inside of tags. Now do something with it... And please, try not to break anything.
				$linked_text .= $this->extract($part); // parse it for Bible references
			}
		}

		$content = $linked_text;

		return $content;    
  }
  
	function extract($text) {
    $volume_regex = '1|2|3|I|II|III|1st|2nd|3rd|First|Second|Third';
    $book_regex = $this->books_regex();
		//$book_regex = '(?:\w{2,12}\.?)';
		$verse_substr_regex = "(?:[:.][0-9]{1,3})?(?:[-&,;]\s?[0-9]{1,3})*";
		$verse_regex = "[0-9]{1,3}(?:". $verse_substr_regex ."){1,2}";
		$passage_regex = '/(?:('.$volume_regex.')\s)?\b('.$book_regex.')\.?\s('.$verse_regex.')/i';

		$text = preg_replace_callback($passage_regex, array(&$this, 'assemble'), $text);

		return $text;
	}
  
  function books($locale) {
    static $books = NULL;
    
    if ($books) {
      return $books;
    }
    
		$translation_file = $this->class_directory .'/'. $locale .'.translation';
    $content = file_get_contents($translation_file);
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
      list ($key, $value) = explode('=', $line);
      $forms = explode(',', $value);
      foreach ($forms as $form) {
        if (trim($form))
				  $books[strtolower(trim($form))] = trim($key);
      }
    }
    return $books;
  }
  
  function books_regex() {
    $books = $this->books($this->locale);
    $forms = array_keys($books);
    $regex = implode('|', $forms);
    return $regex;
  }
  
  function get_book($name) {
    $name = strtolower($name);
    $books = $this->books($this->locale);
    if (isset($books[$name])) {
      return $books[$name];
    }
    else {
      return FALSE;
    }
  }
  
	function assemble($matches) {
    $reference = $matches[0];
    $volume = $matches[1];
    $book = $matches[2];
    $verse = $matches[3];
    
    if (!$volume) {
      $v = substr($book, 0, 1);
      if (in_array($v, array('1', '2', '3'))) {
        $volume = $v;
        $book = substr($book, 1);
      }
    }
    
    // Strip the period off abbrevations
    $new_book = trim($book, '.');
    // Get the full book name
    $new_book = $this->get_book($new_book);
    
    // If the book doesn't exist, return the reference as is
    if (!$new_book) {
      return $reference;
    }
    
		if ($volume) {
      $vol_find = array('III', 'II', 'I', 'Third', 'Second', 'First');
      $vol_replace = array('3', '2', '1', '3', '2', '1');
			$new_volume = str_replace($vol_find, $vol_replace, $volume);
			$new_volume = $new_volume{0}; // will remove st,nd,and rd (presupposes regex is correct)
		}

    $volume = $v ? $volume : $volume .' ';
		$passage = $volume . $book ." ". $verse;
		$passage = trim($passage);

		// We have our main reference. Is this discontinuous? Should we do
		// multiple cites?

		$findPairs = "/([,;]?\s?)?([0-9]{1,3}[:.]?[0-9]{0,3}[-]?[0-9]{0,3})/";
		preg_match_all($findPairs, " ". $verse, $pairs);

		for ($i = 0 ; $i < sizeof($pairs[0]) ; $i++)
		{
			$verseRef = $pairs[2][$i];
			$newVerseRef = str_replace('.', ':', $verseRef);
			$contMatch = $pairs[0][$i];

			// Get the chapter ref
			$chapMatch = "/([0-9]{1,3})[:.]{1}/";
			preg_match($chapMatch, $verseRef, $chapIt);

			if (sizeof($chapIt) > 0) {
				$chapter = $chapIt[1];
			}
      else {
        if ($i == 0) {
  				$newVerseRef = $chapter = $verseRef;
        }
        else {
  				$newVerseRef = $chapter .":". $verseRef;
        }
			}

			if ($i == 0)
			{
				$verseRef = $volume . $book ." ". $verseRef;
				$contMatch = $volume . $book . $contMatch;
			}

	  	$contMod = str_replace($verseRef, '<cite class="bibleref" title="'. $new_volume . $new_book .' '. $newVerseRef .'">'. $verseRef .'</cite>', $contMatch);
  		$passage = str_replace($contMatch, $contMod, $passage);
		}

		return $passage;
	}

}


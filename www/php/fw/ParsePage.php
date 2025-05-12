<?php

/**
 * ParsePage - Template Parser
 *
 * Part of PHP osa framework  www.osalabs.com/osafw/php
 * (c) 2009-2025 Oleg Savchuk www.osalabs.com
 */

/*
2006-07-15 Oleg Savchuk - fixed inline tags parsing
2006-12-30 - fixed parse_page_sort_tags (added \b)
2009-03-28 - added multi-language support
2009-03-30 - added 'global' attribute
2008-08-29 - fixed inline|sub tag sorting
2008-09-27 - added suport for modifiers like in Smarty
2008-09-27 - added support for deep arrays/hashes like <~var[aaa][0][bbb]>
2008-09-27 - fixed subtemplates paths in inline tags
2008-09-27 - added parent tag support
2008-12-30 - added global[] and session[] support
2009-01-11 - added cols to repeat (FEATURE REMOVED)
2009-02-22 - added [] support to select/radio
2009-03-14 - added even/odd support
2009-05-07 - added htmlescape_back for radio delim
2009-05-15 - fixed truncate
2010-08-04 - added ability to set custom open/close tags instead of <~xxx>, added parsing of descriptions in select/radios
2010-08-12 - fixed hfvalue if there are no array passed to tags like <~arr[a][b][c]>
2010-08-17 - added ability to turn off lang parsing
2011-08-12 - added string_format/sprintf
2011-09-20 - changes in parse_json and var2js to use json_encode and application/json content type
2011-10-10 - use hfvalue in parse_cache_template
2011-11-21 - fixed: use tag_replace_raw for replacing empty tags (or tags not passed if)
2011-12-01 - added number_format attribute
2012-11-10 - CSRF shield - all vars escaped, if var shouldn't be escaped use "noescape" attr: <~raw_variable noescape>
2012-11-10 - <~tag if="var"> just test var for TRUE, not using eval(), also added <~tag unless="var">, i.e. evaled if deprecated
2012-11-29 - if/unless fixes
2012-12-01 - added get_selvalue fuction
2012-12-04 - added support of SESSION/GLOBAL to repeat
2013-02-23 - vvalue="abc" - actual value got via getVariableValue('abc', $data);
2013-04-04 - removed evaled parse_page_if
2013-04-04 - changed ifXX to perl-notation, ex: neq => eq see below (!!!)
2013-04-04 - removed cols support from repeat
2013-04-04 - tag_if()
2013-04-04 - if/unless final TRUE/FALSE conditions documented
2013-04-09 - fixed: when .sel lines parsed now it don't skip lines with 0
2013-10-20 - fixed: passing empty value to repeat tag
2013-10-23 - fixed: delim for radios now is a class for <label>
2014-11-20 - fixed: tag_if better compares values
2014-11-25 - changed to use $CONFIG global var instead site_root, site_templ...
2014-11-28 - fixed: sec2date now detect '0000-00-00 00:00:00' and return empty string
2017-03-08 - fixed: quote special PHP $ and \\12345
2017-09-24 - parse_radio_tag changed to bootstrap4
2018-01-18 - support for <~object[property]>
2018-01-27 - parse_radio_tag changed to bootstrap4 custom radio
2018-05-07 - date standard formats support: short/long/sql, support select from arrays
2018-07-29 - added more secure headers
2019-01-31 - added json, PARSEPAGE.TOP, PARSEPAGE.PARENT
2019-02-01 - added sub="var" support
2025-01-11 - converted to ParsePage class and PHP 8.3+
2025-01-16 - comments support <~#tag>
2025-01-17 - added support for attributes: currency, url, noparse
2025-03-30 - added support for languageCallback

##########################################################
tags in templates should be in <~name [attributes]> format
<~var[aaa][0][bbb]>  - also supported

# Supported attributes:

var - tag is variable, no fileseek necessary if var is not present in $data
ifXX - if conditions
    ifeq="var" value="XXX" - tag/template will be parsed only if var=XXX
    ifne="var" value="XXX" - tag/template will be parsed only if var!=XXX
    ifgt="var" value="XXX" - tag/template will be parsed only if var>XXX
    ifge="var" value="XXX" - tag/template will be parsed only if var>=XXX
    iflt="var" value="XXX" - tag/template will be parsed only if var<XXX
    ifle="var" value="XXX" - tag/template will be parsed only if var<=XXX

vvalue - value as hf variable:
    <~tag ifeq="var" vvalue="YYY"> - actual value got via getVariableValue('YYY', $data);

if/unless shortcuts:
    <~tag if="var"> - tag will be shown if var is evaluated as TRUE, not using eval(), equivalent to "if ($var)"
    <~tag unless="var"> - tag will be shown if var is evaluated as TRUE, not using eval(), equivalent to "if (!$var)"
-------------------------
True/False determined according boolval():
    TRUE values:
      non-empty string, but not equal to '0'!
      1 or other non-zero number
      true (boolean)

    FALSE values:
      '0'
      0
      false (boolean)
      ''
      unset variable
-------------------------

repeat - use $data[tag] (array of hashes) for repeat content, the following vars can be used in repeat template:
    repeat.first (0-not first, 1-first)
    repeat.last  (0-not last, 1-last)
    repeat.total (total number of items)
    repeat.index  (0-based)
    repeat.iteration (1-based)
    example:  <~/common/comma unless="repeat.last"> - will add comma template for all but last item

sub - this attribute tell parser to use sub-hashtable for parse subtemplate ($data hash should contain reference to hash), examples:
  <~tag sub inline>...</~tag>- use $data[tag] as hashtable for inline template
  <~tag sub="var"> - use $data[var] as hashtable for template in "tag.html"
inline - this attribute tell parser that subtemplate is not in file - it's between <~tag>...</~tag> , useful in combination with 'repeat' and 'if'
global - this attribute is a global var, not in $data hash
  <~GLOBAL[var]> - also possible
session - this tag is a $_SESSION var, not in $data hash
  <~SESSION[var]> - also possible
parent - this tag is a $parent_hf var, not in current $data hash
  <~PARSEPAGE.PARENT[var]> - also possible (parent $data)
  <~PARSEPAGE.TOP[var]> - also possible (topmost $data)

select="var" - this attribute tells parser to either load file with tag name and use it as value|display for <select> tag
    or if variable with tag name exists - use it as arraylist of hashtables with id/iname keys
    , example:
    <select name="item[fcombo]">
        <option value=""> - select -
        <~./fcombo.sel select="fcombo">  or <~fcombo_options select="fcombo">
    </select>

radio="var" name="YYY" [delim="CLASSNAME"]- this attribute tell parser to load file and use it as value|display for <input type=radio> tags, example:
    <~./fradio.sel radio="fradio" name="item[fradio]" delim="custom-control-inline">

selvalue="var" - display value (fetched from the .sel file) for the var (example: to display 'select' and 'radio' values in List view)
    ex: <~../fcombo.sel selvalue="fcombo">  or <~fcombo_options selvalue="fcombo">

nolang - do not parse lang strings in backticks `Hello`, files only, not "inline" templates (this automatically applied for .js files)
noescape - do not escape variable
htmlescape - (by default) replace special symbols by their html equivalents (such as <>,",')

support `text` => replaced by multilang from $CONFIG['SITE_TEMPLATES']/lang/$lang.txt according to $GLOBAL['lang'] (if !='' - english by default)
    example: <b>`Hello`</b>
    lang.txt line format:
    english string === lang string

support modifiers:
date          - format as datetime, sample "d M Y H:i", see http://php.net/manual/en/function.date.php
    <~var date>         output "m/d/Y" - date only (TODO - should be formatted per user settings actually)
    <~var date="short"> output "m/d/Y H:i" - date and time short (to mins)
    <~var date="long">  output "m/d/Y H:i:s" - date and time long
    <~var date="sql">   output "Y-m-d H:i:s" - sql date and time
currency[="USD"]      - NumberFormatter::formatCurrency(value, "3 letter ISO 4217 currency code") => $12,345.12
string_format
sprintf
truncate
strip_tags
trim
nl2br
count
lower
upper
default
urlencode
url             - add https:// to begin of string if absent
json[="pretty"] - produces json-compatible string, example: {success:true, msg:""}
noparse       - doesn't parse file and just include file by tag path as is, ignores all other attrs except if
 */

class ParsePage {
    private static array $langCacheGlobal = []; //Cache for loaded language files - shared across all ParsePage instances

    /**************************************************************************
     * Configuration & Internal Properties
     **************************************************************************/
    private array $parsePageOps = ['if', 'unless', 'ifeq', 'ifne', 'ifgt', 'iflt', 'ifge', 'ifle']; //Supported conditions for if/unless attributes
    private string $openTag = '<~'; //Opening tag for parse patterns
    private string $closeTag = '>'; //Closing tag for parse patterns
    private string $openEndTag = '</~'; //Closing tag for inline subtemplates

    // runtime configuration
    private string $templatesRoot; //templates root path
    private string $rootUrl = ''; //Root URL for the site
    /* @var array|callable */
    private $globals = []; //Global variables
    private string $language = 'en'; //Current language
    private string $languageDir = '/lang'; //Directory for language files, relative to templates root
    private bool $isLanguageUpdate = false; //true if auto-add missing language strings to lang file

    private mixed $languageCallback = null; //Callback for language string processing

    // working variables
    private array $dataTop = []; //Data top-level reference (similar to PARSE_PAGE_DATA_TOP in original)
    private bool $langParse = true; //Whether to parse language strings enclosed in backticks
    private array $fileCache = []; //File cache for loaded template files to prevent re-loading from disk
    private string $baseDir = ''; //base directory for current Page

    // preg_quoted versions for performance
    private string $openTagQuoted;
    private string $closeTagQuoted;
    private string $openEndTagQuoted;

    /**
     * Constructor
     *
     * @param array $options can contain:
     *   string `templatesRoot` - Root path for templates - REQUIRED here or via setTemplatesRoot()
     *   string `rootUrl` - Root URL for the site
     *   array|callable `globals` - Global variables
     *   string `language` - Language code (default: 'en')
     *   string `languageDir` - Directory for language files, relative to templates root
     *   bool `isLangUpdate` - Whether to auto-add missing language strings to lang file
     */
    public function __construct(array $options = []) {
        //string $templatesRoot, $rootUrl, array $globals, string $language = null, string $languageDir = null, bool $isLanguateUpdate = false) {
        // Apply any known options using chainable setters
        if (isset($options['templatesRoot'])) {
            $this->setTemplatesRoot($options['templatesRoot']);
        }
        if (isset($options['rootUrl'])) {
            $this->setRootUrl($options['rootUrl']);
        }
        if (isset($options['globals'])) {
            $this->setGlobals($options['globals']);
        }
        if (isset($options['language'])) {
            $this->setLanguage($options['language']);
        }
        if (isset($options['languageDir'])) {
            $this->setLanguageDir($options['languageDir']);
        }
        if (isset($options['isLangUpdate'])) {
            $this->setLangUpdate($options['isLangUpdate']);
        }
        if (isset($options['languageCallback'])) {
            $this->setLanguageCallback($options['languageCallback']);
        }

        $this->initTagQuotes();
    }

    private function initTagQuotes(): void {
        // Pre-compile quoted versions of open/close tags for performance
        $this->openTagQuoted    = preg_quote($this->openTag, '/');
        $this->closeTagQuoted   = preg_quote($this->closeTag, '/');
        $this->openEndTagQuoted = preg_quote($this->openEndTag, '/');
    }

    /*************************************************************************
     * Chainable Setters (return $this)
     *************************************************************************/
    public function setTemplatesRoot(string $path): self {
        $this->templatesRoot = rtrim($path, '/');
        return $this;
    }

    public function setRootUrl(string $url): self {
        $this->rootUrl = $url;
        return $this;
    }

    /**
     * Accept either an array or a callable.
     * If callable, we can later do call_user_func($this->globals).
     */
    public function setGlobals(array|callable $globals): self {
        $this->globals = $globals;
        return $this;
    }

    public function setLanguage(string $lang): self {
        $this->language = $lang;
        return $this;
    }

    public function setLanguageDir(string $dir): self {
        $this->languageDir = $dir;
        return $this;
    }

    public function setLangUpdate(bool $flag): self {
        $this->isLanguageUpdate = $flag;
        return $this;
    }

    public function setLanguageCallback(callable $callback): self {
        $this->languageCallback = $callback;
        return $this;
    }

    /**************************************************************************
     * Public API - parsePage
     **************************************************************************/

    /**
     * Main entry point for parsing a template.
     *
     * @param string $baseDir Base directory relative to SITE_TEMPLATES
     * @param string $templateName The template file name or path
     * @param array $data Hash of data to substitute
     * @return string         Parsed result (if outputMode='v'), otherwise prints or writes file
     * @throws Exception
     */
    public function parsePage(string $baseDir, string $templateName, array $data): string {
        $this->logger("NOTICE", "Parsing template [$templateName] with baseDir [$baseDir]");
        //validate $this->templatesRoot is set
        if ($this->templatesRoot === '') {
            throw new Exception('ParsePage - templatesRoot not set');
        }

        // Set top-level data reference
        $this->dataTop = &$data;
        $this->baseDir = rtrim($baseDir, '/');

        // Parse the template content
        $parsedOutput = $this->parseTemplate($templateName, $data);
        #$this->logger("DEBUG", "Parsed page output: $parsedOutput");
        return $parsedOutput;
    }

    /**
     * Parse template content from a string.
     *
     * @param string $template
     * @param array $data
     * @return string
     * @throws Exception
     */
    public function parseString(string $template, array $data): string {
        // Set top-level data reference
        $this->dataTop = &$data;
        $this->baseDir = '/';

        // Parse the template content
        $parsedOutput = $this->parseTemplate('', $data, $template);
        #$this->logger("DEBUG", "Parsed string output: $parsedOutput");
        return $parsedOutput;
    }

    /**************************************************************************
     * Internal Parsing Methods
     **************************************************************************/

    /**
     * Load and parse the template by scanning for <~tag [attrs]>, inline subtemplates, etc.
     *
     * @param string $templateName Template file path/name (will be appended to $baseDir if not absolute)
     * @param array $data key/value of data for substitutions
     * @param string $inlineContent Internal usage only (inline template content if any)
     * @param array|null $parentData Parent data for 'parent' attributes
     * @param array|null $parentAttrs Parent attributes
     * @return string    Parsed template content
     * @throws Exception
     */
    private function parseTemplate(string $templateName, array $data, string $inlineContent = '', array $parentData = null, array $parentAttrs = null): string {
        #$this->logger("DEBUG", "Parsing template: [$templateName]" . ($inlineContent !== '' ? ' inline' : ''));
        // Resolve the full template path
        $templateName = $this->getFullTemplatePath($templateName);

        // Load the template file if inline content not provided
        $content = ($inlineContent !== '' ? $inlineContent : $this->loadTemplate($templateName));
        if ($content === '') {
            $this->logger("TRACE", "ParsePage - empty template: [$templateName]");
            return ''; // empty template - nothing to parse
        }

        //parse lang if caller attrs doesn't have "nolang" key and tpl_name is not .js file
        if (!isset($parentAttrs['nolang']) && !str_ends_with($templateName, '.js')) {
            $this->parseLanguage($content);
        }

        // Grab all <~...> tags from content
        $pattern = '/' . $this->openTagQuoted . '([^' . $this->closeTagQuoted . ']+)' . $this->closeTagQuoted . '/';
        if (preg_match_all($pattern, $content, $matches)) {
            $tags = $matches[1];

            // Sort tags so 'inline' / 'sub' are processed first
            $isSort = false;
            foreach ($tags as $t) {
                if (preg_match('/\b(inline|sub)\b/', $t)) {
                    $isSort = true;
                    break;
                }
            }
            if ($isSort) {
                $tags = $this->sortTags($tags);
            }

            // Insert config-based special variables if needed (ROOT_URL, etc.)
            $data['ROOT_URL'] = $this->rootUrl ?? '';

            // Process each tag
            $seenTags = []; #tag => true
            foreach ($tags as $tagFull) {
                if (isset($seenTags[$tagFull])) {
                    continue;
                }
                $seenTags[$tagFull] = true;

                $content = $this->processTag($content, $tagFull, $data, $templateName, $parentData);
            }
        } // else no tags found - nothing to parse

        return $content;
    }

    /**
     * Sort tags so that those containing 'inline' or 'sub' attributes go first.
     * This ensures inline and sub-templates get parsed at the correct time.
     */
    private function sortTags(array $tags): array {
        $inlineSubTags = [];
        $otherTags     = [];
        foreach ($tags as $tag) {
            if (preg_match('/\b(inline|sub)\b/', $tag)) {
                $inlineSubTags[] = $tag;
            } else {
                $otherTags[] = $tag;
            }
        }
        // Merge inline/sub tags first, then the rest
        return array_merge($inlineSubTags, $otherTags);
    }

    private function getGlobals(): array {
        if (is_array($this->globals)) {
            return $this->globals;
        } elseif (is_callable($this->globals)) {
            return call_user_func($this->globals);
        }
        return [];
    }

    /**
     * Process a single tag (e.g. <~tagName attr1="..." attr2="...">)
     *
     * @param string $content Template content
     * @param string $tagFull Full text between <~ and >
     * @param array $data key/value of data for substitutions
     * @param string $templateName Current template name
     * @param array|null $parentData Parent data (for 'parent' references)
     * @return string                    Updated template content with tag replaced
     */
    private function processTag(string $content, string $tagFull, array $data, string $templateName, ?array $parentData): string {
        #$this->logger("DEBUG", "Processing tag: $tagFull");
        [$tagName, $attributes] = $this->parseTagAttributes($tagFull);

        // If conditions not met (if/unless/ifeq/ifne/etc), remove entire tag content
        if (!$this->ifProcessTag($attributes, $data)) {
            #$this->logger("DEBUG", "Skipping tag [$tagName] due to if/unless condition");
            return $this->replaceTagRaw($content, $tagFull, $tagName, '', isset($attributes['inline']));
        }

        // Retrieve the variable value for this tag (unless specialized logic below)
        if ($attributes) {
            if (isset($attributes['global'])) {
                // If it's a global var
                $tagValue = $this->getVariableValue($tagName, $this->getGlobals());
            } elseif (isset($attributes['session'])) {
                // If it's a session var
                $tagValue = $this->getVariableValue($tagName, $_SESSION);
            } elseif (isset($attributes['parent']) && is_array($parentData)) {
                // Use parent data
                $tagValue = $this->getVariableValue($tagName, $parentData);
            } else {
                // Default: get from local data
                $tagValue = $this->getVariableValue($tagName, $data, $parentData);
            }
        } else {
            $tagValue = $this->getVariableValue($tagName, $data, $parentData); #no attributes - try to get value from data by tagName
        }

        // start working with tag value
        // Now handle specialized attributes: repeat, sub, select, radio, selvalue, inline or just variable
        if (isset($attributes['repeat'])) {
            // repeat array
            return $this->processRepeatTag($content, $tagFull, $tagName, $attributes, $tagValue, $data, $templateName);
        } elseif (isset($attributes['select'])) {
            // <select> options
            return $this->processSelectTag($content, $tagFull, $tagName, $attributes, $data);
        } elseif (isset($attributes['radio'])) {
            // <radio> set
            return $this->processRadioTag($content, $tagFull, $tagName, $attributes, $data);
        } elseif (isset($attributes['selvalue'])) {
            // show selected label
            return $this->processSelvalueTag($content, $tagFull, $tagName, $attributes, $data);
        } elseif (isset($attributes['noparse'])) {
            // just include file as is
            return $this->processNoParseTag($content, $tagFull, $tagName, $attributes, $data, $templateName);
        } elseif (isset($attributes['sub'])) {
            // sub-template
            return $this->processSubTemplate($content, $tagFull, $tagName, $attributes, $tagValue, $data, $parentData, $templateName);
        } else {
            // normal variable with possible inline subtemplate
            return $this->processVariableTag($content, $tagFull, $tagName, $attributes, $tagValue, $data, $parentData, $templateName);
        }
    }

    /**
     * Extract the tag name and its attributes from a "tagFull" string (e.g. `tagName attr1="..." attr2="..."`)
     *
     * @param string $tagFull
     * @return array [tagName, attributes[]]  attributes is an associative array of key/value pairs
     */
    private function parseTagAttributes(string $tagFull): array {
        // Split into tokens by whitespace, first token is tag name
        if (!preg_match("/\s/", $tagFull)) {
            // No attributes
            return [$tagFull, []];
        }

        // Detailed attribute extraction
        // <abcd attr1="aa a" attr2='b bb' attr3=ccc attr4> => abcd, attr1="aa a", attr2='b bb', attr3=ccc, attr4
        preg_match_all("/(\S+=\".*?\"|\S+='.*?'|[^'\"\s]+|\S+=\S*)/", $tagFull, $matches, PREG_PATTERN_ORDER);

        $tokens     = $matches[1];
        $tagName    = array_shift($tokens);
        $attributes = [];

        foreach ($tokens as $token) {
            if (preg_match("/(\S+)=\"([^\"]*)\"|(\S+)=(\S*)|(\S+)='([^']*)'/", $token, $m)) {
                // attribute="value" or attribute='value' or attribute=value
                // we combine captures so that name is in $m[1 or 3 or 5], value in $m[2 or 4 or 6]
                $name              = $m[1] ?: ($m[3] ?? $m[5] ?? '');
                $value             = $m[2] ?: ($m[4] ?? $m[6] ?? '');
                $attributes[$name] = $value;
            } else {
                // attribute with no value
                $attributes[$token] = "";
            }
        }

        #$this->logger("DEBUG", "Parsed tag [$tagName] attributes: ", $attributes);
        return [$tagName, $attributes];
    }

    /**
     * Determine if a tag should be processed or skipped based on if/unless/ifeq/etc.
     *
     * @param array $attributes Tag attributes
     * @param array $data Data array
     * @return bool  True if we should process the tag content, false if we skip it
     */
    private function ifProcessTag(array $attributes, array $data): bool {
        // If there is no condition attribute, then show by default
        if (!$attributes) {
            return true;
        }

        $op = '';
        foreach ($this->parsePageOps as $ppop) {
            if (isset($attributes[$ppop])) {
                $op = $ppop;
                break;
            }
        }
        if ($op === '') {
            return true;
        }

        // Evaluate condition
        $varName = $attributes[$op] ?? '';
        $varVal  = $this->getVariableValue($varName, $data);

        // If "value"/"vvalue" is present, compare to that
        $compareVal = null;
        if (isset($attributes['value'])) {
            $compareVal = $attributes['value'];
        } elseif (isset($attributes['vvalue'])) {
            $compareVal = $this->getVariableValue($attributes['vvalue'], $data);
        }

        //use compareVal to detect type of comparison because compare val is in templates, while varVal can be any type from data
        if (is_numeric($compareVal)) {
            // if variable is numeric - do numeric comparison
            $compareVal = (float)$compareVal;
            if ($varVal !== null) {
                $varVal = is_scalar($varVal) ? (float)$varVal : !empty($varVal); #if array - assume true value if not empty
            }
        } else {
            // if variable is string - do string comparison
            $compareVal = is_scalar($compareVal) ? (string)$compareVal : !empty($compareVal); #if array - assume true value if not empty
            if ($varVal !== null) {
                $varVal = is_scalar($varVal) ? (string)$varVal : !empty($varVal); #if array - assume true value if not empty
            }
        }

        #$this->logger("DEBUG", "Evaluating condition: {$op} {$varName}=[{$varVal}] <=> [{$compareVal}]");
        switch ($op) {
            case 'if':
                // Show if varVal is "truthy": not empty, not '0', not false
                if (!$this->isTruthy($varVal)) {
                    return false;
                }
                break;
            case 'unless':
                // Show if varVal is NOT truthy
                if ($this->isTruthy($varVal)) {
                    return false;
                }
                break;
            case 'ifeq':
                if ($varVal !== $compareVal) {
                    return false;
                }
                break;
            case 'ifne':
                if ($varVal === $compareVal) {
                    return false;
                }
                break;
            case 'ifgt':
                if ($varVal <= $compareVal) {
                    return false;
                }
                break;
            case 'iflt':
                if ($varVal >= $compareVal) {
                    return false;
                }
                break;
            case 'ifge':
                if ($varVal < $compareVal) {
                    return false;
                }
                break;
            case 'ifle':
                if ($varVal > $compareVal) {
                    return false;
                }
                break;
        }

        return true;
    }

    /**
     * Determine if a variable value is "truthy" in the sense used by the parser.
     *
     * Truthy: non-empty string not equal to '0', non-zero number, true boolean, etc.
     * Falsy: '0', 0, false, '', or unset
     */
    private function isTruthy($val): bool {
        return boolval($val);
        // explicit version for reference:
        //        if (is_null($val)) {
        //            return false;
        //        } elseif (is_bool($val)) {
        //            return $val;
        //        } elseif (is_numeric($val)) {
        //            // 0 is falsey, anything else is truthy
        //            return ($val != 0);
        //        } else {
        //            // For strings, '' or '0' are falsey
        //            $valStr = (string)$val;
        //            if ($valStr === '' || $valStr === '0') {
        //                return false;
        //            }
        //            return true;
        //        }
    }

    /**
     * Retrieve a variable value from a data array, possibly nested (e.g., var[a][b]), or from parent data.
     *
     * Also handles special references to "PARSEPAGE.TOP", "PARSEPAGE.PARENT", "GLOBAL", "SESSION" if needed.
     *
     * @param string $tagName
     * @param array|null $data
     * @param array|null $parentData
     * @return mixed The variable value or null if not found
     */
    private function getVariableValue(string $tagName, array $data = null, array $parentData = null): mixed {
        if ($tagName === '' || is_null($data)) {
            return null;
        }

        // Check if the tagName includes array syntax (e.g. "somevar[a][b]")
        if (str_contains($tagName, '[')) {
            // e.g. "somevar[a][b]"
            // Split at '[' but keep keys
            $segments = explode('[', $tagName);
            $root     = strtoupper($segments[0]);
            $ptr      = $data;

            // If root is GLOBAL, SESSION, PARSEPAGE.TOP, PARSEPAGE.PARENT, override pointer
            if ($root === 'GLOBAL') {
                $ptr = $this->getGlobals();
                array_shift($segments);
            } elseif ($root === 'SESSION') {
                $ptr = $_SESSION;
                array_shift($segments);
            } elseif ($root === 'PARSEPAGE.TOP') {
                $ptr = $this->dataTop;
                array_shift($segments);
            } elseif ($root === 'PARSEPAGE.PARENT' && $parentData) {
                $ptr = $parentData;
                array_shift($segments);
            }

            foreach ($segments as $s) {
                $key = rtrim($s, ']'); // remove trailing ]
                if (is_array($ptr) && array_key_exists($key, $ptr)) {
                    $ptr = $ptr[$key];
                } elseif (is_object($ptr) && property_exists($ptr, $key)) {
                    $ptr = $ptr->$key;
                } else {
                    return null; #looks like there are just no such key in array OR $ptr is not an array at all - so return empty value
                }
            }
            return $ptr;
        } else {
            // Single-level key
            return $data[$tagName] ?? null;
        }
    }

    /**************************************************************************
     * Repeat (Loop) Support
     **************************************************************************/

    /**
     * Handle a repeat tag that loops over an array and processes subtemplates or inline content.
     */
    private function processRepeatTag(string $content, string $tagFull, string $tagName, array $attributes, mixed $tagValue, array $data, string $templateName): string {
        $isInline = isset($attributes['inline']);
        if (!is_array($tagValue)) {
            // Not an array, just remove it
            return $this->replaceTagRaw($content, $tagFull, $tagName, '', $isInline);
        }

        // load inline template if 'inline' attribute is present
        $inlineTemplate = '';
        if ($isInline) {
            $inlineTemplate = $this->getInlineTemplate($content, $tagFull, $tagName);
        }

        $loopedOutput = '';
        $items        = $tagValue;
        $total        = count($items);
        foreach ($items as $index => $item) {
            // Add repeat.* variables
            $item['repeat.first']     = ($index === 0) ? 1 : 0;
            $item['repeat.last']      = ($index === $total - 1) ? 1 : 0;
            $item['repeat.index']     = $index;
            $item['repeat.iteration'] = $index + 1;
            $item['repeat.total']     = $total;
            $item['repeat.even']      = ($index % 2) ? 1 : 0;
            $item['repeat.odd']       = ($index % 2) ? 0 : 1;

            // Parse template for one item
            $tagTplPath   = $this->getTagTemplatePath($tagName, $templateName, $isInline);
            $loopedOutput .= $this->parseTemplate($tagTplPath, $item, $inlineTemplate, $data, $attributes);
        }

        return $this->replaceTagRaw($content, $tagFull, $tagName, $loopedOutput, $isInline);
    }

    /**
     * Process "noparse" - insert template into content without parsing it
     * @param string $content
     * @param string $tagFull
     * @param string $tagName
     * @param array $attributes
     * @param array $data
     * @param string $templateName
     * @return string
     */
    private function processNoParseTag(string $content, string $tagFull, string $tagName, array $attributes, array $data, string $templateName): string {
        // load file and use it as is
        $filePath = $this->getTagTemplatePath($tagName, $templateName);

        $filePath   = $this->getFullTemplatePath($filePath);
        $tplContent = $this->loadTemplate($filePath);

        return $this->replaceTagRaw($content, $tagFull, $tagName, $tplContent, isset($attributes['inline']));
    }

    /**************************************************************************
     * Sub-Templates (sub="...") and Inline Templates
     **************************************************************************/

    /**
     * Process a subtemplate tag (<~tagName sub> or <~tagName sub="varName">)
     */
    private function processSubTemplate(string $content, string $tagFull, string $tagName, array $attributes, mixed $tagValue, array $data, ?array $parentData, string $templateName = ''): string {
        // If sub="somekey", use that data instead of tagValue
        if (!empty($attributes['sub'])) {
            $subData = $this->getVariableValue($attributes['sub'], $data, $parentData);
        } else {
            $subData = $tagValue; // default
        }

        if (!is_array($subData)) {
            $subData = [];
        }

        #$this->logger("DEBUG", "Processing subtemplate: $tagFull", $subData);

        // Potentially load inline template if 'inline' is also present
        $isInline       = isset($attributes['inline']);
        $inlineTemplate = '';
        if ($isInline) {
            $inlineTemplate = $this->getInlineTemplate($content, $tagFull, $tagName);
        }

        // Parse the subtemplate or inline
        $tagTplPath = $this->getTagTemplatePath($tagName, $templateName, $isInline);
        $subOutput  = $this->parseTemplate($tagTplPath, $subData, $inlineTemplate, $data, $attributes);

        return $this->replaceTagRaw($content, $tagFull, $tagName, $subOutput, $isInline);
    }

    /**
     * Extract inline template content from the main page if there is `inline` attribute.
     *
     * The inline template is everything between <~tagFull> and </~tagName>.
     */
    private function getInlineTemplate(string $content, string $tagFull, string $tagName): string {
        // Build a regex to capture everything between <~tagFull> and </~tagName>
        $regex = '/' . $this->openTagQuoted . preg_quote($tagFull, '/') . $this->closeTagQuoted . '(.*?)' . $this->openEndTagQuoted . preg_quote($tagName, '/') . $this->closeTagQuoted . '/si';

        if (preg_match($regex, $content, $m)) {
            return $m[1];
        } else {
            return '';
        }
    }

    /**
     * Resolve the full template path - if template name is not absolute (starting with "/"), prepend baseDir
     *
     * @param string $templateName
     * @return string
     */
    private function getFullTemplatePath(string $templateName): string {
        if ($templateName > '' && !preg_match('/^\//', $templateName)) {
            $templateName = $this->baseDir . '/' . ltrim($templateName, '/');
        }
        return $templateName;
    }

    /**
     * Return template path for the tag with respect to the current template path.
     *
     * @param string $tagName
     * @param string $templateName
     * @param bool $isInline
     * @return string
     */
    private function getTagTemplatePath(string $tagName, string $templateName, bool $isInline = false): string {
        $addPath = '';
        $result  = $tagName;

        if (str_starts_with($tagName, './') || $isInline) {
            $addPath = $templateName;
            $addPath = preg_replace("/[^\/]+$/", '', $addPath); //delete file name
            $result  = preg_replace("/^\.\//", '', $result); //delete ./ at the begin
        }

        $result = $addPath . $result;

        if (!preg_match("/\.[^\/]+$/", $tagName)) {
            $result .= ".html"; // set default .html extension (if extension is not set)
        }

        return $result;
    }

    /**************************************************************************
     * Specialized Tag Handlers (select, radio, selvalue)
     **************************************************************************/

    /**
     * Generate <option> tags for <select> from either an array or a .sel file.
     */
    private function processSelectTag(string $content, string $tagFull, string $tagName, array $attributes, array $data): string {
        $selectedValue = $this->getVariableValue($attributes['select'], $data);
        $optionsHtml   = $this->buildOptionsHtml($tagName, $data, $selectedValue, !isset($attributes['noescape']));

        // Replace in main content
        return $this->replaceTagRaw($content, $tagFull, $tagName, $optionsHtml);
    }

    /**
     * Generate radio inputs from .sel file or array, similar to select but with radio buttons.
     */
    private function processRadioTag(string $content, string $tagFull, string $tagName, array $attributes, array $data): string {
        $selectedValue = $this->getVariableValue($attributes['radio'], $data);
        $name          = $attributes['name'] ?? $tagName;
        $delim         = $attributes['delim'] ?? '';
        $options       = $this->loadOptions($tagName, $data);

        $html = '';
        $i    = 0;
        foreach ($options as $value => $label) {
            // Possibly escape if noescape not set
            if (!isset($attributes['noescape'])) {
                $value = $this->htmlescape($value);
                $label = $this->htmlescape($label);
            }

            $checked = ((string)$value === (string)$selectedValue) ? " checked='checked'" : '';
            // Using Bootstrap4 custom-control style as in original
            $html .= "<div class='custom-control custom-radio $delim'>"
                . "<input class='custom-control-input' type='radio' id=\"$name\$$i\" name=\"$name\" value=\"$value\"$checked>"
                . "<label class='custom-control-label' for=\"$name\$$i\">$label</label>"
                . "</div>";
            $i++;
        }

        return $this->replaceTagRaw($content, $tagFull, $tagName, $html);
    }

    /**
     * Show the selected label from .sel file or array (like a read-only version of select/radio).
     */
    private function processSelvalueTag(string $content, string $tagFull, string $tagName, array $attributes, array $data): string {
        $selectedValue = $this->getVariableValue($attributes['selvalue'], $data);
        $options       = $this->loadOptions($tagName, $data);

        $label = $options[$selectedValue] ?? '';
        if (!isset($attributes['noescape'])) {
            $label = $this->htmlescape($label);
        }

        return $this->replaceTagRaw($content, $tagFull, $tagName, $label);
    }

    /**
     * Build <option>... for a select. Shared logic for array-based or .sel-file-based data.
     */
    private function buildOptionsHtml(string $tagName, array $data, mixed $selectedValue, bool $escape = true): string {
        $options = $this->loadOptions($tagName, $data);
        $html    = '';
        foreach ($options as $value => $label) {
            $selectedAttr = ((string)$value === (string)$selectedValue) ? ' selected' : '';
            if ($escape) {
                $value = $this->htmlescape($value);
                $label = $this->htmlescape($label);
            }
            $html .= "<option value=\"$value\"$selectedAttr>$label</option>\n";
        }
        return $html;
    }

    /**
     * Load option data from a .sel file or from $data[$tagName] if it's an array of items with id/iname.
     */
    private function loadOptions(string $tagName, array $data): array {
        // 1) If $data[$tagName] is an array of assoc arrays with 'id'/'iname', use that
        if (isset($data[$tagName]) && is_array($data[$tagName])) {
            $options = [];
            foreach ($data[$tagName] as $item) {
                $val   = $item['id'] ?? $item['iname'];
                $label = $item['iname'];
                // Optionally parse language and do partial parse if needed
                $this->parseLanguage($val);
                $this->parseLanguage($label);
                $options[$val] = $label;
            }
            return $options;
        }

        // 2) Otherwise, look for a .sel file
        //    e.g. if $tagName = "options.sel" or "options" => final path = "$baseDir/options.sel"
        //    or "SITE_TEMPLATES/$baseDir/options" if we add .sel ourselves
        $filePath = $tagName;
        if (!preg_match('/\.\w+$/', $tagName)) {
            // If no extension, assume .sel
            $filePath .= '.sel';
        }
        if (!preg_match('/^\//', $filePath)) {
            $filePath = $this->baseDir . '/' . $filePath;
        }

        $fileContent = $this->loadTemplate($filePath);
        if (!$fileContent) {
            return [];
        }

        $options = [];
        $lines   = preg_split("/[\r\n]+/", $fileContent);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode('|', $line);
            if (count($parts) >= 2) {
                $val   = trim($parts[0]);
                $label = trim($parts[1]);
                // Possibly parse language
                $this->parseLanguage($val);
                $this->parseLanguage($label);
                $options[$val] = $label;
            }
        }
        return $options;
    }

    /**************************************************************************
     * Variable Tag Handler (including inline template if any)
     **************************************************************************/

    /**
     * Process a normal variable tag, applying any modifiers, or using inline template if specified.
     */
    private function processVariableTag(string $content, string $tagFull, string $tagName, array $attributes, mixed $tagValue, ?array $data, ?array $parentData, string $templateName = ''): string {
        #$this->logger("DEBUG", "Processing variable tag: $tagName = ", $tagValue);
        // If 'var' is explicitly set and there's no value, short-circuit - replace with empty string
        if (isset($attributes['var']) && is_null($tagValue)) {
            return $this->replaceTagRaw($content, $tagFull, $tagName, '');
        }

        if (isset($attributes['inline'])) {
            // extract and parse inline template
            $inlineTemplate = $this->getInlineTemplate($content, $tagFull, $tagName);

            $tagTplPath    = $this->getTagTemplatePath($tagName, $templateName, true);
            $inlineContent = $this->parseTemplate($tagTplPath, $data, $inlineTemplate, $parentData, $attributes);
            return $this->replaceTagRaw($content, $tagFull, $tagName, $inlineContent, true);
        }

        // now check if we have a value to replace
        if (!is_null($tagValue)) {
            // Apply additional modifiers (date, truncate, default, etc.)
            // And by default, we escape normal variables if "noescape" is not set
            $tagValue = $this->applyModifiers($tagValue, $attributes, true);
        } else {
            // Null variable => then tag name could be a template name to include
            // but no need for file seek if it contains "[" (array syntax) or starts with "#" (comment)
            $tagValue = '';
            if (!str_starts_with($tagName, '#') && !str_contains($tagName, '[')) {
                // Try to load a template file with the tag name
                $tagTplPath = $this->getTagTemplatePath($tagName, $templateName);
                $tagValue   = $this->parseTemplate($tagTplPath, $data, '', $parentData, $attributes);
            }

            // Apply additional modifiers (date, truncate, default, etc.)
            $tagValue = $this->applyModifiers($tagValue, $attributes);
        }

        return $this->replaceTagRaw($content, $tagFull, $tagName, $tagValue);
    }

    /**
     * Apply modifiers to a scalar or array value (date, truncate, etc.)
     * @param $value
     * @param $attributes
     * @param bool $is_htmlescape if true and no "noescape" attribute present - additionally htmlescape the value
     * @return array|bool|float|mixed|string|string[]|null
     */
    private function applyModifiers($value, $attributes, bool $is_htmlescape = false): mixed {
        if (!$attributes) {
            if ($is_htmlescape) {
                $value = $this->htmlescape($value);
            }
            return $value;
        }

        #$this->logger("DEBUG", "Applying modifiers to value: ", $value, $attributes);

        // If it's not scalar but "json" is requested, we handle that
        if (!is_scalar($value)) {
            if (isset($attributes['json'])) {
                // If "json=pretty" => pretty print
                $options = (strtolower($attributes['json']) === 'pretty') ? JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK : JSON_NUMERIC_CHECK;
                $result  = json_encode($value, $options);
                if ($is_htmlescape && !isset($attributes['noescape'])) {
                    $result = $this->htmlescape($result);
                }
                return $result;
            }
            // Non-scalar but no json => default to empty
            return '';
        }

        // number_format
        if (isset($attributes['number_format'])) {
            $value = $this->applyNumberFormat($value, $attributes);
        }

        // currency
        if (isset($attributes['currency'])) {
            $currency = $attributes['currency'] ?: "USD";
            $fmt      = new NumberFormatter("en_US", NumberFormatter::CURRENCY);
            $value    = $fmt->formatCurrency($value, $currency);
        }

        // string_format or sprintf
        if (isset($attributes['string_format'])) {
            $value = sprintf($attributes['string_format'], $value);
        }
        if (isset($attributes['sprintf'])) {
            $value = sprintf($attributes['sprintf'], $value);
        }

        // htmlescape
        if (isset($attributes['htmlescape'])) {
            $value = $this->htmlescape($value);
        }

        // date
        if (isset($attributes['date'])) {
            $value = $this->applyDateFormat($value, $attributes['date']);
        }

        // truncate
        if (isset($attributes['truncate'])) {
            $maxLen = (int)($attributes['truncate']);
            if ($maxLen > 0) {
                $value = mb_substr($value, 0, $maxLen);
            }
        }

        // strip_tags
        if (isset($attributes['strip_tags'])) {
            $value = strip_tags($value);
        }

        // trim
        if (isset($attributes['trim'])) {
            $value = trim($value);
        }

        // count
        if (isset($attributes['count'])) {
            // if $value is something countable, use count
            $value = (is_array($value)) ? count($value) : 0;
        }

        // lower
        if (isset($attributes['lower'])) {
            $value = mb_strtolower($value);
        }

        // upper
        if (isset($attributes['upper'])) {
            $value = mb_strtoupper($value);
        }

        // default
        if (isset($attributes['default']) && (string)$value === '') {
            $value = $attributes['default'];
        }

        // urlencode
        if (isset($attributes['urlencode'])) {
            $value = urlencode($value);
        }

        // url
        if (isset($attributes['url'])) {
            if (!preg_match('!^\w+://!', $value)) {
                $value = 'https://' . $value;
            }
        }

        // json (last pass, if the user wants to re-encode)
        if (isset($attributes['json'])) {
            // Re-encode if this is a scalar?
            $options = (strtolower($attributes['json']) === 'pretty') ? JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK : JSON_NUMERIC_CHECK;
            $value   = json_encode($value, $options);
        }

        if ($is_htmlescape && !isset($attributes['noescape'])) {
            $value = $this->htmlescape($value);
        }

        // nl2br - do after htmlescape to avoid double-escaping
        if (isset($attributes['nl2br'])) {
            $value = nl2br($value);
        }

        return $value;
    }

    /**
     * number_format attribute handling
     */
    private function applyNumberFormat($value, $attributes) {
        if ($value === '') {
            return '';
        }
        if (!is_numeric($value)) {
            return $value;
        }
        $nfdecimals  = (int)($attributes['number_format'] ?? 2);
        $nfpoint     = $attributes['nfpoint'] ?? '.';
        $nfthousands = $attributes['nfthousands'] ?? ' ';
        return number_format($value, $nfdecimals, $nfpoint, $nfthousands);
    }

    /**
     * Convert a time/string into a date with a given format
     */
    private function applyDateFormat($value, string $format): string {
        if (!$value) {
            return '';
        }

        // recognized short codes: short, long, sql
        switch (strtolower($format)) {
            case '':
                $format = 'm/d/Y';
                break;
            case 'short':
                $format = 'm/d/Y H:i';
                break;
            case 'long':
                $format = 'm/d/Y H:i:s';
                break;
            case 'sql':
                $format = 'Y-m-d H:i:s';
                break;
            default:
                // use the raw $format if it's a valid date() format
                break;
        }

        // If value is '0000-00-00', '0000-00-00 00:00:00', skip
        if ($value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '';
        }

        // If not numeric, parse as datetime
        if (!preg_match('/^\d+$/', $value)) {
            $value = strtotime($value);
        }

        return date($format, (int)$value);
    }

    /**************************************************************************
     * Replace Tag Logic
     **************************************************************************/

    /**
     * Replace all tag occurrences tag (e.g. <~tagFull>) with a raw value. If inline is true, we remove entire block from <~tagFull> to </~tagName>.
     *
     * @param string $content
     * @param string $tagFull
     * @param string $tagName
     * @param string $replacement
     * @param bool $isInline
     * @return string
     */
    private function replaceTagRaw(string $content, string $tagFull, string $tagName, string $replacement, bool $isInline = false): string {
        if ($isInline) {
            // If it's an inline block, remove everything between open and close
            $regex = '/' . $this->openTagQuoted . preg_quote($tagFull, '/') . $this->closeTagQuoted . '.*?' . $this->openEndTagQuoted . preg_quote($tagName, '/') . $this->closeTagQuoted . '/si';

            // Escape special chars to avoid backrefs messing up - order is important
            $replacement = str_replace(['\\', '$'], ['\\\\', '\\$'], $replacement);
            return preg_replace($regex, $replacement, $content);
        } else {
            // Simple replacement of <~tagFull>
            $search = $this->openTag . $tagFull . $this->closeTag;
            return str_replace($search, $replacement, $content);
        }
    }

    /**************************************************************************
     * Language Parsing
     **************************************************************************/

    /**
     * Parse language strings in a piece of text by replacing `Something` with the localized version.
     *
     * @param string $content modified by reference
     * @return void
     */
    public function parseLanguage(string &$content): void {
        if (!$this->langParse || !str_contains($content, '`')) {
            return;
        }

        // Replace all backtick-enclosed text with translations
        $content = preg_replace_callback('/`([^`]*)`/', function ($m) {
            return $this->translate($m[1]);
        }, $content);
    }

    /**
     * Translate a string from the loaded language file(s).
     *
     * @param string $str
     * @return string
     */
    private function translate(string $str): string {
        if (!is_null($this->languageCallback)) {
            return call_user_func($this->languageCallback, $str);
        }

        $langFile = $this->templatesRoot . $this->languageDir . "/$this->language.txt";

        if (!isset(self::$langCacheGlobal[$langFile])) {
            // Load or create empty if no file
            self::$langCacheGlobal[$langFile] = $this->loadLanguageFile($langFile);
        }

        // If there's a translation, return it; otherwise the original
        $dict = self::$langCacheGlobal[$langFile];
        if (isset($dict[$str]) && $dict[$str] !== '') {
            return $dict[$str];
        } else {
            // Not found - possibly auto-update
            if ($this->isLanguageUpdate) {
                $this->updateLanguage($langFile, $str);
            }
            // Return original string
            return $str;
        }
    }

    /**
     * Load language file and parse lines in the format:  original === translation
     */
    private function loadLanguageFile(string $langFile): array {
        $dict = [];
        if (file_exists($langFile)) {
            $lines = file($langFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_contains($line, '===')) {
                    // "key === value"
                    [$key, $val] = explode('===', $line, 2);
                    $key        = trim($key);
                    $val        = trim($val);
                    $dict[$key] = $val;
                }
            }
        }
        return $dict;
    }

    /**
     * Appends a missing translation key to the .txt file
     * in "KEY === " format
     *
     * @param string $langFile
     * @param string $missingKey
     * @return void
     */
    private function updateLanguage(string $langFile, string $missingKey): void {
        // Make sure it’s not already in our dict
        if (isset(self::$langCacheGlobal[$langFile][$missingKey])) {
            return;
        }

        self::$langCacheGlobal[$langFile][$missingKey] = '';
        $this->logger('TRACE', "ParsePage - auto-adding missing language string [$missingKey] to $langFile");
        // Append to file
        // In a real system, do file-locking or something safer
        $line = $missingKey . " === \n";
        @file_put_contents($langFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**************************************************************************
     * Template & File Loading, Output
     **************************************************************************/

    /**
     * Load a template file relative to templatesRoot, with caching.
     *
     * @param string $filename
     * @return string
     * @throws Exception if file is not found and $isDieOnError=true
     */
    private function loadTemplate(string $filename): string {
        if (isset($this->fileCache[$filename])) {
            #$this->logger("DEBUG", "cache hit $filename");
            return $this->fileCache[$filename];
        }

        // if (filename contains a mask - load all files via recursive calls and return as a single template
        if (str_contains($filename, '*')) {
            $folder    = dirname($filename);
            $mask      = basename($filename);
            $path_mask = $this->templatesRoot . $folder . '/' . $mask;
            $files     = glob($path_mask);
            $sb        = '';
            foreach ($files as $file) {
                //strip off the templatesRoot
                $file = str_replace($this->templatesRoot, '', $file);
                $sb   .= $this->loadTemplate($file);
            }
            return $sb;
        }

        #$this->logger("DEBUG", "loading file $filename");
        $full_path = $this->templatesRoot . $filename;

        #$this->logger("DEBUG", "loadTemplate full path: $full_path");
        if (file_exists($full_path)) {
            $content                    = file_get_contents($full_path);
            $this->fileCache[$filename] = $content;
            return $content;
        } else {
            $msg = "ParsePage - cannot open template file [$filename]";
            $this->logger('TRACE', $msg);
            return ''; // it's ok if no template found, just use empty string then
        }
    }

    /**************************************************************************
     * Helper: HTML Escape
     **************************************************************************/

    /**
     * Safely escape HTML (similar to htmlspecialchars).
     */
    private function htmlescape(mixed $str): string {
        if (!is_scalar($str)) {
            return '';
        }
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * logger, calls external global logger()
     *
     * @param string $log_type 'ERROR'|'DEBUG'|'NOTICE'|'INFO'
     * @param mixed $value value to log
     * @param mixed $value2 optional second value (usually params for param query)
     * @return void
     */
    private function logger(string $log_type, mixed $value, mixed $value2 = null): void {
        if (!function_exists('logger')) {
            return;
        }

        #do it separately depending if $value2 set for cleaner logs
        if (is_null($value2)) {
            logger($log_type, $value);
        } else {
            logger($log_type, $value, $value2);
        }
    }
}

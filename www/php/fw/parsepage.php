<?php
/*
ParsePage - Site Template Parser

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com

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
2013-02-23 - vvalue="abc" - actual value got via hfvalue('abc', $hf);
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
 */
require_once dirname(__FILE__) . "/lock.php";

/*
##########################################################
Universal Page parser
ATTENTION! this version of parse_page assumes all templates under $CONFIG['SITE_TEMPLATES'] dir, so root path is $CONFIG['SITE_TEMPLATES']
mode autodetect $out_filename='' or 's' - variable/screen, >'' - to file $out_filename
tags in templates should be in <~name [attributes]> format
<~var[aaa][0][bbb]>  - also supported

# DEPRECATED:
# if="php expression" - tag/template will be parsed only if expression is true, notes:
#    vars from $hf can be used as $var_name
#    > comparison sign MUST be written as &gt; (for faster tag parsing)
#    example: <~aaa if="$bbb < 100"> <~ccc if="$bbb &gt; 100"> <~xxx if="$yyy eq $zzz">

# Supported attributes:

var - tag is variable, no fileseek necessary
ifXX - if confitions
ifeq="var" value="XXX" - tag/template will be parsed only if var=XXX
ifne="var" value="XXX" - tag/template will be parsed only if var!=XXX
ifgt="var" value="XXX" - tag/template will be parsed only if var>XXX
ifge="var" value="XXX" - tag/template will be parsed only if var>=XXX
iflt="var" value="XXX" - tag/template will be parsed only if var<XXX
ifle="var" value="XXX" - tag/template will be parsed only if var<=XXX

## old mapping
neq => ne
ge => gt
le => lt
gee => ge
lee => le

vvalue - value as hf variable:
<~tag ifeq="var" vvalue="YYY"> - actual value got via hfvalue('YYY', $hf);

#shortcuts
<~tag if="var"> - tag will be shown if var is evaluated as TRUE, not using eval(), equivalent to "if ($var)"
<~tag unless="var"> - tag will be shown if var is evaluated as TRUE, not using eval(), equivalent to "if (!$var)"
-------------------------
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

repeat - this tag is repeat content ($hf hash should contain reference to array of hashes),
supported repeat vars:
repeat.first (0-not first, 1-first)
repeat.last  (0-not last, 1-last)
repeat.total (total number of items)
repeat.index  (0-based)
repeat.iteration (1-based)

sub - this tag tell parser to use subhash for parse subtemplate ($hf hash should contain reference to hash), examples:
<~tag sub inline>...</~tag>- use $hf[tag] as hashtable for inline template
<~tag sub="var"> - use $hf[var] as hashtable for template in "tag.html"
inline - this tag tell parser that subtemplate is not in file - it's between <~tag>...</~tag> , useful in combination with 'repeat' and 'if'
global - this tag is a global var, not in $hf hash
global[var] - also possible
session - this tag is a $_SESSION var, not in $hf hash
session[var] - also possible
parent - this tag is a $parent_hf var, not in current $hf hash
select="var" - this tag tell parser to either load file with tag name and use it as value|display for <select> tag
or if variable with tag name exists - use it as arraylist of hashtables with id/iname keys
, example:
<select name="item[fcombo]">
<option value=""> - select -
<~./fcombo.sel select="fcombo">  or <~fcombo_options select="fcombo">
</select>
radio="var" name="YYY" [delim="CLASSNAM"]- this tag tell parser to load file and use it as value|display for <input type=radio> tags, example:
<~./fradio.sel radio="fradio" name="item[fradio]" delim="custom-control-inline">
selvalue="var" - display value (fetched from the .sel file) for the var (example: to display 'select' and 'radio' values in List view)
ex: <~../fcombo.sel selvalue="fcombo">  or <~fcombo_options selvalue="fcombo">
nolang - for subtemplates - use default language instead of current (usually english)
htmlescape - replace special symbols by their html equivalents (such as <>,",')

support `text` => replaced by multilang from $CONFIG['SITE_TEMPLATES']/lang/$lang.txt according to $GLOBAL['lang'] (if !='' - english by default)
example: <b>`Hello`</b>
lang.txt line format:
english string === lang string

support modifiers:
htmlescape
date          - format as datetime, sample "d M Y H:i", see http://php.net/manual/en/function.date.php
<~var date>         output "m/d/Y" - date only (TODO - should be formatted per user settings actually)
<~var date="short"> output "m/d/Y H:i" - date and time short (to mins)
<~var date="long">  output "m/d/Y H:i:s" - date and time long
<~var date="sql">   output "Y-m-d H:i:s" - sql date and time
truncate
strip_tags
trim
nl2br
count
lower
upper
default
urlencode
json (was var2js) - produces json-compatible string, example: {success:true, msg:""}
 */

// !!!RECURSIVE
// $page - if set - contains page contents (no need to read file!), $tpl_name in this case is just for helping determine relative dirs
$PARSE_PAGE_OPS = array('if', 'unless', 'ifeq', 'ifne', 'ifgt', 'iflt', 'ifge', 'ifle');

#open/close tags, must be regexp compatible!
$PARSE_PAGE_OPEN_TAG    = '<~';
$PARSE_PAGE_CLOSE_TAG   = '>';
$PARSE_PAGE_OPENEND_TAG = '</~'; #open for end inline tag

$PARSE_PAGE_LANG_PARSE = 1; #parse lang strings in `` or not - 1 - parse(default), 0 - no

$PARSE_PAGE_DATA_TOP = array(); #used fro PARSEPAGE.TOP

function parse_page($basedir, $tpl_name, $hf, $out_filename = '') {
    $GLOBALS['PARSE_PAGE_DATA_TOP'] = &$hf;

    $page = _parse_page($basedir, $tpl_name, $hf);

    if ($out_filename && $out_filename != 'v' && $out_filename != 's') {
        $outdir = dirname($out_filename); //check dir
        if (!is_dir($outdir)) {
            mkdir($outdir, 0777);
        }

        $OUTFILE = fopen($out_filename, "w") or die("Can't open out [$out_filename] file");
        fputs($OUTFILE, $page);
        fclose($OUTFILE);
    } else {
        if ($out_filename == 'v') { #variable mode
            return $page;
        } else { #screen mode
            print_header();
            print $page; #no XSS here intended to output html
        }
    }
}

function _parse_page($basedir, $tpl_name, $hf, $page = '', $parent_hf = 0) {
    global $PARSE_PAGE_OPEN_TAG, $PARSE_PAGE_CLOSE_TAG;
    global $CONFIG;

    //make path 'absolute'
    if (!preg_match("/^\//", $tpl_name)) {
        $tpl_name = "$basedir/$tpl_name";
    }

    #logger('TRACE', "parsing $tpl_name");

    if (!$page) {
        //load template
        $page = precache_file($CONFIG['SITE_TEMPLATES'] . $tpl_name);
    }

    if ($page) { //if template empty - don't parse it

        parse_lang($page);

        //get all tags with attributes needed to be filled
        preg_match_all("/$PARSE_PAGE_OPEN_TAG([^$PARSE_PAGE_CLOSE_TAG]+)$PARSE_PAGE_CLOSE_TAG/", $page, $out, PREG_PATTERN_ORDER);
        $tags_full = $out[1];
        //rw("$tpl_name: found ".count($tags_full)." tags");

        if (count($tags_full) > 0) { //if there are no tags found - dont' parse it
            $hf['ROOT_URL'] = $CONFIG['ROOT_URL'];
            // rw("hf=".count($hf)." ");

            //re-sort $tags_full, so all 'inline' and 'sub' tags will be parsed first
            $tags_full = parse_page_sort_tags($tags_full);
            #logger($tags_full);

            $TAGSEEN = array();
            for ($i = 0; $i < count($tags_full); $i++) {
                $tag_full = $tags_full[$i];
                if (array_key_exists($tag_full, $TAGSEEN)) {
                    continue;
                }

                $TAGSEEN[$tag_full] = 1;
                #logger($tag_full);

                $tag   = '';
                $attrs = array();
                if (!preg_match("/\s/", $tag_full)) { //if tag doesn't have attrs - don't parse attrs
                    $tag = $tag_full;
                } else { //now cut attrs  <abcd attr1="aa a" attr2='b bb' attr3=ccc attr4> => abcd, attr1="aa a", attr2='b bb', attr3=ccc, attr4
                    preg_match_all("/((?:\S+\=\".*?\")|(?:\S+\='.*?')|(?:[^'\"\s]+)|(?:\S+\=\S*))/", $tag_full, $out, PREG_PATTERN_ORDER);
                    $attrs_raw = $out[1];
                    //dumparr($attrs_raw);
                    //rw($tag." => ".$attrs_raw);
                    //echo Dumper(@attrs);

                    $tag = $attrs_raw[0];
                    for ($j = 0; $j < count($attrs_raw); $j++) {
                        $attr = $attrs_raw[$j];
                        //rw("search attr [$attr]");
                        if (preg_match("/(?:(\S+)\=\"([^\"]*)\")|(?:(\S+)\=(\S*))|(?:(\S+)\='([^']*)')/", $attr, $match)) { //split attr and it's value
                            $name  = $match[1];
                            $value = $match[2];
                            //rw("attr [$attr] $name => $value");
                            $attrs[$name] = $value;
                        } else {
                            $attrs[$attr] = "";
                        }
                    }
                }
                #echo "tag=$tag_full<br>\n";

                if (tag_if($attrs, $hf)) {
                    //check for inline template and prepare it
                    $inline_tpl    = '';
                    $is_inline_tpl = false;

                    if (count($attrs) > 0) {
                        //check only if there are some attrs
                        if (array_key_exists('inline', $attrs)) {
                            $is_inline_tpl = true;
                            $inline_tpl    = get_inline_tpl($page, $tag, $tag_full);
                            #rw("inline = $inline_tpl");
                        }

                        if (array_key_exists('global', $attrs)) {
                            //get var from GLOBALS, not from $hf
                            $tagvalue = hfvalue($tag, fw::i()->GLOBAL); #was: $GLOBALS
                        } elseif (array_key_exists('session', $attrs)) {
                            //get var from SESSION, not from $hf
                            $tagvalue = hfvalue($tag, $_SESSION);
                        } elseif (array_key_exists('parent', $attrs) && is_array($parent_hf)) {
                            //get var from parent $hf, not from current $hf (for use original $hf in sub and repeat's)
                            $tagvalue = hfvalue($tag, $parent_hf);
                        } else {
                            //get usual var
                            $tagvalue = hfvalue($tag, $hf, $parent_hf);
                        }
                    } else {
                        $tagvalue = hfvalue($tag, $hf, $parent_hf);
                    }

                    #            $attr_lang='';
                    #            if ( array_key_exists('lang', $attrs) ) $attr_lang=$attrs['lang'];

                    //start working with tag value
                    if (!is_null($tagvalue)) {
                        //rw("$tag EXISTS");

                        if (array_key_exists('repeat', $attrs)) { //if this is 'repeat' tag parse as datarow
                            if (is_array($tagvalue)) {
                                $repeat_array = $tagvalue;
                                $tagvalue     = '';
                                $hfcount      = count($repeat_array);
                                $k1           = 0;
                                foreach ($repeat_array as $k => $v) {
                                    $hfrow                     = $v;
                                    $hfrow['repeat.last']      = ($k1 == $hfcount - 1) ? 1 : 0;
                                    $hfrow['repeat.first']     = ($k1 == 0) ? 1 : 0;
                                    $hfrow['repeat.even']      = ($k1 % 2) ? 1 : 0;
                                    $hfrow['repeat.odd']       = ($k1 % 2) ? 0 : 1;
                                    $hfrow['repeat.index']     = $k1;
                                    $hfrow['repeat.iteration'] = $k1 + 1;
                                    $hfrow['repeat.total']     = $hfcount;
                                    $tagvalue                  .= _parse_page($basedir, tag_tplpath($tag, $tpl_name, $is_inline_tpl), $hfrow, $inline_tpl, $hf);
                                    $k1++;
                                }
                            } else {
                                logger('DEBUG', 'ParsePage warning - not an array passed to repeat tag = ' . $tag); #DEBUG cause this dev error happens frequently
                                $tagvalue = '';
                            }
                        } elseif (array_key_exists('select', $attrs)) {
                            #this is special case for '<select>' HTML tag when options passed as array
                            $tagvalue = parse_select_tag($basedir, tag_tplpath($tag, $tpl_name, $is_inline_tpl), $tag, $hf, $attrs);
                        } elseif (array_key_exists('selvalue', $attrs)) {
                            $tagvalue = parse_selvalue_tag($basedir, tag_tplpath($tag, $tpl_name, $is_inline_tpl), $tag, $hf, $attrs);
                            if (!array_key_exists('noescape', $attrs)) {
                                $tagvalue = htmlescape($tagvalue);
                            }
                        } elseif (array_key_exists('sub', $attrs)) { //if this is 'sub' tag - parse it as independent subtemplate
                            $tagvalue = _tag_sub($basedir, tag_tplpath($tag, $tpl_name, $is_inline_tpl), $tag, $hf, $attrs, $inline_tpl, $parent_hf, $tagvalue);
                        } else { //if usual tag - replace it with tagvalue got above
                            #CSRF shield +1
                            if ($tagvalue > "" && !array_key_exists('noescape', $attrs)) {
                                $tagvalue = htmlescape($tagvalue);
                            }
                        }
                        $page = tag_replace($page, $tag_full, $tagvalue, $attrs);
                    } elseif (array_key_exists('repeat', $attrs)) {
                        //if it's repeat tag, but tag value empty - just replace with empty string
                        $page = tag_replace($page, $tag_full, '', $attrs);
                    } elseif (array_key_exists('var', $attrs)) {
                        //if tag doesn't exists in $hf and it's marked as variable (var)
                        //then don't make subparse and just replace to empty string for more performance
                        $page = tag_replace($page, $tag_full, "", $attrs);
                    } elseif (array_key_exists('select', $attrs)) {
                        //this is special case for '<select>' HTML tag
                        $v    = parse_select_tag($basedir, tag_tplpath($tag, $tpl_name, $is_inline_tpl), $tag, $hf, $attrs);
                        $page = tag_replace($page, $tag_full, $v, $attrs);
                    } elseif (array_key_exists('radio', $attrs)) {
                        //this is special case for '<index type=radio>' HTML tag
                        $v    = parse_radio_tag($basedir, tag_tplpath($tag, $tpl_name, $is_inline_tpl), $hf, $attrs);
                        $page = tag_replace($page, $tag_full, $v, $attrs);
                    } elseif (array_key_exists('selvalue', $attrs)) {
                        //this is special case for displaying just one selected value (for '<select>' or '<index type=radio>' HTML tags
                        $v = parse_selvalue_tag($basedir, tag_tplpath($tag, $tpl_name, $is_inline_tpl), $tag, $hf, $attrs);
                        if (!array_key_exists('noescape', $attrs)) {
                            $v = htmlescape($v);
                        }

                        $page = tag_replace($page, $tag_full, $v, $attrs);
                    } else {
                        #also checking for sub
                        if (array_key_exists('sub', $attrs)) {
                            $v = _tag_sub($basedir, tag_tplpath($tag, $tpl_name, $is_inline_tpl), $tag, $hf, $attrs, $inline_tpl, $parent_hf, $tagvalue);
                        } else {
                            //if tag is not set and not a var - then it's subtemplate in a file - parse it
                            //echo "$tag SUBPARSE<br>\n";
                            $v = _parse_page($basedir, tag_tplpath($tag, $tpl_name, $is_inline_tpl), $hf, $inline_tpl, $hf);
                        }
                        $page = tag_replace($page, $tag_full, $v, $attrs);
                    }
                } else {
                    $page = tag_replace_raw($page, $tag_full, '', array_key_exists('inline', $attrs));
                    // print "$tag not shown\n";
                }
            }
        } //if tags empty
    } else { //if  tpl empty
        logger('TRACE', "ParsePage notice - empty template [$tpl_name]");
    }

    #logger('TRACE', "End of $tpl_name");
    return $page;
}

function print_header() {
    print header("Content-Type: text/html; charset=utf-8");
    #security headers - https://infosec.mozilla.org/guidelines/web_security
    print header("X-Content-Type-Options: nosniff");
    print header("Content-Security-Policy: frame-ancestors 'self'");
    print header("X-Frame-Options: DENY");
    #print header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    print header("X-XSS-Protection: 1; mode=block");
    print header("X-Permitted-Cross-Domain-Policies: master-only");
}

############## return value from hf , support arrays/hashes
function hfvalue($tag, &$hf, &$parent_hf = null) {
    global $PARSE_PAGE_DATA_TOP;

    $value = null;
    if (!isset($tag)) {
        return $value;
    }

    $empty_val = null;
    if (preg_match("/\[/", $tag)) {
        $arr    = explode('[', $tag);
        $parts0 = strtoupper($arr[0]);

        if ($parts0 == 'GLOBAL') {
            #was $ptr=&$GLOBALS;
            $ptr = fw::i()->GLOBAL;
            array_shift($arr);
        } elseif ($parts0 == 'SESSION') {
            $ptr = &$_SESSION;
            array_shift($arr);
        } elseif ($parts0 == 'PARSEPAGE.TOP') {
            $ptr = &$PARSE_PAGE_DATA_TOP;
            array_shift($arr);
        } elseif ($parts0 == 'PARSEPAGE.PARENT' && $parent_hf) {
            $ptr = &$parent_hf;
            array_shift($arr);
        } else {
            $ptr = &$hf;
        }

        for ($i = 0; $i < count($arr); $i++) {
            $k = preg_replace("/\].*?/", '', $arr[$i]); #remove last ]
            if (is_array($ptr) && array_key_exists($k, $ptr)) {
                $ptr = &$ptr[$k];
            } elseif (is_object($ptr) && property_exists($ptr, $k)) {
                $ptr = $ptr->$k;
            } else {
                $ptr = &$empty_val; #looks like there are just no such key in array OR $ptr is not an array at all - so return empty value
                break;
            }
        }
        $value = $ptr;
    } else {
        if (is_array($hf) && array_key_exists($tag, $hf)) {
            $value = $hf[$tag];
        }
    }

    return $value;
}

##############  extract inline template from page
function get_inline_tpl(&$page, $tag, $tag_full) {
    global $PARSE_PAGE_OPEN_TAG, $PARSE_PAGE_CLOSE_TAG, $PARSE_PAGE_OPENEND_TAG;

    $inline_tpl = '';

    #get inline template first
    $restr = '/' . preg_quote($PARSE_PAGE_OPEN_TAG . $tag_full . $PARSE_PAGE_CLOSE_TAG, '/') . "(.*?)" . preg_quote($PARSE_PAGE_OPENEND_TAG . $tag . $PARSE_PAGE_CLOSE_TAG, '/') . '/si';
    if (preg_match($restr, $page, $match)) {
        $inline_tpl = $match[1];
    } else {
        if (preg_match('/' . preg_quote($PARSE_PAGE_OPEN_TAG . $tag_full . $PARSE_PAGE_CLOSE_TAG, '/') . '/', $page)) {
            logger('DEBUG', "ParsePage error - no closing tag </~$tag> found for inline template <~$tag_full>");
        } else {
            //no open tag found - skip
            //TODO - optimize - when inline template parsed - tags should be marked as seen
        }
    }
    return $inline_tpl;
}

//#################################
// tag to right template path for the parse_page
// IN: $tag,$tpl_name,$is_nolang
function tag_tplpath($tag, $tpl_name, $is_inline_tpl = false) {
    $add_path = '';
    $result   = $tag;

    #     rw("&nbsp;&nbsp;&nbsp;tag=$tag, tpl_name=$tpl_name [$is_inline_tpl]\n");
    if (substr($tag, 0, 2) == './' || $is_inline_tpl) { //if tag start from './' then take template from same dir as tpl_name
        $add_path = $tpl_name;
        $add_path = preg_replace("/[^\/]+$/", '', $add_path); //delete file name
        $result   = preg_replace("/^\.\//", '', $result); //delete ./ at the begin
    }

    // print "add_path=$add_path\n";
    $result = $add_path . $result;

    if (!preg_match("/\.[^\/]+$/", $tag)) { //set default .html extension (if extension is not set)
        $result .= ".html";
    }
    #     rw("&nbsp;&nbsp;&nbsp;result=$result");
    return $result;
}

function _tag_sub($basedir, $tpl_path, $tag, &$hf, &$attrs, &$inline_tpl, &$parent_hf, &$tagvalue) {
    if ($attrs['sub'] > '') {
        #if sub attr contains name - use it to get value from hf (instead using tag_value)
        $tagvalue = hfvalue($attrs['sub'], $hf, $parent_hf);
    }
    if (!is_array($tagvalue)) {
        logger('DEBUG', 'ParsePage warning - not an array passed for a SUB tag = ' . $tag . ', sub = ' . $attrs['sub']);
        $tagvalue = array();
    }

    return _parse_page($basedir, $tpl_path, $tagvalue, $inline_tpl, $parent_hf);
}

//return true when "ifXX/unless" conditions true, otherwise - false
function tag_if($attrs, $hf) {
    global $PARSE_PAGE_OPS;

    $result = false;

    //first - check attrs that control show tag or not
    $oper  = '';
    $eqvar = null;
    foreach ($PARSE_PAGE_OPS as $k => $v) {
        if (array_key_exists($v, $attrs)) {
            $oper  = $v;
            $eqvar = $attrs[$v];
            break;
        }
    }
    # if no if operation - return true
    if (!$oper) {
        return true;
    }

    #just for debug upgrade from old templates
    if (array_key_exists("ifgee", $attrs) || array_key_exists("iflee", $attrs)) {
        logger('WARN', "ParsePage warning - old-style template used. Upgrade templates!");
    }

    $avalue = '';
    $aeqvar = ($eqvar) ? hfvalue($eqvar, $hf) : '';
    #get the comparison value
    if (array_key_exists('value', $attrs)) {
        $avalue = $attrs['value'];
    } elseif (array_key_exists('vvalue', $attrs)) {
        $avalue = hfvalue($attrs['vvalue'], $hf);
    }
    #rw("[$tag_full] oper=$oper, eqvar=$eqvar, value=$avalue, hfvalue=$aeqvar");
    #if (!$eqvar or $eqvar && (array_key_exists('if', $attrs) && parse_page_if($attrs{'if'}, $hf)
    /*
    if (){
    $result = true;
    }
     */

    if ($oper == 'if' && $aeqvar
        or $oper == 'unless' && !$aeqvar
        or $oper == 'ifeq' && ((string)$aeqvar == (string)$avalue)
        or $oper == 'ifne' && ((string)$aeqvar != (string)$avalue)
        or $oper == 'ifgt' && ((string)$aeqvar > (string)$avalue)
        or $oper == 'iflt' && ((string)$aeqvar < (string)$avalue)
        or $oper == 'ifge' && ((string)$aeqvar >= (string)$avalue)
        or $oper == 'ifle' && ((string)$aeqvar <= (string)$avalue)
    ) {
        $result = true;
    }
    #rw("result = $result");

    return $result;
}

//#################################
// tag replacer for the parse_page - WITH NECESSARY value VISUAL CHANGES
// IN: $page, $tag, $value, $attrs  - references
function tag_replace($page, $tag_full, $value, $attrs) {
    // echo "tag_replace tag=$tag_full<br>";
    // echo "tag_replace value=$value<br>";
    // echo "tag_replace page=".strlen($page)."<br>";
    if (is_null($value)) {
        $value = '';
    }

    if (!is_scalar($value)) {
        if (array_key_exists('json', $attrs)) {
            if ($attrs['json'] == 'pretty') {
                $value = var2js($value, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
            } else {
                $value = var2js($value, JSON_NUMERIC_CHECK);
            }
            if (!array_key_exists('noescape', $attrs)) {
                $value = htmlescape($value);
            }
        } else {
            logger('ParsePage warning - not a scalar passed for tag = ' . $tag_full . ' Passed type = ' . gettype($value));
            $value = '';
        }
    } else {
        if (array_key_exists('number_format', $attrs)) {
            $value = tpl_number_format($value, $attrs);
        }

        if (array_key_exists('string_format', $attrs)) {
            $value = string_format($value, $attrs['string_format']);
        }
        #for some compatibility with Smarty
        if (array_key_exists('sprintf', $attrs)) {
            $value = string_format($value, $attrs['sprintf']);
        }

        if (array_key_exists('htmlescape', $attrs)) {
            $value = htmlescape($value);
        }

        if (array_key_exists('date', $attrs)) {
            $value = sec2date($value, $attrs);
        }

        if (array_key_exists('truncate', $attrs)) {
            $value = Utils::str2truncate($value, $attrs);
        }

        if (array_key_exists('strip_tags', $attrs)) {
            $value = strip_tags($value);
        }

        if (array_key_exists('trim', $attrs)) {
            $value = trim($value);
        }

        if (array_key_exists('nl2br', $attrs)) {
            $value = nl2br($value);
        }

        if (array_key_exists('count', $attrs)) {
            $value = count($value);
        }

        if (array_key_exists('lower', $attrs)) {
            $value = strtolower($value);
        }

        if (array_key_exists('upper', $attrs)) {
            $value = strtoupper($value);
        }

        if (array_key_exists('default', $attrs)) {
            $value = str2default($value, $attrs);
        }

        if (array_key_exists('urlencode', $attrs)) {
            $value = urlencode($value);
        }

        if (array_key_exists('json', $attrs)) {
            if ($attrs['json'] == 'pretty') {
                $value = var2js($value, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
            } else {
                $value = var2js($value, JSON_NUMERIC_CHECK);
            }
            if (!array_key_exists('noescape', $attrs)) {
                $value = htmlescape($value);
            }
        }
    }
    return tag_replace_raw($page, $tag_full, $value, array_key_exists('inline', $attrs));
}

//#################################
// raw tag replacer for the parse_page - i.e. doesn't change the value
// IN: $page, $tag, $value, $is_inline
function tag_replace_raw($page, $tag_full, $value, $is_inline) {
    global $PARSE_PAGE_OPEN_TAG, $PARSE_PAGE_CLOSE_TAG, $PARSE_PAGE_OPENEND_TAG;

    if ($is_inline) { //check if this inline template - replace it in special way
        #get just tag without attrs
        $tag = $tag_full;
        if (preg_match('/^(\S+)/', $tag, $match)) {
            $tag = $match[1];
        }

        #replace tag+inline tpl+close tag
        $restr = '/' . preg_quote($PARSE_PAGE_OPEN_TAG . $tag_full . $PARSE_PAGE_CLOSE_TAG, '/') . ".*?" . preg_quote($PARSE_PAGE_OPENEND_TAG . $tag . $PARSE_PAGE_CLOSE_TAG, '/') . '/si';

        #quote special PHP $ and \\12345
        $value = str_replace(array('\\', '$'), array('\\\\', '\\$'), $value); //even better, order is important

        return preg_replace($restr, $value, $page);
    } else {
        //return preg_replace("/<~".preg_quote($tag_full,"/").">/", $value.'', $page);
        return str_replace($PARSE_PAGE_OPEN_TAG . $tag_full . $PARSE_PAGE_CLOSE_TAG, $value, $page);
    }
}

############### parse select tag
# output <option value="XX">YY
# $attrs['select'] could be an array! for <select multiple>
function parse_select_tag($basedir, $tpl_path, $tag, $hf, $attrs) {
    global $CONFIG;

    $sel_value = hfvalue($attrs['select'], $hf);
    if (is_array($sel_value)) {
        $sel_value = array_flip($sel_value);
    }

    $result      = '';
    $is_noescape = array_key_exists('noescape', $attrs);
    if (array_key_exists($tag, $hf)) {
        # hf(tag) is Array of Assoc Arrays with "id" and "iname" keys, for example rows returned from db.array('select id, iname from ...')
        # "id" key is optional, if not present - iname will be used for values too
        if (!is_array($hf[$tag])) {
            logger('DEBUG', 'ParsePage warning - not an array passed to select tag = ' . $tag);
            return '';
        }

        foreach ($hf[$tag] as $key => $item) {
            $desc = $item['iname'];
            if (array_key_exists('id', $item)) {
                $value = trim($item['id']);
            } else {
                $value = trim($item['iname']);
            }
            parse_lang($value);
            parse_lang($desc);

            $selected = '';
            if (is_array($sel_value) && array_key_exists($value, $sel_value) || $value == $sel_value) {
                $selected = ' selected';
            }

            if (!$is_noescape) {
                $value = htmlescape($value);
                $desc  = htmlescape($desc);
            }
            $result .= "<option value=\"$value\"$selected>$desc</option>\n";
        }
    } else {
        # just read from the plain text file
        if (!preg_match("/^\//", $tpl_path)) {
            $tpl_path = "$basedir/$tpl_path";
        }

        $lines = preg_split("/[\r\n]+/", precache_file($CONFIG['SITE_TEMPLATES'] . $tpl_path));

        for ($i = 0; $i < count($lines); $i++) {
            $arr   = preg_split("/\|/", $lines[$i]);
            $value = $arr[0] ?? '';
            $desc  = $arr[1] ?? '';
            if (!strlen($value) && !strlen($desc)) {
                continue;
            }

            parse_lang($value);
            parse_lang($desc);
            $desc = parse_cache_template($desc, $hf);

            $selected = '';
            if (is_array($sel_value) && array_key_exists($value, $sel_value) || $value == $sel_value) {
                $selected = ' selected';
            }

            if (!$is_noescape) {
                $value = htmlescape($value);
                $desc  = htmlescape($desc);
            }
            $result .= "<option value=\"$value\"$selected>$desc</option>\n";
        }
    }

    return $result;
}

############### parse radio tag
# output <input type=radio ...>label
function parse_radio_tag($basedir, $tpl_path, $hf, $attrs) {
    global $CONFIG;

    $sel_value = hfvalue($attrs['radio'], $hf);
    $name      = $attrs['name'];
    $delim     = htmlescape_back($attrs['delim']);
    if (!preg_match("/^\//", $tpl_path)) {
        $tpl_path = "$basedir/$tpl_path";
    }

    $lines = preg_split("/[\r\n]+/", precache_file($CONFIG['SITE_TEMPLATES'] . $tpl_path));

    $result = '';
    for ($i = 0; $i < count($lines); $i++) {
        $arr   = preg_split("/\|/", $lines[$i]);
        $value = $arr[0] ?? '';
        $desc  = $arr[1] ?? '';
        if (!strlen($value) && !strlen($desc)) {
            continue;
        }

        parse_lang($desc);
        $desc = parse_cache_template($desc, $hf);

        if (!array_key_exists('noescape', $attrs)) {
            $value = htmlescape($value);
            $desc  = htmlescape($desc);
        }

        $str_checked = '';
        if ($value == $sel_value) {
            $str_checked = " checked='checked' ";
        }

        #$result.="<label class='radio $delim'><input type='radio' name=\"$name\" value=\"$value\" $str_checked>$desc</label>"; #bootstrap3
        #$result.="<div class='form-check $delim'><label class='form-check-label'><input class='form-check-input' type='radio' name=\"$name\" value=\"$value\" $str_checked> $desc</label></div>"; #bootstrap4 normal control
        $result .= "<div class='custom-control custom-radio $delim'><input class='custom-control-input' type='radio' id=\"$name\$$i\" name=\"$name\" value=\"$value\" $str_checked><label class='custom-control-label' for=\"$name\$$i\">$desc</label></div>"; #bootstrap4 custom control
    }
    return $result; #bootstrap4
}

############
# output - just string
function parse_selvalue_tag($basedir, $tpl_path, $tag, $hf, $attrs) {
    global $CONFIG;

    $sel_value = hfvalue($attrs['selvalue'], $hf);
    if (!preg_match("/^\//", $tpl_path)) {
        $tpl_path = "$basedir/$tpl_path";
    }

    $lines = preg_split("/[\r\n]+/", precache_file($CONFIG['SITE_TEMPLATES'] . $tpl_path));

    $result = '';
    if ($tag > '' && array_key_exists($tag, $hf)) {
        # hf(tag) is Array of Assoc Arrays with "id" and "iname" keys, for example rows returned from db.array('select id, iname from ...')
        # "id" key is optional, if not present - iname will be used for values too
        if (!is_array($hf[$tag])) {
            logger('DEBUG', 'ParsePage warning - not an array passed to select tag = ' . $tag);
            return '';
        }

        foreach ($hf[$tag] as $key => $item) {
            $desc = $item['iname'];
            if (array_key_exists('id', $item)) {
                $value = trim($item['id']);
            } else {
                $value = trim($item['iname']);
            }
            parse_lang($value);
            parse_lang($desc);

            if (is_array($sel_value) && array_key_exists($value, $sel_value) || $value == $sel_value) {
                $result = $desc;
                break;
            }
        }
    } else {
        # from file
        for ($i = 0; $i < count($lines); $i++) {
            $arr   = preg_split("/\|/", $lines[$i]);
            $value = $arr[0] ?? '';
            $desc  = $arr[1] ?? '';
            if (!strlen($value) && !strlen($desc)) {
                continue;
            }

            if ($value == $sel_value) {
                parse_lang($desc);
                $desc = parse_cache_template($desc, $hf);

                $result = $desc;
                break;
            }
        }
    }

    return $result;
}

//return value for the selvalue
//example:
//  get_selvalue('/common/sel/yn.sel', 1);
function get_selvalue($tpl, $value) {
    return parse_selvalue_tag('', $tpl, '', array('v' => $value), array('selvalue' => 'v'));
}

# sort tags so 'inline' and 'sub' tags will be parsed first)
function parse_page_sort_tags($tags_full) {
    $ainline = array();
    $atag    = array();

    for ($i = 0; $i < count($tags_full); $i++) {
        if (preg_match('/inline|sub\b/', $tags_full[$i])) {
            $ainline[] = &$tags_full[$i];
        } else {
            $atag[] = &$tags_full[$i];
        }
    }

    return array_merge($ainline, $atag);
}

//#################################
// load file into variable
// CACHED!!!
global $FILE_CACHE;
$FILE_CACHE = array();
function precache_file($infile, $isdie = false) {
    global $FILE_CACHE;
    $result = '';

    if (array_key_exists($infile, $FILE_CACHE)) {
        return $FILE_CACHE[$infile];
    }

    if (file_exists($infile)) {
        $result = file_get_contents($infile);
    } else {
        $msg = "ParsePage - can't open template file [$infile]";
        if ($isdie) {
            logger('ERROR', $msg);
            throw new Exception();
        } else {
            #if no die - just log trace, it's not an issue if there are no template file
            logger('TRACE', $msg);
        }
    }

    $FILE_CACHE[$infile] = $result;
    return $result;
}

##########################################################
#Cached Template parser
#
function parse_cache_template($page, $hf, $out_filename = '') {
    global $PARSE_PAGE_OPEN_TAG, $PARSE_PAGE_CLOSE_TAG, $PARSE_PAGE_OPENEND_TAG;
    global $CONFIG;

    preg_match_all("/$PARSE_PAGE_OPEN_TAG([^$PARSE_PAGE_CLOSE_TAG]+)$PARSE_PAGE_CLOSE_TAG/", $page, $out, PREG_PATTERN_ORDER);
    $tags           = $out[1];
    $tags[]         = 'ROOT_URL';
    $hf['ROOT_URL'] = $CONFIG['ROOT_URL'];

    foreach ($tags as $key => $tag) {
        $page = preg_replace("/" . $PARSE_PAGE_OPEN_TAG . preg_quote($tag, "/") . $PARSE_PAGE_CLOSE_TAG . "/", hfvalue($tag, $hf), $page);
    }

    if ($out_filename && $out_filename != 's') {
        $outdir = dirname($out_filename); #check dir
        if (!is_dir($outdir)) {
            mkdir($outdir, 0777);
        }

        $OUTFILE = fopen($out_filename, "w") or die("Can't open out [$out_filename] file");
        fputs($OUTFILE, $page);
        fclose($OUTFILE);
    } else {
        if ($out_filename == '') { #variable mode
            return $page;
        } else { #screen mode
            print $page; #no XSS here intended to output html
        }
    }
}

############## HELPER UTILS

############# basically htmlspecialchars($str, ENT_QUOTES, 'UTF-8')
function htmlescape($str) {
    if (!is_scalar($str)) {
        return $str;
    }

    $str = preg_replace("/&/", '&amp;', $str);
    $str = preg_replace('/"/', '&quot;', $str);
    $str = preg_replace("/'/", '&#039;', $str);
    $str = preg_replace("/</", '&lt;', $str);
    $str = preg_replace("/>/", '&gt;', $str);

    return $str;
}

#############
function htmlescape_back($str) {
    $str = preg_replace("/&amp/", '&', $str);
    $str = preg_replace('/&quot;/', '"', $str);
    $str = preg_replace("/&#039;/", "'", $str);
    $str = preg_replace("/&lt;/", '<', $str);
    $str = preg_replace("/&gt;/", '>', $str);

    return $str;
}

# This replaces all repeated spaces, newlines and tabs with a single space.
function strip($str) {
    return preg_replace("/\s+/", ' ', $str);
}

# convert time in seconds to date
function sec2date($str, $attrs) {
    $format = $attrs['date'];
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
    }
    if ($str) {
        if ($str == '0000-00-00 00:00:00' || $str == '0000-00-00') {
            return '';
        }
        if (!preg_match("/^\d+$/", $str)) {
            $str = strtotime($str);
        }
        return date($format, (int)$str);
    } else {
        return $str;
    }
}

#format number according to rules
#return empty string if empty string passed
#<~tag number_format>     => 1234567890.23 => 1 234 567 890.23
#<~tag number_format="0"> => 1234567890.23 => 1 234 567 890
#<~tag number_format="2" nfpoint="." nfthousands=" ">
function tpl_number_format($str, $attrs) {
    if (!strlen($str)) {
        return '';
    }

    $nfdecimals  = 2;
    $nfpoint     = ".";
    $nfthousands = " ";
    if ($attrs['number_format'] > '') {
        $nfdecimals = $attrs['number_format'];
    }

    if (array_key_exists('nfpoint', $attrs)) {
        $nfpoint = $attrs['nfpoint'];
    }

    if (array_key_exists('nfthousands', $attrs)) {
        $nfthousands = $attrs['nfthousands'];
    }

    return number_format($str, $nfdecimals, $nfpoint, $nfthousands);
}

#This is used to set a default value for a variable. If the variable is unset or an empty string, the given default value is printed instead.
function str2default($str, $attrs) {
    if (!strlen($str)) {
        $str = $attrs['default'];
    }

    return $str;
}

#This is used parse string via sprintf
# useful example: <~price sprintf='%.2f'> will print 16.78 if price is 16.393293
function string_format($str, $format) {
    return sprintf($format, $str);
}

// Convert PHP scalar, array or hash to JS scalar/array/hash.
function var2js($a, $options = 0) {
    if (function_exists('json_encode')) {
        return json_encode($a, $options);
    }

    if (is_null($a)) {
        return 'null';
    }

    if ($a === false) {
        return 'false';
    }

    if ($a === true) {
        return 'true';
    }

    if (is_scalar($a)) {
        $a = addslashes($a);
        $a = str_replace("\n", '\n', $a);
        $a = str_replace("\r", '\r', $a);
        return "\"$a\"";
    }
    $isList = true;
    for ($i = 0, reset($a); $i < count($a); $i++, next($a)) {
        if (key($a) !== $i) {
            $isList = false;
            break;
        }
    }

    $result = array();
    if ($isList) {
        foreach ($a as $v) {
            $result[] = var2js($v);
        }

        return '[ ' . join(',', $result) . ' ]';
    } else {
        foreach ($a as $k => $v) {
            $result[] = var2js($k) . ': ' . var2js($v);
        }

        return '{ ' . join(',', $result) . ' }';
    }
}

################################################ LANGUAGE UTILS

# parse all lang stings in large text
# BY REFERENCE!
function parse_lang(&$page) {
    global $PARSE_PAGE_LANG_PARSE;

    if (!$PARSE_PAGE_LANG_PARSE) {
        return; #don't parse langs if told so
    }

    $page = preg_replace_callback("/\`([^\`]*)\`/", "replace_lang", $page);
}

#########
# USES GLOBAL $LANG
function replace_lang($matches) {
    return lng($matches[1]);
}

#########
function lng($str) {
    global $CONFIG;
    $l = $CONFIG['LANG'];
    if (!$l) {
        $l = $CONFIG['LANG_DEF'];
    }

    $result    = '';
    $lang_file = $CONFIG['SITE_TEMPLATES'] . "/lang/$l.txt";

    # logger("$str => $lang_file");

    if ($l) {
        $LANG_STR = load_lang($lang_file);
        $lang_str = @$LANG_STR[$str];
        $result   = $lang_str;
    }

    if (!$result) {
        $result = $str; #if no language - return original string

        #    logger("before update $result");
        #    if ($l=='en'){  #update only if we are under English (this keep performance better)
        if ($CONFIG['IS_LANG_UPD']) {
            update_lang($result, $CONFIG['LANG_DEF']); #if no translation - add string to en.txt file (but only if we allowed to update)
        }
        #    }
    }

    return $result;
}

######### precache language file into hash
global $LANG_CACHE;
$LANG_CACHE = array();

#$LANG - hash reference
function load_lang($infile, $isdie = 0) {
    global $LANG_CACHE;
    $result = '';

    if (array_key_exists($infile, $LANG_CACHE)) {
        return $LANG_CACHE[$infile];
    }

    if (file_exists($infile)) {
        $result = file_get_contents($infile);
    } else {
        if ($isdie) {
            die("Can't open language [$infile] file");
        }
    }

    $arr  = preg_split("/[\r\n]/", $result);
    $hash = array();
    foreach ($arr as $value) {
        if (!trim($value) || !preg_match("/\=\=\=/", $value)) {
            continue;
        }

        $a           = preg_split("/\s*\=\=\=\s*/", $value);
        $hash[$a[0]] = $a[1];
    }

    $LANG_CACHE[$infile] = $hash;
    return $hash;
}

###########
function update_lang($str, $lang) {
    global $CONFIG, $LANG_CACHE;

    $lang_file = $CONFIG['SITE_TEMPLATES'] . "/lang/$lang.txt";
    $LANG_STR  = load_lang($lang_file);
    logger('TRACE', "ParsePage notice - updating lang [$lang_file]");

    if (!array_key_exists($str, $LANG_STR)) {
        $LANG_CACHE[$lang_file][$str] = "";
        add_lockfile($lang_file, "$str === \n");
    }
}

//*****************************
function parse_json($hf, $out_filename = '') {
    $page = var2js($hf);

    if ($out_filename && $out_filename != 'v' && $out_filename != 's') {
        $outdir = dirname($out_filename); //check dir
        if (!is_dir($outdir)) {
            mkdir($outdir, 0777);
        }

        $OUTFILE = fopen($out_filename, "w") or die("Can't open out [$out_filename] file");
        fputs($OUTFILE, $page);
        fclose($OUTFILE);
    } else {
        if ($out_filename == 'v') { #variable mode
            return $page;
        } else { #screen mode
            header("Content-Type: application/json; charset=utf-8");
            print $page;
        }
    }
}

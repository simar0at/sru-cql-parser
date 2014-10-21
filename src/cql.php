<?php

/*
  Title: CQL-PHP Version 0.8.5
  Author:  Robert Sanderson
  Date:  2006-02-12
  Author:  Omar Siam
  Date:  2014-03
  Copyright: University of Liverpool, ACDH/ÖAW
  Licence: GPL
  Description:  Port of Python CQLParser to PHP
  Parses CQL Version 1.2
  Usage:  $parser = new CQLParser("query");
  $tree = &parser->query();
  $tree.toCQL();
  $tree.toXCQL();

  Changes:  php5 object model fixes, namespace, split test output to index.php
 */

namespace rsanderson\CQLParser;

$XCQLNamespace = 'http://www.loc.gov/zing/cql/xcql/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
xsi:schemaLocation="http://www.loc.gov/zing/cql/xcql/ http://www.loc.gov/standards/sru/xmlFiles/xcql.xsd';


/* The following is derived from Python's ShLex */

class SimpleLex {

    private $data;
    private $whitespace;
    private $quotes;
    private $state;
    private $token;

    function __construct($data) {
        mb_internal_encoding('UTF-8');
        mb_regex_encoding('UTF-8');
        $this->data = $data;
        $this->datalen = mb_strlen($data);
        $this->reWordchars = "/[\\pL\\pN_!@#$%^&*-+{}\\[\\];,.?|~`:\\\\']/";
        $this->whitespace = " \t\n";
        $this->quotes = '"';

        $this->state = ' ';
        $this->token = '';
        $this->nextToken = '';
        $this->position = -1;
        $this->debug = 0;
    }

    function get_token() {
        /* Read a token from data */

        if ($this->position >= $this->datalen) {
            return "";
        }

        $cont = 1;

        while ($cont) {

            $this->position += 1;
            if ($this->position >= $this->datalen) {
                return trim($this->token);
            }

            $nextchar = mb_substr($this->data, $this->position, 1);
            $is_ws = strpos($this->whitespace, $nextchar) > -1 ? true : false;
            $is_word = preg_match($this->reWordchars, $nextchar) === 1 ? true : false;
            $is_quote = strpos($this->quotes, $nextchar) > -1 ? true : false;

            if ($this->state == ' ') {
                if ($is_ws) {
                    if ($this->token != ' ') {
                        $cont = 0;
                    } else {
                        continue;
                    }
                } elseif ($is_word) {
                    $this->token = $nextchar;
                    $this->state = 'a';
                } elseif ($is_quote) {
                    $this->token = $nextchar;
                    $this->state = $nextchar;
                } elseif (strpos("<>", $nextchar) > -1) {
                    $this->token = $nextchar;
                    $this->state = '<';
                } elseif (strpos("=", $nextchar) > -1) {
                    $this->token = $nextchar;
                    $this->state = '=';
                } else {
                    $this->token = $nextchar;
                    $cont = 0;
                }
            } elseif ($this->state == "<") {
                if ($this->token == ">" && $nextchar == "=") {
                    $this->token .= $nextchar;
                    $this->state = ' ';
                } elseif ($this->token == "<" && strpos(">=", $nextchar) > -1) {
                    $this->token .= $nextchar;
                    $this->state = ' ';
                } elseif ($nextchar == "/") {
                    $this->state = " ";
                    $this->position -= 1;
                } elseif ($is_word) {
                    $this->state = "a";
                    $this->position -= 1;
                } elseif ($is_quote) {
                    $this->state = $nextchar;
                    $this->position -= 1;
                } else {
                    $this->state = ' ';
                }
                $cont = 0;
            } elseif ($this->state == "=") {
                if ($this->token == "=" && $nextchar == "=") {
                    $this->token .= $nextchar;
                    $this->state = ' ';
                } elseif ($nextchar == "/") {
                    $this->state = " ";
                    $this->position -= 1;
                } elseif ($is_word) {
                    $this->state = "a";
                    $this->position -= 1;
                } elseif ($is_quote) {
                    $this->state = $nextchar;
                    $this->position -= 1;
                } else {
                    $this->state = ' ';
                }
                $cont = 0;
            } elseif (strpos($this->quotes, $this->state) > -1) {
                $this->token .= $nextchar;
                /* allow escape */
                if ($nextchar == $this->state && mb_substr($this->token, -2, 1) != "\\") {
                    $this->state = ' ';
                    $cont = 0;
                }
            } elseif ($this->state == 'a') {
                if ($is_ws) {
                    $this->state = ' ';
                    if (mb_strlen($this->token) > 0) {
                        $cont = 0;
                    } else {
                        continue;
                    }
                } elseif (strpos("<>", $nextchar) > -1) {
                    $tok = $this->token;
                    $this->token = $nextchar;
                    $this->state = "<";
                    return trim($tok);
                } elseif ($is_word || $is_quote) {
                    $this->token .= $nextchar;
                } else {
                    /* break */
                    $tok = $this->token;
                    $this->token = $nextchar;
                    $this->position -= 1;
                    $this->state = ' ';
                    return trim($tok);
                }
            }
        }
        $tok = $this->token;
        $this->token = " ";
        return trim($tok);
    }

}

class Diagnostic {

    protected $uri;
    protected $message;
    protected $details;

    function __construct($message, $type = 10, $details = "") {
        $this->message = $message;
        $this->uri = "info:srw/diagnostic/1/$type";
        $this->details = $details;
    }

    function toXML() {
        $txt = "<diag:diagnostic xmlns:diag=\"http://www.loc.gov/zing/srw/diagnostic/\">\n";
        $txt .= "  <diag:uri>$this->uri</diag:uri>\n";
        $txt .= "  <diag:message>$this->message</diag:message>\n";
        if ($this->details) {
            $txt .= "  <diag:details>$this->message</diag:details>\n";
        }
        $txt .="</diag:diagnostic>\n";
        return $txt;
    }

}

abstract class CQLVisitor {
    public abstract function onCQLPrefixes(array $prefixes);
    public abstract function onCQLPart(CQLObject $data);
}

class CQLObject {

    protected $value;
    protected $modifiers;
    protected $parentNode;
    protected $config;

    function set_config($c) {
        $this->config = $c;
    }

    function resolve_prefix($pref) {
        if ($this->parentNode != null) {
            return $this->parentNode->resolve_prefix($pref);
        } elseif ($this->config != null) {
            return $this->config->resolve_prefix($pref);
        } else {
            /* Not in tree, and no config. Unknown */
            return null;
        }
    }

    public function to(CQLVisitor $v) {
        if (count($this->modifiers) > 0) {
            foreach ($this->modifiers as $mod) {
               $mod->to($v); 
            }   
        }
        $v->onCQLPart($this);
    }
    
    function toCQL() {
        $txt = $this->value;
        if (count($this->modifiers) > 0) {
            foreach ($this->modifiers as $mod) {
                $txt .= "/" . $mod->toCQL();
            }
        }
        return $txt;
    }

    function toXCQL() {
        return "";
    }

    function mods_toXCQL($depth = 0) {
        $space = str_repeat("  ", $depth);
        $txt = "$space<modifiers>\n";
        foreach ($this->modifiers as $mod) {
            $txt .= $mod->toXCQL($depth + 1);
        }
        $txt .= "$space</modifiers>\n";
        return $txt;
    }

    /**
     * Obtains an object class name without namespaces
     */
    function get_plain_class($obj) {
        $classname = get_class($obj);

        $matches = array();

        if (preg_match('@\\\\([\w]+)$@', $classname, $matches)) {
            $classname = $matches[1];
        }

        return $classname;
    }

    function toTxt($depth = 0) {
        $cl = $this->get_plain_class($this);
        $space = str_repeat("  ", $depth);
        $modtxt = "";
        if ($this->modifiers) {
            foreach ($this->modifiers as $mod) {
                $modtxt .= $mod->toTxt($depth + 1);
            }
        }
        return "$space$cl: $this->value\n$modtxt";
    }

}

class Prefixable extends CQLObject {

    /**
     * @var array
     */
    protected $prefixes = null;

    function add_prefix($p, $uri) {
        $this->prefixes[$p] = $uri;
    }

    function resolve_prefix($pref) {
        if ($this->prefixes && array_key_exists($pref, $this->prefixes)) {
            return $this->prefixes[$pref];
        } elseif ($this->parentNode != null) {
            return $this->parentNode->resolve_prefix($pref);
        } elseif ($this->config != null) {
            return $this->config->resolve_prefix($pref);
        } else {
            /* Not in tree, and no config. Unknown */
            return null;
        }
    }

    public function to(CQLVisitor $v) {
        if ($this->prefixes) {
            $v->onCQLPrefixes($this->prefixes);
        }
        parent::to($v);
    }
    
    function prefs_toCQL() {
        $txt = "";
        if ($this->prefixes) {
            foreach (array_keys($this->prefixes) as $key) {
                $val = $this->prefixes[$key];
                if ($key) {
                    $txt .= ">$key=\"$val\" ";
                } else {
                    $txt .= ">\"$val\" ";
                }
            }
        }
        return $txt;
    }

    function prefs_toXCQL($depth = 0) {
        $space = str_repeat("  ", $depth);
        $txt = "$space<prefixes>\n";
        foreach (array_keys($this->prefixes) as $key) {
            $val = $this->prefixes[$key];
            $txt .= "$space  <prefix>\n";
            if ($key) {
                $txt .= "$space    <name>" . $key . "</name>\n";
            }
            $txt .= "$space    <identifier>" . $val . "</identifier>\n";
            $txt .= "$space  </prefix>\n";
        }
        $txt .= "$space</prefixes>\n";
        return $txt;
    }

}

class Prefixed extends CQLObject {

    /**
     * @var string
     */
    public $prefix;
    /**
     * @var string
     */
    protected $uri;

    function split_value() {
        $c = substr_count($this->value, '.');
        if ($c > 1) {
            /* NASTY! */
            $diag = new Diagnostic("Too many .s in value: $this->value");
        } elseif ($this->value{0} == '.') {
            @trigger_error("Null prefix");
        } elseif ($c > 0) {
            list($pref, $data) = explode('.', $this->value);
            $this->prefix = $pref;
            $this->value = $data;
        }
    }

    function resolve_prefix($pref = NULL) {
        /* resolve my prefix */
        if (!$this->uri && $this->parentNode != null) {
            $uri = $this->parentNode->resolve_prefix($this->prefix);
            $this->uri = $uri;
        }
        return $this->uri;
    }

    function toCQL() {
        if ($this->prefix) {
            $txt = "$this->prefix.$this->value";
        } else {
            $txt = $this->value;
        }
        if (count($this->modifiers) > 0) {
            foreach ($this->modifiers as $mod) {
                $txt .= "/" . $mod->toCQL();
            }
        }
        return $txt;
    }

    function toTxt($depth = 0) {
        $cl = $this->get_plain_class($this);
        $space = str_repeat("  ", $depth);
        $modtxt = "";
        if ($this->modifiers) {
            foreach ($this->modifiers as $mod) {
                $modtxt .= $mod->toTxt($depth + 1);
            }
        }
        $this->resolve_prefix();
        if ($this->uri) {
            return "$space$cl: $this->prefix $this->uri . $this->value\n$modtxt";
        } elseif ($this->prefix) {
            return "$space$cl: $this->prefix . $this->value\n$modtxt";
        } else {
            return "$space$cl: $this->value\n$modtxt";
        }
    }

}

class Triple extends Prefixable {

    var $leftOperand;
    var $rightOperand;
    var $boolean;
    var $sortKeys;

    function __construct($left, $right, $bool) {
        $this->prefixes = array();
        $this->parentNode = null;
        $this->sortKeys = null;
        $this->leftOperand = $left;
        $this->rightOperand = $right;
        $this->boolean = $bool;
        $left->parentNode = $this;
        $right->parentNode = $this;
        $bool->parentNode = $this;
    }

    public function to(CQLVisitor $v) {
        parent::to($v);
        $v->onCQLPart($this->leftOperand);
        $this->leftOperand->to($v);
        $v->onCQLPart($this->boolean);
        $this->boolean->to($v);
        $v->onCQLPart($this->rightOperand);
        $this->rightOperand->to($v);
        if (isset($this->sortKeys)) {
            foreach ($this->sortKeys as $sortKey) {
                $v->onCQLPart($sortKey);
            }
        }
    }
    
    function toCQL() {
        $prefs = $this->prefs_toCQL();
        $ret = "$prefs(" . $this->leftOperand->toCQL() . " " . $this->boolean->toCQL() . " " . $this->rightOperand->toCQL() . ")";
        if (is_array($this->sortKeys)) {
            $ret .= ' sortBy ';
            foreach ($this->sortKeys as $sortKey) {
                $ret .= $sortKey->toCQL() . ' ';
            }
        }
        return $ret;
    }

    function toXCQL($depth = 0) {
        global $XCQLNamespace;
        $space = str_repeat("  ", $depth);
        if ($depth == 0) {
            $txt = '<triple xmlns="' . $XCQLNamespace . "\">\n";
        } else {
            $txt = "$space<triple>\n";
        }
        if ($this->prefixes) {
            $txt .= $this->prefs_toXCQL($depth + 1);
        }
        $txt .= $this->boolean->toXCQL($depth + 1);
        $txt .= "$space  <leftOperand>\n";
        $txt .= $this->leftOperand->toXCQL($depth + 2);
        $txt .= "$space  </leftOperand>\n";
        $txt .= "$space  <rightOperand>\n";
        $txt .= $this->rightOperand->toXCQL($depth + 2);
        $txt .= "$space  </rightOperand>\n";

        if ($this->sortKeys) {
            $txt .= "$space  <sortKeys>\n";
            foreach ($this->sortKeys as $key) {
                $txt .= $key->toXCQL($depth + 2);
            }
            $txt .= "$space  </sortKeys>\n";
        }
        $txt .= "$space</triple>\n";
        return $txt;
    }

    function toTxt($depth = 0) {
        $space = str_repeat("  ", $depth);
        $txt = CQLObject::toTxt($depth);
        $txt .= $this->leftOperand->toTxt($depth + 1);
        $txt .= $this->boolean->toTxt($depth + 1);
        $txt .= $this->rightOperand->toTxt($depth + 1);

        if ($this->sortKeys) {
            $txt .= "$space  sortBy:\n";
            foreach ($this->sortKeys as $key) {
                $txt .= "$space    " . $key->toTxt() . "\n";
            }
        }
        return $txt;
    }

}

class SearchClause extends Prefixable {

    /**
     * @var Index 
     */
    public $index;
    /**
     * @var Relation 
     */
    protected $relation;
    /**
     * @var Term 
     */    
    protected $term;
    /**
     * @var array(SortKey) 
     */
    protected $sortKeys;

    function __construct(Index $i, Relation $r, Term $t) {
        $this->parentNode = null;
        $this->sortKeys = null;
        $this->index = $i;
        $this->relation = $r;
        $this->term = $t;
        $i->parentNode = $this;
        $r->parentNode = $this;
        $t->parentNode = $this;
    }

    function toCQL() {
        $prefs = $this->prefs_toCQL();
        return $prefs . $this->index->toCQL() . " " . $this->relation->toCQL() . " \"" . $this->term->toCQL() . "\" ";
    }

    function toXCQL($depth = 0) {
        global $XCQLNamespace;
        $space = str_repeat("  ", $depth);
        $txt = '';
        if ($depth == 0) {
            $txt .= '<searchClause xmlns="' . $XCQLNamespace . "\">\n";
        } else {
            $old_prefixes = $this->prefixes;
            if (!isset($this->prefixes) && isset($this->index->prefix)) {
                $this->add_prefix($this->index->prefix, $this->resolve_prefix($this->index->prefix));
            }
            if ($this->prefixes) {
                $txt .= $this->prefs_toXCQL();
            }
            $this->prefixes = $old_prefixes;
            $txt .= "$space<searchClause>\n";
        }

        $txt .= $this->index->toXCQL($depth + 1);
        $txt .= $this->relation->toXCQL($depth + 1);
        $txt .= $this->term->toXCQL($depth + 1);

        if ($this->sortKeys) {
            $txt .= "$space  <sortKeys>\n";
            foreach ($sortkeys as $key) {
                $txt .= $key->toXCQL($depth + 2);
            }
            $txt .= "$space  </sortKeys>\n";
        }

        $txt .= "$space</searchClause>\n";
        return $txt;
    }

    function toTxt($depth = 0) {
        $space = str_repeat("  ", $depth);
        $txt = CQLObject::toTXT($depth);
        $txt .= $this->index->toTxt($depth + 1);
        $txt .= $this->relation->toTxt($depth + 1);
        $txt .= $this->term->toTxt($depth + 1);
        return $txt;
    }

}

class Index extends Prefixed {

    function __construct($data) {
        $this->value = $data;
        $this->split_value();
    }

    function toXCQL($depth = 0) {
        $space = str_repeat("  ", $depth);
        $txt = "$space<index>";
        $txt .= $this->value;
        if ($this->modifiers) {
            $txt .= "\n";
            $txt .= $this->mods_toXCQL($depth + 1);
            $txt .= "$space</index>\n";
        } else {
            $txt .= "</index>\n";
        }
        return $txt; 
    }

}

class Relation extends Prefixed {

    function __construct($data) {
        $this->value = $data;
        $this->split_value();
    }

    function add_modifiers($mods) {
        $this->modifiers = $mods;
        foreach ($mods as $m) {
            $m->parentNode = $this;
        }
    }

    function toXCQL($depth = 0) {
        $space = str_repeat("  ", $depth);
        $txt = "$space<relation>\n";
        $txt .= "$space  <value>" . $this->value . "</value>\n";
        if ($this->modifiers) {
            $txt .= $this->mods_toXCQL($depth + 1);
        }
        $txt .= "$space</relation>\n";
        return $txt;
    }

}

class Term extends CQLObject {

    function __construct($data) {
        if ($data{0} == '"' && $data{strlen($data) - 1} == '"') {
            $data = substr($data, 1, strlen($data) - 2);
        }
        $this->value = $data;
    }

    function toXCQL($depth = 0) {
        $space = str_repeat("  ", $depth);
        return "$space<term>" . $this->value . "</term>\n";
    }

}

class Boolean extends CQLObject {

    function __construct($data) {
        $this->value = $data;
    }

    function add_modifiers($mods) {
        $this->modifiers = $mods;
        foreach ($mods as $m) {
            $m->parentNode = $this;
        }
    }

    function toCQL() {
        $txt = strtoupper($this->value);
        if (count($this->modifiers) > 0) {
            foreach ($this->modifiers as $mod) {
                $txt .= "/" . $mod->toCQL();
            }
        }
        return $txt;
    }

    function toXCQL($depth = 0) {
        $space = str_repeat("  ", $depth);
        $txt = "$space<boolean>\n";
        $txt .= "$space  <value>" . $this->value . "</value>\n";
        if ($this->modifiers) {
            $txt .= $this->mods_toXCQL($depth + 1);
        }
        $txt .= "$space</boolean>\n";
        return $txt;
    }

}

class ModifierType extends Prefixed {

    function __construct($v) {
        $this->value = $v;
        $this->split_value();
    }

    function toXCQL($depth = 0) {
        $space = str_repeat("  ", $depth);
        return "$space<type>" . $this->toCQL() . "</type>\n";
    }

}

class ModifierClause extends CQLObject {

    var $type;
    var $comparison;

    function __construct($m, $r, $v) {
        $this->value = $v;
        $this->comparison = $r;
        $this->type = new ModifierType($m);
        $this->type->parentNode = $this;
    }

    function toCQL() {
        return $this->type->toCQL() . $this->comparison . $this->value;
    }

    function toXCQL($depth = 0) {
        $space = str_repeat("  ", $depth);
        $txt = "$space<modifier>\n";
        $txt .= $this->type->toXCQL($depth + 1);
        if ($this->value) {
            $txt .= "$space  <comparison>" . $this->comparison . "</comparison>\n";
            $txt .= "$space  <value>" . $this->value . "</value>\n";
        }
        $txt .= "$space</modifier>\n";
        return $txt;
    }

    function toTxt($depth = 0) {
        $space = str_repeat("  ", $depth);
        $txt = $space . CQLObject::toTxt();
        $t = $this->type->toCQL();
        $txt .= "{$space}  type: $t\n";
        if ($this->value) {
            $txt .= "{$space}  comparison: $this->comparison\n";
            $txt .= "{$space}  value: $this->value\n";
        }
        return $txt;
    }

}

class SortKey extends CQLObject {

    var $index;

    function __construct($i, $m) {
        $this->index = $i;
        $this->index->modifiers = $m;
    }

//    function add_modifiers($mods) {
//        $this->modifiers = $mods;
//        foreach ($mods as $m) {
//            $m->parentNode = $this;
//        }
//    }

    function toTxt($depth = 0) {
        return $this->index->toTxt($depth);
    }

    function toXCQL($depth = 0) {
        return $this->index->toXCQL($depth);
    }
    
    function toCQL($depth = 0) {
        return $this->index->toCQL($depth);
    }
}

class CQLParser {

    var $serverChoiceRelation;
    var $serverChoiceIndex;
    var $order;
    var $separator;
    var $booleans;
    var $sortWord;
    var $config;
    var $diagnostic;
    var $lexer;
    private $current;
    private $next;

    function __construct($data) {
        $this->serverChoiceRelation = "=";
        $this->serverChoiceIndex = "cql.serverChoice";
        $this->order = array("=", ">", ">=", "<", "<=", "<>", "==");
        $this->separator = "/";
        $this->booleans = array("and", "or", "not", "prox");
        $this->sortWord = "sortby";

        $this->diagnostic = null;
        $this->lexer = new SimpleLex($data);
        $this->current = "";
        $this->next = "";

        $this->fetch_token();
        $this->fetch_token();
    }

    function get_current_token() {
        return $this->current;
    }

    function fetch_token() {
        $this->current = $this->next;
        $this->next = $this->lexer->get_token();
    }

    function is_bool($token) {
        return in_array(strtolower($token), $this->booleans);
    }

    function is_sort($token) {
        return strtolower($token) == $this->sortWord;
    }

    function query() {
        $prefs = $this->prefixes();
        $left = $this->subQuery();
        if ($this->diagnostic) {
            return $this->diagnostic;
        }
        $cont = 1;
        while ($cont) {
            if (!$this->current) {
                $cont = 0;
            } elseif ($this->is_sort($this->current)) {
                $left->sortKeys = $this->sortQuery();
            } elseif ($this->current == ")") {
                return $left;
            } else {
                $bool = $this->boolean();
                if ($this->diagnostic) {
                    return $this->diagnostic;
                }
                $right = $this->subQuery();
                if ($this->diagnostic) {
                    return $this->diagnostic;
                }
                $triple = new Triple($left, $right, $bool);
                $left = $triple;
            }
        }
        foreach (array_keys($prefs) as $key) {
            $left->add_prefix($key, $prefs[$key]);
        }
        return $left;
    }

    function subQuery() {
        if ($this->current == "(") {
            $this->fetch_token();
            $object = $this->query();
            if ($this->current == ")") {
                $this->fetch_token();
            } else {
                $this->diagnostic = new Diagnostic("Mismatched Parens");
                return null;
            }
        } else {
            $prefs = $this->prefixes();
            if ($prefs) {
                $object = $this->query();
                foreach (array_keys($prefs) as $key) {
                    $object->add_prefix($key, $prefs[$key]);
                }
            } else {
                $object = $this->clause();
            }
        }
        return $object;
    }

    function clause() {
        $bool = $this->is_bool($this->next);
        $sort = $this->is_sort($this->next);

        if (!$sort && !$bool && $this->next && !strpos("()", $this->next)) {
            $index = new Index($this->current);
            $this->fetch_token();
            $rel = $this->relation();
            if (!$this->current) {
                $this->diagnostic = new Diagnostic("Missing Term");
                return null;
            } else {
                $term = new Term($this->current);
                $this->fetch_token();
            }
        } elseif ($this->current && ($bool || $sort || !$this->next || $this->next == ")")) {
            $index = new Index($this->serverChoiceIndex);
            $rel = new Relation($this->serverChoiceRelation);
            $term = new Term($this->current);
            $this->fetch_token();
        } elseif ($this->current == ">") {
            $prefs = $this->prefixes();
            $object = $this->clause();
            foreach (array_keys($prefs) as $key) {
                $object->add_prefix($key, $prefs[$key]);
            }
            return $object;
        } else {
            $this->diagnostic = new Diagnostic("Expected Boolean or Relation");
            return null;
        }
        $sc = new SearchClause($index, $rel, $term);
        return $sc;
    }

    function boolean() {
        if ($this->is_bool($this->current)) {
            $bool = new Boolean($this->current);
            $this->fetch_token();
            $bool->add_modifiers($this->modifiers());
            return $bool;
        } else {
            $this->diagnostic = new Diagnostic("Expected Boolean, got $this->current");
            return null;
        }
    }

    function relation() {
        $rel = new Relation($this->current);
        $this->fetch_token();
        $rel->add_modifiers($this->modifiers());
        return $rel;
    }

    function modifiers() {
        $mods = array();
        while ($this->current == $this->separator) {
            $this->fetch_token();
            $mod = strtolower($this->current);
            $this->fetch_token();
            if (in_array($this->current, $this->order)) {
                $comp = $this->current;
                $this->fetch_token();
                $val = $this->current;
                $this->fetch_token();
            } else {
                $comp = "";
                $val = "";
            }
            $mods[] = new ModifierClause($mod, $comp, $val);
        }
        return $mods;
    }

    function prefixes() {
        $prefs = array();
        while ($this->current == ">") {
            $this->fetch_token();
            if ($this->next == "=") {
                $name = $this->current;
                $this->fetch_token();
                $this->fetch_token();
                $identifier = $this->current;
                $this->fetch_token();
            } else {
                $name = "";
                $identifier = $this->current;
                $this->fetch_token();
            }
            if ($identifier{0} == '"' && $identifier{strlen($identifier) - 1} == '"') {
                $identifier = substr($identifier, 1, strlen($identifier) - 2);
            }
            $prefs[strtolower($name)] = $identifier;
        }
        return $prefs;
    }

    function sortQuery() {
        $this->fetch_token();
        $keys = array();
        if (!$this->current) {
            $this->diagnostic = new Diagnostic("No sortkeys after sortBy");
            return null;
        } else {
            while ($this->current) {
                $index = new Index($this->current);
                $this->fetch_token();
                $mods = $this->modifiers();
                $keys[] = new SortKey($index, $mods);
            }
            return $keys;
        }
    }

}

class CQLConfig {

    var $defaultContextSet;
    var $defaultIndex;
    var $defaultRelation;
    var $contextSets;

    function __construct($zeerex = null) {
        $this->contextSets = array();

        if ($zeerex == null) {
            $this->defaultContextSet = "dc";
            $this->defaultIndex = "title";
            $this->defaultRelation = "any";

            $this->contextSets['cql'] = 'info:srw/cql-context-set/1/cql-v1.1';
            $this->contextSets['dc'] = "info:srw/cql-context-set/1/dc-v1.1";
            $this->contextSets['zthes'] = "http://zthes.z3950.org/cql/1.0/";
            $this->contextSets['ccg'] = "http://srw.cheshire3.org/contextSets/ccg/1.1/";
            $this->contextSets['rec'] = "info:srw/cql-context-set/2/rec-1.1";
            $this->contextSets['net'] = "info:srw/cql-context-set/2/net-1.0";
            $this->contextSets['music'] = "info:srw/cql-context-set/3/music-1.0";
            $this->contextSets['rel'] = "info:srw/cql-context-set/2/relevance-1.0";
            $this->contextSets['zeerex'] = "info:srw/cql-context-set/2/zeerex-1.1";
            $this->contextSets['mods'] = "info:srw/cql-context-set/1/mods-1.0";
            $this->contextSets['marc'] = "info:srw/cql-context-set/1/marc-1.0";
        }
    }

    function add_set($set, $id) {
        $this->contextSets[$set] = $id;
    }

    function resolve_prefix($pref) {
        if (array_key_exists($pref, $this->contextSets)) {
            return $this->contextSets[$pref];
        } else {
            return null;
        }
    }

}
